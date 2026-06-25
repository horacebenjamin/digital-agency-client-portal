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
            if ($projectUpdate->status !== 'published') {
                return;
            }

            $projectUpdate->loadMissing('project.client.users');

            $projectUpdate->project->client->users
                ->each
                ->notify(new ProjectUpdatePublished($projectUpdate));
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
