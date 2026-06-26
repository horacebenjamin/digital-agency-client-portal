<?php

namespace App\Models;

use App\Notifications\PaymentRequestSent;
use Database\Factories\PaymentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    /** @use HasFactory<PaymentRequestFactory> */
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'client_id',
        'project_id',
        'title',
        'description',
        'amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (PaymentRequest $paymentRequest): void {
            $paymentRequest->notifyClientUsersIfSent();
        });

        static::updated(function (PaymentRequest $paymentRequest): void {
            if (! $paymentRequest->wasChanged('status')) {
                return;
            }

            $paymentRequest->notifyClientUsersIfSent();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    private function notifyClientUsersIfSent(): void
    {
        if ($this->status !== 'sent' || ! $this->client_id) {
            return;
        }

        $this->loadMissing('client.users', 'project');

        $this->client->users
            ->reject(fn (User $user): bool => $this->userAlreadyNotified($user))
            ->each
            ->notify(new PaymentRequestSent($this));
    }

    private function userAlreadyNotified(User $user): bool
    {
        return $user->notifications()
            ->where('type', PaymentRequestSent::class)
            ->get()
            ->contains(fn ($notification): bool => ($notification->data['payment_request_id'] ?? null) === $this->id);
    }
}
