<?php

namespace App\Services;

use App\Models\PaymentRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PaymentRequestPdfGenerator
{
    public function download(PaymentRequest $paymentRequest): Response
    {
        $paymentRequest->loadMissing(['client', 'project']);

        return Pdf::loadView('pdf.payment-request', $this->data($paymentRequest))
            ->setPaper('a4')
            ->download($this->filename($paymentRequest));
    }

    /**
     * @return array<string, mixed>
     */
    public function data(PaymentRequest $paymentRequest): array
    {
        $paymentRequest->loadMissing(['client', 'project']);

        return [
            'portalName' => $this->portalName(),
            'agencyEmail' => 'support@digitalagency.test',
            'documentTitle' => $paymentRequest->status === 'paid' ? 'Payment Receipt' : 'Payment Request',
            'documentReference' => ($paymentRequest->status === 'paid' ? 'Receipt' : 'Payment Request').' #'.$paymentRequest->id,
            'paymentRequest' => $paymentRequest,
            'clientName' => $paymentRequest->client?->company_name ?? 'Unknown client',
            'projectName' => $paymentRequest->project?->title,
            'amount' => $this->formatAmount($paymentRequest),
            'currency' => strtoupper($paymentRequest->currency),
            'status' => PaymentRequest::STATUSES[$paymentRequest->status] ?? str($paymentRequest->status)->replace('_', ' ')->title()->toString(),
            'dueDate' => $paymentRequest->due_date?->format('M j, Y'),
            'paidDate' => $paymentRequest->paid_at?->format('M j, Y g:ia'),
            'stripePaymentId' => $paymentRequest->stripe_payment_intent_id,
            'generatedDate' => now()->format('M j, Y g:ia'),
        ];
    }

    private function filename(PaymentRequest $paymentRequest): string
    {
        $prefix = $paymentRequest->status === 'paid' ? 'receipt' : 'payment-request';
        $title = Str::slug($paymentRequest->title) ?: 'payment';

        return "{$prefix}-{$paymentRequest->id}-{$title}.pdf";
    }

    private function formatAmount(PaymentRequest $paymentRequest): string
    {
        if (strtolower($paymentRequest->currency) === 'gbp') {
            return '£'.number_format($paymentRequest->amount / 100, 2);
        }

        return strtoupper($paymentRequest->currency).' '.number_format($paymentRequest->amount / 100, 2);
    }

    private function portalName(): string
    {
        $appName = config('app.name');

        return $appName === 'Laravel' ? 'Digital Agency Client Portal' : $appName;
    }
}
