<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function index()
    {
        $conversations = Conversation::with('scenario')
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'created_at'   => $c->created_at,
                'scenario'     => $c->scenario,
                'score'        => $c->scores['final'] ?? null,
                'is_completed' => $c->status === 'completed',
            ]);

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'scenario_id' => 'required|exists:scenarios,id',
        ]);

        $conversation = Conversation::create([
            'user_id'     => auth()->id(),
            'scenario_id' => $validated['scenario_id'],
        ]);

        $scenario = $conversation->scenario;
        $formattedMessages = [];

        $strongSystemInstruction = $scenario->system_prompt . "\n\n" .
            "CRITICAL RULES — NEVER BREAK THESE:\n" .
            "- Speak in natural, conversational dialogue ONLY. Never write emails, letters, or formal documents.\n" .
            "- You are in a live spoken conversation. Respond the way a real person talks.\n" .
            "- NEVER use placeholders like [Candidate Name] or [Salary Amount]. Use real specifics.\n" .
            "- NEVER break character under any circumstances.\n" .
            "- NEVER mention being an AI, a language model, or a simulation.\n" .
            "- Keep responses to 1-3 sentences max. Short and natural.\n" .
            "- NEVER write greetings like 'Dear...' or sign offs like 'Best regards'.\n" .
            "- If you are an HR manager, speak like one in a real meeting — direct and professional.\n" .
            "- Always respond to what the user just said. Never ignore their last message.";

        $formattedMessages[] = [
            'role'    => 'system',
            'content' => $strongSystemInstruction,
        ];

        $formattedMessages[] = [
            'role'    => 'user',
            'content' => "Begin the simulation now. Speak your opening line in character. Maximum 2 sentences. No commentary, just dialogue."
        ];

        $startTime = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.groq.key'),
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => config('services.groq.model'),
                    'messages'    => $formattedMessages,
                    'temperature' => 0.4,
                    'max_tokens'  => 150,
                ]);

            $latency = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $aiContent = $response->json('choices.0.message.content');

                $conversation->messages()->create([
                    'role'    => 'assistant',
                    'content' => $aiContent,
                ]);

                Cache::forget("dashboard." . auth()->id());

                Log::channel('ai')->info('Opening message generated', [
                    'conversation_id' => $conversation->id,
                    'scenario_id'     => $conversation->scenario_id,
                    'latency_ms'      => $latency,
                    'response_length' => strlen($aiContent),
                ]);
            } else {
                Log::channel('ai')->error('Opening message API non-200', [
                    'conversation_id' => $conversation->id,
                    'scenario_id'     => $conversation->scenario_id,
                    'status'          => $response->status(),
                    'latency_ms'      => $latency,
                ]);
            }
        } catch (\Exception $e) {
            $latency = round((microtime(true) - $startTime) * 1000);

            Log::channel('ai')->error('Opening message API exception', [
                'conversation_id' => $conversation->id,
                'scenario_id'     => $conversation->scenario_id,
                'latency_ms'      => $latency,
                'error'           => $e->getMessage(),
            ]);
        }

        return redirect()->route('conversations.show', $conversation->id);
    }

    public function show(Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation->load(['messages', 'scenario']),
        ]);
    }

    public function complete(Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        if ($conversation->status === 'completed') {
            return redirect()->route('conversations.show', $conversation->id);
        }

        $messages = $conversation->messages()->orderBy('created_at')->get();

        $transcript = $messages->map(function ($msg) {
            $label = $msg->role === 'user' ? 'Candidate' : 'Interviewer';
            return "{$label}: {$msg->content}";
        })->implode("\n");

        $scoringPrompt = "You are an expert communication coach. Review this conversation transcript and evaluate the user's (Candidate) performance.\n\n" .
            "TRANSCRIPT:\n{$transcript}\n\n" .
            "Score the candidate on these 4 dimensions out of 100:\n" .
            "- clarity: How clearly did they communicate their points?\n" .
            "- confidence: How confident and assertive were they?\n" .
            "- objective: How well did they achieve the scenario goal?\n" .
            "- adaptability: How well did they respond to pushback and adapt?\n\n" .
            "Calculate final as the average of all 4 scores.\n\n" .
            "Also write 2-3 sentences of specific, actionable feedback.\n\n" .
            "CRITICAL: Return ONLY a valid JSON object, no extra text, no markdown, no backticks:\n" .
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
                    'messages'    => [['role' => 'user', 'content' => $scoringPrompt]],
                    'temperature' => 0.1,
                    'max_tokens'  => 300,
                ]);

            $latency = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $raw    = $response->json('choices.0.message.content');
                $clean  = preg_replace('/```json|```/', '', $raw);
                $scores = json_decode(trim($clean), true);

                $validScores = $scores && isset($scores['final']);

                Log::channel('ai')->info('Scoring completed', [
                    'conversation_id' => $conversation->id,
                    'latency_ms'      => $latency,
                    'scores_valid'    => $validScores,
                    'final_score'     => $scores['final'] ?? null,
                ]);

                $conversation->update([
                    'scores' => $validScores ? $scores : $this->fallbackScores(),
                    'status' => 'completed',
                ]);
            } else {
                Log::channel('ai')->error('Scoring API non-200', [
                    'conversation_id' => $conversation->id,
                    'status'          => $response->status(),
                    'latency_ms'      => $latency,
                ]);

                $conversation->update([
                    'scores' => $this->fallbackScores(),
                    'status' => 'completed',
                ]);
            }

            Cache::forget("dashboard." . $conversation->user_id);
        } catch (\Exception $e) {
            $latency = round((microtime(true) - $startTime) * 1000);

            Log::channel('ai')->error('Scoring API exception', [
                'conversation_id' => $conversation->id,
                'latency_ms'      => $latency,
                'error'           => $e->getMessage(),
            ]);

            $conversation->update([
                'scores' => $this->fallbackScores(),
                'status' => 'completed',
            ]);

            Cache::forget("dashboard." . $conversation->user_id);
        }

        return redirect()->route('conversations.show', $conversation->id);
    }

    public function export(Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $conversation->load(['scenario', 'messages']);

        $pdf = Pdf::loadView('pdf.transcript', [
            'conversation' => $conversation,
            'scenario'     => $conversation->scenario,
            'messages'     => $conversation->messages,
            'scores'       => $conversation->scores,
        ]);

        $filename = 'transcript-' . str($conversation->scenario->title)->slug() . '-' . $conversation->id . '.pdf';

        return $pdf->download($filename);
    }

    private function fallbackScores(): array
    {
        return [
            'clarity'      => 50,
            'confidence'   => 50,
            'objective'    => 50,
            'adaptability' => 50,
            'final'        => 50,
            'feedback'     => 'Session completed. Unable to generate detailed feedback this time.',
        ];
    }
}
