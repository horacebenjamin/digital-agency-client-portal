<?php

namespace App\Models;

use App\Notifications\ProjectUpdatePublished;
use Database\Factories\ProjectUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectUpdate extends Model
{
    /** @use HasFactory<ProjectUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'body',
        'status',
    ];

    protected static function booted(): void
    {
        static::created(function (ProjectUpdate $projectUpdate): void {
            $projectUpdate->notifyClientUsersIfPublished();
        });

        static::updated(function (ProjectUpdate $projectUpdate): void {
            if (! $projectUpdate->wasChanged('status')) {
                return;
            }

            $projectUpdate->notifyClientUsersIfPublished();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    private function notifyClientUsersIfPublished(): void
    {
        if ($this->status !== 'published') {
            return;
        }

        $this->loadMissing('project.client.users');

        $this->project->client->users
            ->reject(fn (User $user): bool => $this->userAlreadyNotified($user))
            ->each
            ->notify(new ProjectUpdatePublished($this));
    }

    private function userAlreadyNotified(User $user): bool
    {
        return $user->notifications()
            ->where('type', ProjectUpdatePublished::class)
            ->get()
            ->contains(fn ($notification): bool => ($notification->data['project_update_id'] ?? null) === $this->id);
    }
}
