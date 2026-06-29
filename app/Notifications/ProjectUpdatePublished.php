<?php

namespace App\Notifications;

use App\Models\ProjectUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ProjectUpdatePublished extends Notification
{
    use Queueable;

    public function __construct(
        public ProjectUpdate $projectUpdate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->projectUpdate->project;
        $message = (new MailMessage)
            ->subject('New project update: '.$this->projectUpdate->title)
            ->greeting('New project update')
            ->line("{$project->title} has a new update: {$this->projectUpdate->title}.")
            ->action('View project', route('client.projects.show', $project));

        if (filled($this->projectUpdate->body)) {
            $message->line(Str::limit(strip_tags($this->projectUpdate->body), 220));
        }

        return $message->line('Sign in to the client portal to review the full update.');
    }

    public function toArray(object $notifiable): array
    {
        $project = $this->projectUpdate->project;

        return [
            'type' => 'project_update',
            'title' => 'New project update',
            'body' => $this->projectUpdate->title,
            'project_update_id' => $this->projectUpdate->id,
            'project_id' => $project->id,
            'project_title' => $project->title,
            'url' => route('client.projects.show', $project),
        ];
    }
}
