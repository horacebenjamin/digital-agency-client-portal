<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        abort_if(blank($webhookSecret), 500, 'Stripe webhook secret is not configured.');

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Invalid Stripe webhook signature.', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response()->noContent();
        }

        $session = $event->data->object;

        $paymentRequest = PaymentRequest::query()
            ->where('stripe_checkout_session_id', $session->id)
            ->first();

        if (! $paymentRequest || $paymentRequest->status === 'paid') {
            return response()->noContent();
        }

        $paymentRequest->update([
            'status' => 'paid',
            'paid_at' => now(),
            'stripe_payment_intent_id' => $session->payment_intent,
        ]);

        return response()->noContent();
    }
}
