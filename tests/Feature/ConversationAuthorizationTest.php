<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_post_message_to_another_users_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $scenario = Scenario::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id'     => $userA->id,
            'scenario_id' => $scenario->id,
        ]);

        $response = $this->actingAs($userB)
            ->postJson("/conversations/{$conversation->id}/messages", [
                'message_text' => 'Trying to hijack this session',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'content'         => 'Trying to hijack this session',
        ]);
    }

    public function test_user_cannot_view_another_users_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $scenario = Scenario::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id'     => $userA->id,
            'scenario_id' => $scenario->id,
        ]);

        $response = $this->actingAs($userB)
            ->get("/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_complete_another_users_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $scenario = Scenario::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id'     => $userA->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        $response = $this->actingAs($userB)
            ->post("/conversations/{$conversation->id}/complete");

        $response->assertStatus(403);

        $this->assertDatabaseHas('conversations', [
            'id'     => $conversation->id,
            'status' => 'active',
        ]);
    }

    public function test_user_cannot_export_another_users_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $scenario = Scenario::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id'     => $userA->id,
            'scenario_id' => $scenario->id,
        ]);

        $response = $this->actingAs($userB)
            ->get("/conversations/{$conversation->id}/export");

        $response->assertStatus(403);
    }

    public function test_owner_can_view_their_own_conversation(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
        ]);

        $response = $this->actingAs($user)
            ->get("/conversations/{$conversation->id}");

        $response->assertStatus(200);
    }
}
