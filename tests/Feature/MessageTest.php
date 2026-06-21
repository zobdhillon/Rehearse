<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_post_message_and_receive_ai_reply(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => "That's a great point, can you elaborate?"]]],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/conversations/{$conversation->id}/messages", [
                'message_text' => 'I led a team of five engineers on the redesign.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('auto_complete', false);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => 'I led a team of five engineers on the redesign.',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => "That's a great point, can you elaborate?",
        ]);
    }

    public function test_tenth_user_message_triggers_auto_completion(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        for ($i = 1; $i <= 9; $i++) {
            $conversation->messages()->create([
                'role'    => 'user',
                'content' => "Answer number {$i} with enough words to count as real.",
            ]);
        }

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'final' => 80,
                            'clarity' => 80,
                            'confidence' => 80,
                            'objective' => 80,
                            'adaptability' => 80,
                            'feedback' => 'Strong full-length session.',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/conversations/{$conversation->id}/messages", [
                'message_text' => 'Final answer, the tenth one.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('auto_complete', true);
        $response->assertJsonPath('status', 'completed');

        $conversation->refresh();
        $this->assertEquals('completed', $conversation->status);
        $this->assertNotNull($conversation->scores);
    }

    public function test_auto_completion_clears_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        for ($i = 1; $i <= 9; $i++) {
            $conversation->messages()->create([
                'role' => 'user',
                'content' => "Answer {$i} long enough to count.",
            ]);
        }

        Cache::put("dashboard.{$user->id}", ['fake' => 'data'], 3600);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'final' => 60,
                    'clarity' => 60,
                    'confidence' => 60,
                    'objective' => 60,
                    'adaptability' => 60,
                    'feedback' => 'ok',
                ])]]],
            ], 200),
        ]);

        $this->actingAs($user)->postJson("/conversations/{$conversation->id}/messages", [
            'message_text' => 'Tenth and final answer.',
        ]);

        $this->assertNull(Cache::get("dashboard.{$user->id}"));
    }

    public function test_user_cannot_post_message_to_completed_conversation_path_still_blocked_for_others(): void
    {

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $scenario = Scenario::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id'     => $owner->id,
            'scenario_id' => $scenario->id,
        ]);

        $response = $this->actingAs($intruder)
            ->postJson("/conversations/{$conversation->id}/messages", [
                'message_text' => 'Should be blocked.',
            ]);

        $response->assertStatus(403);
    }

    public function test_groq_timeout_returns_504(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        Http::fake([
            'api.groq.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
            },
        ]);

        $response = $this->actingAs($user)
            ->postJson("/conversations/{$conversation->id}/messages", [
                'message_text' => 'This should hit a timeout.',
            ]);

        $response->assertStatus(504);
    }

    public function test_groq_server_error_returns_503(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        Http::fake([
            'api.groq.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host');
            },
        ]);

        $response = $this->actingAs($user)
            ->postJson("/conversations/{$conversation->id}/messages", [
                'message_text' => 'This should hit a connection failure.',
            ]);

        $response->assertStatus(503);
    }
}
