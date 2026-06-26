<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Services\PaymentRequestCheckoutSessionCreator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientBillingController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $request->user()->client;

        if (! $client) {
            return Inertia::render('Client/Billing/Index', [
                'outstandingPayments' => [],
                'paidPayments' => [],
            ]);
        }

        $paymentRequests = $client->paymentRequests()
            ->with('project:id,title')
            ->whereIn('status', ['draft', 'sent', 'paid'])
            ->latest()
            ->get();

        return Inertia::render('Client/Billing/Index', [
            'outstandingPayments' => $paymentRequests
                ->whereIn('status', ['draft', 'sent'])
                ->sortBy(fn (PaymentRequest $paymentRequest): int => $paymentRequest->status === 'sent' ? 0 : 1)
                ->map(fn (PaymentRequest $paymentRequest): array => $this->serializePaymentRequest($paymentRequest))
                ->values(),
            'paidPayments' => $paymentRequests
                ->where('status', 'paid')
                ->map(fn (PaymentRequest $paymentRequest): array => $this->serializePaymentRequest($paymentRequest))
                ->values(),
        ]);
    }

    public function checkout(
        Request $request,
        PaymentRequest $paymentRequest,
        PaymentRequestCheckoutSessionCreator $checkoutSessionCreator
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless(
            $request->user()->client_id
            && $paymentRequest->client_id === $request->user()->client_id
            && $paymentRequest->status === 'sent',
            403
        );

        $checkoutSession = $checkoutSessionCreator->create($paymentRequest);

        $paymentRequest->update([
            'stripe_checkout_session_id' => $checkoutSession->id,
        ]);

        return Inertia::location($checkoutSession->url);
    }

    /**
     * @return array{id: int, title: string, project_name: string|null, amount: string, status: string, status_label: string, status_badge_classes: string, due_date: string|null, paid_date: string|null, can_pay: bool, checkout_url: string}
     */
    private function serializePaymentRequest(PaymentRequest $paymentRequest): array
    {
        return [
            'id' => $paymentRequest->id,
            'title' => $paymentRequest->title,
            'project_name' => $paymentRequest->project?->title,
            'amount' => '£'.number_format($paymentRequest->amount / 100, 2),
            'status' => $paymentRequest->status,
            'status_label' => PaymentRequest::STATUSES[$paymentRequest->status] ?? str($paymentRequest->status)->replace('_', ' ')->title()->toString(),
            'status_badge_classes' => $this->statusBadgeClasses($paymentRequest->status),
            'due_date' => $paymentRequest->due_date?->format('M j, Y'),
            'paid_date' => $paymentRequest->paid_at?->format('M j, Y'),
            'can_pay' => $paymentRequest->status === 'sent',
            'checkout_url' => route('client.billing.payment-requests.checkout', $paymentRequest),
        ];
    }

    private function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'sent' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}
