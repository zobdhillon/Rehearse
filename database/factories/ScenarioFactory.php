<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ScenarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'              => $this->faker->sentence(3),
            'description'        => $this->faker->paragraph(),
            'system_prompt'      => 'You are an interviewer conducting a mock interview. Stay in character.',
            'user_role'          => 'Candidate',
            'ai_role'            => 'Interviewer',
            'objectives'         => ['Communicate clearly', 'Stay confident under pressure'],
            'category'           => 'General',
            'difficulty'         => 'Intermediate',
            'estimated_duration' => 15,
            'icon'               => 'message',
            'color'              => 'purple',
            'is_active'          => true,
            'order'              => 0,
        ];
    }
}
