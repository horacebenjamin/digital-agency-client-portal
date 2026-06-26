<?php

namespace App\Services;

use App\Models\PaymentRequest;
use Laravel\Cashier\Cashier;

class PaymentRequestCheckoutSessionCreator
{
    public function create(PaymentRequest $paymentRequest): object
    {
        return Cashier::stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $paymentRequest->currency,
                        'product_data' => [
                            'name' => $paymentRequest->title,
                        ],
                        'unit_amount' => $paymentRequest->amount,
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'payment_request_id' => (string) $paymentRequest->id,
            ],
            'success_url' => route('client.billing.index', ['checkout' => 'success']),
            'cancel_url' => route('client.billing.index', ['checkout' => 'cancelled']),
        ]);
    }
}
