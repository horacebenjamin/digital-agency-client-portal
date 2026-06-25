<?php

namespace App\Notifications;

use App\Models\ProjectUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectUpdatePublished extends Notification
{
    use Queueable;

    public function __construct(
        public ProjectUpdate $projectUpdate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $project = $this->projectUpdate->project;

        return [
            'type' => 'project_update',
            'title' => 'New project update',
            'body' => $this->projectUpdate->title,
            'project_id' => $project->id,
            'project_title' => $project->title,
            'url' => route('client.projects.show', $project),
        ];
    }
}
