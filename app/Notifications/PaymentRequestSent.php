<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRequestSent extends Notification
{
    use Queueable;

    public function __construct(
        public PaymentRequest $paymentRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_request',
            'title' => 'New payment request',
            'body' => $this->paymentRequest->title,
            'payment_request_id' => $this->paymentRequest->id,
            'project_id' => $this->paymentRequest->project_id,
            'project_title' => $this->paymentRequest->project?->title,
            'url' => route('client.billing.index'),
        ];
    }
}
