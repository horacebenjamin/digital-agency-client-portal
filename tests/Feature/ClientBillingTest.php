<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\PaymentRequestCheckoutSessionCreator;
use App\Services\PaymentRequestPdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class ClientBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_client_can_see_their_own_payment_requests(): void
    {
        Carbon::setTestNow('2026-06-26 10:00:00');

        $client = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create(['title' => 'Client Website Refresh']);

        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Website deposit',
            'amount' => 125000,
            'status' => 'sent',
            'due_date' => '2026-07-10',
        ]);

        $this->actingAs($user)
            ->get(route('client.billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Billing/Index')
                ->has('outstandingPayments', 1)
                ->where('outstandingPayments.0.title', 'Website deposit')
                ->where('outstandingPayments.0.project_name', 'Client Website Refresh')
                ->where('outstandingPayments.0.amount', '£1,250.00')
                ->where('outstandingPayments.0.status', 'sent')
                ->where('outstandingPayments.0.status_label', 'Sent')
                ->where('outstandingPayments.0.due_date', 'Jul 10, 2026')
                ->where('outstandingPayments.0.can_pay', true)
                ->where('outstandingPayments.0.checkout_url', route('client.billing.payment-requests.checkout', PaymentRequest::first()))
                ->where('outstandingPayments.0.pdf_url', route('client.billing.payment-requests.pdf', PaymentRequest::first()))
                ->has('paidPayments', 0)
            );

        Carbon::setTestNow();
    }

    public function test_client_cannot_see_another_clients_payment_requests(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Owned payment request',
            'status' => 'sent',
        ]);
        PaymentRequest::factory()->for($otherClient)->for($otherProject)->create([
            'title' => 'Private other payment request',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('client.billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('outstandingPayments', 1)
                ->where('outstandingPayments.0.title', 'Owned payment request')
                ->has('paidPayments', 0)
            )
            ->assertDontSee('Private other payment request');
    }

    public function test_paid_and_outstanding_requests_are_grouped_correctly(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create();

        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Draft request',
            'status' => 'draft',
        ]);
        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Sent request',
            'status' => 'sent',
        ]);
        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Paid request',
            'status' => 'paid',
            'paid_at' => '2026-06-25 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('client.billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('outstandingPayments', 2)
                ->where('outstandingPayments.0.title', 'Sent request')
                ->where('outstandingPayments.0.can_pay', true)
                ->where('outstandingPayments.1.title', 'Draft request')
                ->where('outstandingPayments.1.can_pay', false)
                ->has('paidPayments', 1)
                ->where('paidPayments.0.title', 'Paid request')
                ->where('paidPayments.0.paid_date', 'Jun 25, 2026')
            );
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get(route('client.billing.index'))
            ->assertRedirect(route('login'));
    }

    public function test_client_can_start_checkout_for_their_own_sent_payment_request(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create();
        $paymentRequest = PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Website deposit',
            'amount' => 125000,
            'currency' => 'gbp',
            'status' => 'sent',
        ]);

        $this->mock(PaymentRequestCheckoutSessionCreator::class, function (MockInterface $mock) use ($paymentRequest) {
            $mock->shouldReceive('create')
                ->once()
                ->withArgs(fn (PaymentRequest $request): bool => $request->is($paymentRequest))
                ->andReturn((object) [
                    'id' => 'cs_test_123',
                    'url' => 'https://checkout.stripe.test/session',
                ]);
        });

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('client.billing.payment-requests.checkout', $paymentRequest))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/session');

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'sent',
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);
    }

    public function test_client_cannot_start_checkout_for_another_clients_payment_request(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();
        $paymentRequest = PaymentRequest::factory()->for($otherClient)->for($otherProject)->create([
            'status' => 'sent',
        ]);

        $this->mock(PaymentRequestCheckoutSessionCreator::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('create');
        });

        $this->actingAs($user)
            ->post(route('client.billing.payment-requests.checkout', $paymentRequest))
            ->assertForbidden();

        $this->assertNull($paymentRequest->refresh()->stripe_checkout_session_id);
    }

    public function test_client_cannot_checkout_a_paid_cancelled_or_draft_request(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create();

        $this->mock(PaymentRequestCheckoutSessionCreator::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('create');
        });

        foreach (['paid', 'cancelled', 'draft'] as $status) {
            $paymentRequest = PaymentRequest::factory()->for($client)->for($project)->create([
                'status' => $status,
            ]);

            $this->actingAs($user)
                ->post(route('client.billing.payment-requests.checkout', $paymentRequest))
                ->assertForbidden();

            $this->assertNull($paymentRequest->refresh()->stripe_checkout_session_id);
        }
    }

    public function test_unauthenticated_users_are_redirected_to_login_when_starting_checkout(): void
    {
        $paymentRequest = PaymentRequest::factory()->create([
            'status' => 'sent',
        ]);

        $this->post(route('client.billing.payment-requests.checkout', $paymentRequest))
            ->assertRedirect(route('login'));
    }

    public function test_client_can_download_their_own_payment_request_pdf(): void
    {
        $client = Client::factory()->create(['company_name' => 'Acme Studio']);
        $user = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create(['title' => 'Website Refresh']);
        $paymentRequest = PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Website deposit',
            'amount' => 125000,
            'currency' => 'gbp',
            'status' => 'sent',
            'due_date' => '2026-07-10',
        ]);

        $response = $this->actingAs($user)
            ->get(route('client.billing.payment-requests.pdf', $paymentRequest))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('payment-request-'.$paymentRequest->id.'-website-deposit.pdf', $response->headers->get('content-disposition'));
    }

    public function test_client_cannot_download_another_clients_payment_request_pdf(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $paymentRequest = PaymentRequest::factory()->for($otherClient)->create([
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('client.billing.payment-requests.pdf', $paymentRequest))
            ->assertForbidden();
    }

    public function test_paid_payment_request_pdf_data_includes_receipt_information(): void
    {
        Carbon::setTestNow('2026-06-29 14:00:00');

        $client = Client::factory()->create(['company_name' => 'Acme Studio']);
        $project = Project::factory()->for($client)->create(['title' => 'Website Refresh']);
        $paymentRequest = PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Final balance',
            'amount' => 250000,
            'currency' => 'gbp',
            'status' => 'paid',
            'paid_at' => '2026-06-28 09:30:00',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $data = app(PaymentRequestPdfGenerator::class)->data($paymentRequest);

        $this->assertSame('Payment Receipt', $data['documentTitle']);
        $this->assertSame('Receipt #'.$paymentRequest->id, $data['documentReference']);
        $this->assertSame('support@digitalagency.test', $data['agencyEmail']);
        $this->assertSame('Acme Studio', $data['clientName']);
        $this->assertSame('Website Refresh', $data['projectName']);
        $this->assertSame('£2,500.00', $data['amount']);
        $this->assertSame('Paid', $data['status']);
        $this->assertSame('Jun 28, 2026 9:30am', $data['paidDate']);
        $this->assertSame('pi_test_123', $data['stripePaymentId']);
        $this->assertSame('Jun 29, 2026 2:00pm', $data['generatedDate']);

        Carbon::setTestNow();
    }

    public function test_unpaid_payment_requests_still_generate_payment_request_pdf_data(): void
    {
        $client = Client::factory()->create(['company_name' => 'Acme Studio']);
        $paymentRequest = PaymentRequest::factory()->for($client)->create([
            'title' => 'Planning deposit',
            'amount' => 50000,
            'currency' => 'gbp',
            'status' => 'sent',
            'paid_at' => null,
            'stripe_payment_intent_id' => null,
        ]);

        $data = app(PaymentRequestPdfGenerator::class)->data($paymentRequest);

        $this->assertSame('Payment Request', $data['documentTitle']);
        $this->assertSame('Payment Request #'.$paymentRequest->id, $data['documentReference']);
        $this->assertSame('support@digitalagency.test', $data['agencyEmail']);
        $this->assertSame('£500.00', $data['amount']);
        $this->assertSame('Sent', $data['status']);
        $this->assertNull($data['paidDate']);
        $this->assertNull($data['stripePaymentId']);
    }
}
