<?php

namespace Tests\Feature;

use App\Models\PaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
    }

    public function test_checkout_session_completed_marks_matching_payment_request_as_paid(): void
    {
        Carbon::setTestNow('2026-06-26 12:00:00');

        $paymentRequest = PaymentRequest::factory()->create([
            'status' => 'sent',
            'paid_at' => null,
            'stripe_checkout_session_id' => 'cs_test_matching',
        ]);

        $this->postStripeWebhook($this->checkoutSessionCompletedPayload('cs_test_matching'))
            ->assertNoContent();

        $paymentRequest->refresh();

        $this->assertSame('paid', $paymentRequest->status);
        $this->assertTrue($paymentRequest->paid_at->equalTo(now()));

        Carbon::setTestNow();
    }

    public function test_webhook_stores_stripe_payment_intent_id(): void
    {
        $paymentRequest = PaymentRequest::factory()->create([
            'status' => 'sent',
            'stripe_checkout_session_id' => 'cs_test_matching',
            'stripe_payment_intent_id' => null,
        ]);

        $this->postStripeWebhook($this->checkoutSessionCompletedPayload(
            sessionId: 'cs_test_matching',
            paymentIntentId: 'pi_test_123'
        ))->assertNoContent();

        $this->assertSame('pi_test_123', $paymentRequest->refresh()->stripe_payment_intent_id);
    }

    public function test_unknown_session_id_does_not_crash(): void
    {
        $this->postStripeWebhook($this->checkoutSessionCompletedPayload('cs_test_unknown'))
            ->assertNoContent();

        $this->assertDatabaseCount('payment_requests', 0);
    }

    public function test_already_paid_request_is_not_overwritten(): void
    {
        $paidAt = Carbon::parse('2026-06-20 09:30:00');

        $paymentRequest = PaymentRequest::factory()->create([
            'status' => 'paid',
            'paid_at' => $paidAt,
            'stripe_checkout_session_id' => 'cs_test_matching',
            'stripe_payment_intent_id' => 'pi_existing',
        ]);

        Carbon::setTestNow('2026-06-26 12:00:00');

        $this->postStripeWebhook($this->checkoutSessionCompletedPayload(
            sessionId: 'cs_test_matching',
            paymentIntentId: 'pi_new'
        ))->assertNoContent();

        $paymentRequest->refresh();

        $this->assertSame('paid', $paymentRequest->status);
        $this->assertTrue($paymentRequest->paid_at->equalTo($paidAt));
        $this->assertSame('pi_existing', $paymentRequest->stripe_payment_intent_id);

        Carbon::setTestNow();
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->checkoutSessionCompletedPayload('cs_test_matching');

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid',
            ],
            $payload
        )->assertStatus(400);
    }

    public function test_unknown_events_are_ignored_safely(): void
    {
        $this->postStripeWebhook(json_encode([
            'id' => 'evt_test_unknown',
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'id' => 'cus_test_123',
                ],
            ],
        ], JSON_THROW_ON_ERROR))->assertNoContent();
    }

    private function checkoutSessionCompletedPayload(
        string $sessionId,
        string $paymentIntentId = 'pi_test_123'
    ): string {
        return json_encode([
            'id' => 'evt_test_checkout_completed',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'payment_intent' => $paymentIntentId,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function postStripeWebhook(string $payload)
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );
    }
}
