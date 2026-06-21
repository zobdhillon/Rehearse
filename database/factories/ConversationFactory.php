<?php

namespace Database\Factories;

use App\Models\Scenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'scenario_id' => Scenario::factory(),
            'scores'      => null,
            'status'      => 'active',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'scores' => [
                'final'        => 75,
                'clarity'      => 80,
                'confidence'   => 70,
                'objective'    => 75,
                'adaptability' => 75,
                'feedback'     => 'Good session overall.',
            ],
        ]);
    }
}