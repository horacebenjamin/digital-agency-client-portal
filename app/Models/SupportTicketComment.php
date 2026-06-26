<?php

namespace App\Models;

use App\Notifications\SupportTicketReplyCreated;
use Database\Factories\SupportTicketCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketComment extends Model
{
    /** @use HasFactory<SupportTicketCommentFactory> */
    use HasFactory;

    protected $fillable = [
        'support_ticket_id',
        'created_by',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (SupportTicketComment $comment): void {
            $comment->notifyClientUsersIfSupportReply();
        });
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    private function notifyClientUsersIfSupportReply(): void
    {
        if ($this->is_internal) {
            return;
        }

        $this->loadMissing('supportTicket.project.client.users', 'creator');

        $client = $this->supportTicket->project->client;

        if ($this->creator?->client_id === $client->id) {
            return;
        }

        $client->users
            ->reject(fn (User $user): bool => $user->is($this->creator))
            ->each
            ->notify(new SupportTicketReplyCreated($this));
    }
}
