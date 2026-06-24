<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->optional(0.7)->dateTimeBetween('-6 months', 'now');
        $status = fake()->randomElement(['planning', 'in_progress', 'on_hold', 'completed']);

        return [
            'client_id' => Client::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraphs(2, true),
            'status' => $status,
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'due_date' => fake()->optional(0.8)->dateTimeBetween('now', '+4 months'),
            'started_at' => $startedAt,
            'completed_at' => $status === 'completed' ? fake()->dateTimeBetween($startedAt ?? '-2 months', 'now') : null,
        ];
    }
}
