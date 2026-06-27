<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\PaymentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRequest>
 */
class PaymentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'project_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'amount' => fake()->numberBetween(5000, 250000),
            'currency' => 'gbp',
            'status' => 'draft',
            'due_date' => fake()->optional(0.8)->dateTimeBetween('now', '+2 months'),
            'paid_at' => null,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
        ];
    }
}
