<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    private const STATUS_PROGRESS = [
        'planning' => 10,
        'in_progress' => 50,
        'on_hold' => 50,
        'completed' => 100,
    ];

    protected $fillable = [
        'client_id',
        'created_by',
        'title',
        'description',
        'status',
        'priority',
        'progress_percentage',
        'due_date',
        'started_at',
        'completed_at',
        'ai_summary',
        'ai_summary_status',
        'ai_summary_error',
        'ai_summary_requested_at',
        'ai_summary_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'ai_summary_requested_at' => 'datetime',
            'ai_summary_generated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return str($this->status)->replace('_', ' ')->title()->toString();
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->attributes['progress_percentage'] ?? null) {
            return (int) $this->attributes['progress_percentage'];
        }

        return self::STATUS_PROGRESS[$this->status] ?? 0;
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            'on_hold' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}
