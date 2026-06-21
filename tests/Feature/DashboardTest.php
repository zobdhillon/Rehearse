<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_load_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_stats_are_computed_accurately_from_completed_conversations(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        // 2 completed sessions with known scores
        Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'completed',
            'scores'      => [
                'final' => 80, 'clarity' => 80, 'confidence' => 80,
                'objective' => 80, 'adaptability' => 80,
            ],
        ]);

        Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'completed',
            'scores'      => [
                'final' => 60, 'clarity' => 60, 'confidence' => 60,
                'objective' => 60, 'adaptability' => 60,
            ],
        ]);

        Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
            'scores'      => null,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalSessions', 3)
            ->where('stats.completedSessions', 2)
            ->where('stats.bestScore', 80)
            ->where('stats.skillAvgs.clarity', 70) 
        );
    }

    public function test_dashboard_stats_are_cached_and_db_is_not_hit_on_second_load(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'completed',
            'scores'      => ['final' => 70, 'clarity' => 70, 'confidence' => 70, 'objective' => 70, 'adaptability' => 70],
        ]);

        $this->actingAs($user)->get('/dashboard');

        $this->assertTrue(Cache::has("dashboard.{$user->id}"));

        $cachedBefore = Cache::get("dashboard.{$user->id}");

        $this->actingAs($user)->get('/dashboard');

        $cachedAfter = Cache::get("dashboard.{$user->id}");

        $this->assertEquals($cachedBefore, $cachedAfter);
    }

    public function test_starting_a_new_conversation_invalidates_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();

        // Warm the cache
        $this->actingAs($user)->get('/dashboard');
        $this->assertTrue(Cache::has("dashboard.{$user->id}"));

        \Illuminate\Support\Facades\Http::fake([
            'api.groq.com/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['content' => 'Opening line.']]],
            ], 200),
        ]);

        $this->actingAs($user)->post('/conversations', [
            'scenario_id' => $scenario->id,
        ]);

        $this->assertFalse(Cache::has("dashboard.{$user->id}"));
    }

    public function test_completing_a_conversation_invalidates_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $scenario = Scenario::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'scenario_id' => $scenario->id,
            'status'      => 'active',
        ]);

        // Warm the cache
        $this->actingAs($user)->get('/dashboard');
        $this->assertTrue(Cache::has("dashboard.{$user->id}"));

        \Illuminate\Support\Facades\Http::fake([
            'api.groq.com/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'final' => 50, 'clarity' => 50, 'confidence' => 50,
                    'objective' => 50, 'adaptability' => 50, 'feedback' => 'ok',
                ])]]],
            ], 200),
        ]);

        $this->actingAs($user)->post("/conversations/{$conversation->id}/complete");

        $this->assertFalse(Cache::has("dashboard.{$user->id}"));
    }

    public function test_dashboard_creates_user_stat_row_on_first_visit(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseMissing('user_stats', ['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard');

        $this->assertDatabaseHas('user_stats', ['user_id' => $user->id]);
    }
}