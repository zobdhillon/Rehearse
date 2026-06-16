<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'message_text' => 'required|string|max:2000',
        ]);

        try {
            $result = DB::transaction(function () use ($conversation, $validated) {
                $conversation->messages()->create([
                    'role'    => 'user',
                    'content' => $validated['message_text'],
                ]);

                $userMessageCount = $conversation->messages()
                    ->where('role', 'user')
                    ->count();

                $autoComplete = $userMessageCount >= 10;

                if ($autoComplete) {
                    $scores = $this->scoreConversation($conversation);

                    $conversation->update([
                        'scores' => $scores,
                        'status' => 'completed',
                    ]);

                    Cache::forget("dashboard." . $conversation->user_id);

                    return [
                        'message'       => null,
                        'auto_complete' => true,
                        'scores'        => $scores,
                        'status'        => 'completed',
                    ];
                }

                $aiMessage = $this->getAiReply($conversation);

                return [
                    'message'       => $aiMessage,
                    'auto_complete' => false,
                ];
            });

            return response()->json($result);
        } catch (ConnectionException $e) {
            if (str_contains(strtolower($e->getMessage()), 'timeout') || str_contains(strtolower($e->getMessage()), 'timed out')) {
                return response()->json([
                    'message' => 'The AI took too long to respond. Please try again.'
                ], 504);
            }
            return response()->json([
                'message' => 'AI service is temporarily unavailable. Please try again.'
            ], 503);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'JSON_PARSE_FAILURE') {
                return response()->json([
                    'message' => 'Received an unexpected response from AI. Please retry.'
                ], 422);
            }
            Log::channel('ai')->error('Message store RuntimeException', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Something went wrong. Please try again.'
            ], 500);
        } catch (\Exception $e) {
            Log::channel('ai')->error('Message store exception', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    private function getAiReply(Conversation $conversation): array
    {
        $scenario = $conversation->scenario;
        $history  = $conversation->messages()->orderBy('created_at')->get();

        $formatted   = [];
        $formatted[] = [
            'role'    => 'system',
            'content' => $scenario->system_prompt . "\n\n" .
                "CRITICAL RULES — NEVER BREAK THESE:\n" .
                "- Speak in natural, conversational dialogue ONLY. Never write emails, letters, or formal documents.\n" .
                "- You are in a live spoken conversation. Respond the way a real person talks.\n" .
                "- NEVER use placeholders like [Candidate Name] or [Salary Amount]. Use real specifics.\n" .
                "- NEVER break character under any circumstances.\n" .
                "- NEVER mention being an AI, a language model, or a simulation.\n" .
                "- Keep responses to 1-3 sentences max. Short and natural.\n" .
                "- NEVER write greetings like 'Dear...' or sign offs like 'Best regards'.\n" .
                "- If you are an HR manager, speak like one in a real meeting — direct and professional.\n" .
                "- Always respond to what the user just said. Never ignore their last message.",
        ];

        foreach ($history as $msg) {
            if ($msg->role === 'system') continue;
            $formatted[] = [
                'role'    => $msg->role,
                'content' => $msg->content,
            ];
        }

        $startTime = microtime(true);

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.groq.key'),
                'Content-Type'  => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => config('services.groq.model'),
                'messages'    => $formatted,
                'temperature' => 0.4,
                'max_tokens'  => 150,
            ]);

        $latency = round((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            Log::channel('ai')->error('Message reply API non-200', [
                'conversation_id' => $conversation->id,
                'status'          => $response->status(),
                'latency_ms'      => $latency,
            ]);
            $response->throw();
        }

        $content = $response->json('choices.0.message.content');

        if ($content === null) {
            Log::channel('ai')->error('Message reply JSON parse failure', [
                'conversation_id' => $conversation->id,
                'latency_ms'      => $latency,
                'raw_response'    => $response->body(),
            ]);
            throw new \RuntimeException('JSON_PARSE_FAILURE');
        }

        Log::channel('ai')->info('Message reply generated', [
            'conversation_id' => $conversation->id,
            'latency_ms'      => $latency,
            'response_length' => strlen($content),
        ]);

        $saved = $conversation->messages()->create([
            'role'    => 'assistant',
            'content' => $content,
        ]);

        return [
            'id'      => $saved->id,
            'role'    => $saved->role,
            'content' => $saved->content,
        ];
    }

    private function scoreConversation(Conversation $conversation): array
    {
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $transcript = $messages->map(function ($msg) {
            $label = $msg->role === 'user' ? 'Candidate' : 'Interviewer';
            return "{$label}: {$msg->content}";
        })->implode("\n");

        $prompt = "You are an expert communication coach. Review this conversation transcript and evaluate the user's (Candidate) performance.\n\n" .
            "TRANSCRIPT:\n{$transcript}\n\n" .
            "Score the candidate on these 4 dimensions out of 100:\n" .
            "- clarity: How clearly did they communicate?\n" .
            "- confidence: How confident and assertive were they?\n" .
            "- objective: How well did they achieve the scenario goal?\n" .
            "- adaptability: How well did they respond to pushback?\n\n" .
            "Calculate final as the average of all 4 scores.\n" .
            "Write 2-3 sentences of specific, actionable feedback.\n\n" .
            "CRITICAL: Return ONLY valid JSON, no markdown, no backticks:\n" .
            '{"final":85,"clarity":90,"confidence":80,"objective":85,"adaptability":85,"feedback":"Your feedback here."}';

        $startTime = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.groq.key'),
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => config('services.groq.model'),
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.1,
                    'max_tokens'  => 300,
                ]);

            $latency = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $raw    = $response->json('choices.0.message.content');
                $clean  = preg_replace('/```json|```/', '', $raw);
                $scores = json_decode(trim($clean), true);

                if ($scores && isset($scores['final'])) {
                    Log::channel('ai')->info('Auto-scoring completed', [
                        'conversation_id' => $conversation->id,
                        'latency_ms'      => $latency,
                        'final_score'     => $scores['final'],
                    ]);

                    return $scores;
                }

                Log::channel('ai')->warning('Auto-scoring JSON invalid — using fallback', [
                    'conversation_id' => $conversation->id,
                    'latency_ms'      => $latency,
                    'raw'             => $raw,
                ]);
            } else {
                Log::channel('ai')->error('Auto-scoring API non-200', [
                    'conversation_id' => $conversation->id,
                    'status'          => $response->status(),
                    'latency_ms'      => $latency,
                ]);
            }
        } catch (\Exception $e) {
            $latency = round((microtime(true) - $startTime) * 1000);

            Log::channel('ai')->error('Auto-scoring API exception', [
                'conversation_id' => $conversation->id,
                'latency_ms'      => $latency,
                'error'           => $e->getMessage(),
            ]);
        }

        return [
            'final'        => 0,
            'clarity'      => 0,
            'confidence'   => 0,
            'objective'    => 0,
            'adaptability' => 0,
            'feedback'     => 'Evaluation could not be completed. Please try again.',
        ];
    }
}
