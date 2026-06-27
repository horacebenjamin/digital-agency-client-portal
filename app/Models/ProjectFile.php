<?php

namespace App\Models;

use App\Notifications\ProjectFileUploaded;
use Database\Factories\ProjectFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectFile extends Model
{
    /** @use HasFactory<ProjectFileFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'path',
        'disk',
        'mime_type',
        'size',
        'description',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_AVAILABLE,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_AVAILABLE => 'Available',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    protected static function booted(): void
    {
        static::saving(function (ProjectFile $projectFile): void {
            $projectFile->disk = $projectFile->disk ?: 'public';

            if (blank($projectFile->name) && filled($projectFile->path)) {
                $projectFile->name = basename($projectFile->path);
            }

            if (blank($projectFile->path)) {
                return;
            }

            $storage = Storage::disk($projectFile->disk);

            if (! $storage->exists($projectFile->path)) {
                return;
            }

            $projectFile->mime_type = $storage->mimeType($projectFile->path);
            $projectFile->size = $storage->size($projectFile->path);
        });

        static::created(function (ProjectFile $projectFile): void {
            $projectFile->notifyClientUsersIfAvailable();
        });

        static::updated(function (ProjectFile $projectFile): void {
            if (! $projectFile->wasChanged('status')) {
                return;
            }

            $projectFile->notifyClientUsersIfAvailable();
        });
    }

    private function notifyClientUsersIfAvailable(): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->loadMissing('project.client.users');

        $this->project->client->users
            ->reject(fn (User $user): bool => $this->userAlreadyNotified($user))
            ->each
            ->notify(new ProjectFileUploaded($this));
    }

    private function userAlreadyNotified(User $user): bool
    {
        return $user->notifications()
            ->where('type', ProjectFileUploaded::class)
            ->get()
            ->contains(fn ($notification): bool => ($notification->data['project_file_id'] ?? null) === $this->id);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
