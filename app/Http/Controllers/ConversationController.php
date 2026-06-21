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

    private const EXPECTED_USER_TURNS = 10;

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
        $this->authorize('view', $conversation);

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation->load(['messages', 'scenario']),
        ]);
    }
    public function complete(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        if ($conversation->status === 'completed') {
            return redirect()->route('conversations.show', $conversation->id);
        }

        $messages = $conversation->messages()->orderBy('created_at')->get();

        $userTurns      = $messages->where('role', 'user');
        $userTurnCount  = $userTurns->count();
        $expectedTurns  = self::EXPECTED_USER_TURNS;
        $completionRate = $expectedTurns > 0
            ? (int) round(min($userTurnCount / $expectedTurns, 1) * 100)
            : 100;

        $substantiveAnswers = $userTurns->filter(function ($msg) {
            return str_word_count($msg->content) >= 4;
        })->count();

        $transcript = $messages->map(function ($msg) {
            $label = $msg->role === 'user' ? 'Candidate' : 'Interviewer';
            return "{$label}: {$msg->content}";
        })->implode("\n");

        $scoringPrompt = "You are a STRICT communication coach grading a practice interview. " .
            "Do not be lenient or encouraging in your scoring — score based only on what is actually in the transcript.\n\n" .

            "SESSION COVERAGE (you MUST factor this into every score):\n" .
            "- Expected candidate turns for a complete session: {$expectedTurns}\n" .
            "- Actual candidate turns provided: {$userTurnCount}\n" .
            "- Turns with a substantive answer (4+ words): {$substantiveAnswers}\n" .
            "- Session completion: {$completionRate}%\n\n" .

            "STRICT SCORING RULES:\n" .
            "1. If completion is below 100%, NONE of the 4 dimension scores may exceed the completion percentage " .
            "(e.g. if completion is 20%, no score can be above 20), because an incomplete session cannot " .
            "demonstrate full competence regardless of how good the few answers were.\n" .
            "2. Unanswered or skipped questions (interviewer turns with no matching candidate response, " .
            "or one-word/low-effort replies) count as a failure on that exchange — do not skip over them when scoring.\n" .
            "3. A short, well-phrased answer to 2 out of 10 questions does NOT deserve a high score just because " .
            "those 2 answers were good. Evaluate coverage of the full scenario, not just answer quality in isolation.\n" .
            "4. Do not round up or give benefit of the doubt. If you are unsure, score lower.\n" .
            "5. objective (goal achievement) should be scored especially harshly if the candidate ended the " .
            "session before the scenario's goal could reasonably be reached.\n\n" .

            "TRANSCRIPT:\n{$transcript}\n\n" .

            "Score the candidate on these 4 dimensions out of 100, applying the rules above:\n" .
            "- clarity: How clearly did they communicate across the ENTIRE session, not just answered turns?\n" .
            "- confidence: How confident and assertive were they, accounting for any avoided questions?\n" .
            "- objective: How well did they achieve the full scenario goal, given how much they actually completed?\n" .
            "- adaptability: How well did they respond to pushback across the whole session?\n\n" .

            "Calculate final as the average of all 4 scores, rounded down (not up).\n" .
            "Write 2-3 sentences of specific, honest feedback. If the session was incomplete, say so directly " .
            "and tell them to finish more questions next time — do not soften this.\n\n" .

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

                $finalScores = $validScores
                    ? $this->enforceCompletionCap($scores, $completionRate)
                    : $this->fallbackScores($completionRate);

                Log::channel('ai')->info('Scoring completed', [
                    'conversation_id'  => $conversation->id,
                    'latency_ms'       => $latency,
                    'scores_valid'     => $validScores,
                    'final_score'      => $finalScores['final'],
                    'completion_rate'  => $completionRate,
                    'user_turn_count'  => $userTurnCount,
                ]);

                $conversation->update([
                    'scores' => $finalScores,
                    'status' => 'completed',
                ]);
            } else {
                Log::channel('ai')->error('Scoring API non-200', [
                    'conversation_id' => $conversation->id,
                    'status'          => $response->status(),
                    'latency_ms'      => $latency,
                ]);

                $conversation->update([
                    'scores' => $this->fallbackScores($completionRate),
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
                'scores' => $this->fallbackScores($completionRate),
                'status' => 'completed',
            ]);

            Cache::forget("dashboard." . $conversation->user_id);
        }

        return redirect()->route('conversations.show', $conversation->id);
    }

    public function export(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

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

    private function enforceCompletionCap(array $scores, int $completionRate): array
    {
        $dimensions = ['clarity', 'confidence', 'objective', 'adaptability'];

        foreach ($dimensions as $dim) {
            if (isset($scores[$dim])) {
                $scores[$dim] = min((int) $scores[$dim], $completionRate);
            }
        }

        $present = array_filter($dimensions, fn($d) => isset($scores[$d]));
        $scores['final'] = $present
            ? (int) floor(array_sum(array_map(fn($d) => $scores[$d], $present)) / count($present))
            : 0;

        $scores['completion_rate'] = $completionRate;

        return $scores;
    }

    private function fallbackScores(int $completionRate = 100): array
    {
        $capped = min(50, $completionRate);

        return [
            'clarity'         => $capped,
            'confidence'      => $capped,
            'objective'       => $capped,
            'adaptability'    => $capped,
            'final'           => $capped,
            'completion_rate' => $completionRate,
            'feedback'        => $completionRate < 100
                ? "Session ended early ({$completionRate}% complete). Unable to generate detailed feedback this time — try finishing more questions next session."
                : 'Session completed. Unable to generate detailed feedback this time.',
        ];
    }

    public function destroy(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->delete();

        return redirect()->route('conversations.index')
            ->with('success', 'Session deleted.');
    }
}
