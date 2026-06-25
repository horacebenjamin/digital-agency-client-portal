<?php

namespace App\Notifications;

use App\Models\ProjectFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectFileUploaded extends Notification
{
    use Queueable;

    public function __construct(
        public ProjectFile $projectFile,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $project = $this->projectFile->project;

        return [
            'type' => 'project_file',
            'title' => 'New project file',
            'body' => $this->projectFile->name,
            'project_id' => $project->id,
            'project_title' => $project->title,
            'url' => route('client.projects.show', $project),
        ];
    }
}
