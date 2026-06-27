<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PaymentRequest;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_request_can_be_created(): void
    {
        $paymentRequest = PaymentRequest::factory()->create([
            'title' => 'Website deposit',
            'amount' => 125000,
        ]);

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'title' => 'Website deposit',
            'amount' => 125000,
        ]);
    }

    public function test_payment_request_belongs_to_a_client_and_project(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $paymentRequest = PaymentRequest::factory()
            ->for($client)
            ->for($project)
            ->create();

        $this->assertTrue($paymentRequest->client->is($client));
        $this->assertTrue($paymentRequest->project->is($project));
        $this->assertTrue($client->paymentRequests->contains($paymentRequest));
        $this->assertTrue($project->paymentRequests->contains($paymentRequest));
    }

    public function test_amount_is_stored_in_pence(): void
    {
        $paymentRequest = PaymentRequest::factory()->create([
            'amount' => 19999,
        ]);

        $this->assertSame(19999, $paymentRequest->refresh()->amount);
    }

    public function test_status_defaults_to_draft(): void
    {
        $paymentRequest = PaymentRequest::create([
            'title' => 'Strategy workshop',
            'amount' => 75000,
        ]);

        $this->assertSame('draft', $paymentRequest->refresh()->status);
    }

    public function test_payment_request_rejects_project_from_another_client(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $project = Project::factory()->for($otherClient)->create();

        $this->expectException(ValidationException::class);

        PaymentRequest::factory()
            ->for($client)
            ->for($project)
            ->create();
    }
}
