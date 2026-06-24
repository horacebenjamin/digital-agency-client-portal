<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFile>
 */
class ProjectFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'png', 'jpg', 'docx', 'xlsx']);

        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(3, true).'.'.$extension,
            'path' => 'project-files/'.fake()->uuid().'.'.$extension,
            'disk' => 'public',
            'mime_type' => fake()->randomElement([
                'application/pdf',
                'image/png',
                'image/jpeg',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
            'size' => fake()->numberBetween(50_000, 5_000_000),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
