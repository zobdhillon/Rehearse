<?php

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_scenario_list(): void
    {
        $user = User::factory()->create();

        Scenario::factory()->count(3)->create(['is_active' => true]);
        Scenario::factory()->create(['is_active' => false]); 

        $response = $this->actingAs($user)->get('/scenarios');

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/scenarios');

        $response->assertRedirect('/login');
    }
}
