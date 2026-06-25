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
    ];

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
            $projectFile->loadMissing('project.client.users');

            $projectFile->project->client->users
                ->each
                ->notify(new ProjectFileUploaded($projectFile));
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
}
