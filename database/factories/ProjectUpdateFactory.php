<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectUpdate>
 */
class ProjectUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(3, true),
            'status' => fake()->randomElement(['draft', 'published']),
        ];
    }
}
