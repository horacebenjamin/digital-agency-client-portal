<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketComment>
 */
class SupportTicketCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'created_by' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => fake()->boolean(20),
        ];
    }
}
