<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRequestSent extends Notification
{
    use Queueable;

    public function __construct(
        public PaymentRequest $paymentRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('New payment request: '.$this->paymentRequest->title)
            ->greeting('New payment request')
            ->line("A new payment request has been sent: {$this->paymentRequest->title}.")
            ->line('Amount: '.$this->formattedAmount())
            ->action('View billing', route('client.billing.index'));

        if ($this->paymentRequest->project?->title) {
            $message->line('Project: '.$this->paymentRequest->project->title);
        }

        if ($this->paymentRequest->due_date) {
            $message->line('Due date: '.$this->paymentRequest->due_date->format('M j, Y'));
        }

        return $message->line('Sign in to the client portal to view payment details.');
    }

    private function formattedAmount(): string
    {
        return strtoupper($this->paymentRequest->currency).' '.number_format($this->paymentRequest->amount / 100, 2);
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
