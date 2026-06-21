<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_conversation_for_a_scenario(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "Hi, thanks for coming in today. Let's get started."]],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->post('/conversations', [
                'scenario_id' => $scenario->id,
            ]);

        $conversation = Conversation::first();
        $this->assertNotNull($conversation);
        $this->assertEquals($user->id, $conversation->user_id);
        $this->assertEquals($scenario->id, $conversation->scenario_id);

        $response->assertRedirect(route('conversations.show', $conversation->id));

        // The opening AI message should be saved
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => "Hi, thanks for coming in today. Let's get started.",
        ]);

        Http::assertSentCount(1);
    }

    public function test_creating_conversation_clears_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        Cache::put("dashboard.{$user->id}", ['fake' => 'cached-data'], 3600);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Opening line.']]],
            ], 200),
        ]);

        $this->actingAs($user)->post('/conversations', [
            'scenario_id' => $scenario->id,
        ]);

        $this->assertNull(Cache::get("dashboard.{$user->id}"));
    }

    public function test_user_can_complete_a_conversation(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Tell me about yourself.']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'I have five years of experience in product design.']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'final'        => 72,
                            'clarity'      => 75,
                            'confidence'   => 70,
                            'objective'    => 70,
                            'adaptability' => 73,
                            'feedback'     => 'Solid start, but the session was short.',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->post("/conversations/{$conversation->id}/complete");

        $response->assertRedirect(route('conversations.show', $conversation->id));

        $conversation->refresh();

        $this->assertEquals('completed', $conversation->status);
        $this->assertEquals(72 > 0, $conversation->scores['final'] > 0); 
        $this->assertArrayHasKey('feedback', $conversation->scores);
    }

    public function test_completing_an_already_completed_conversation_does_not_call_ai_again(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->completed()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
        ]);

        Http::fake(); 

        $response = $this->actingAs($user)
            ->post("/conversations/{$conversation->id}/complete");

        $response->assertRedirect(route('conversations.show', $conversation->id));

        Http::assertNothingSent();
    }

    public function test_completing_conversation_clears_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        Cache::put("dashboard.{$user->id}", ['fake' => 'cached-data'], 3600);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'final' => 50, 'clarity' => 50, 'confidence' => 50,
                    'objective' => 50, 'adaptability' => 50, 'feedback' => 'ok',
                ])]]],
            ], 200),
        ]);

        $this->actingAs($user)->post("/conversations/{$conversation->id}/complete");

        $this->assertNull(Cache::get("dashboard.{$user->id}"));
    }

    public function test_groq_failure_during_complete_falls_back_gracefully(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($user)
            ->post("/conversations/{$conversation->id}/complete");

        $response->assertRedirect(route('conversations.show', $conversation->id));

        $conversation->refresh();

        $this->assertEquals('completed', $conversation->status);
        $this->assertNotNull($conversation->scores);
    }
}