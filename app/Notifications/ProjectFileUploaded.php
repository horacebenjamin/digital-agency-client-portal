<?php

namespace App\Notifications;

use App\Models\ProjectFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectFileUploaded extends Notification
{
    use Queueable;

    public function __construct(
        public ProjectFile $projectFile,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->projectFile->project;
        $message = (new MailMessage)
            ->subject('New project file: '.$this->projectFile->name)
            ->greeting('New project file')
            ->line("A new file has been added to {$project->title}: {$this->projectFile->name}.")
            ->action('View project files', route('client.projects.show', $project));

        if (filled($this->projectFile->description)) {
            $message->line($this->projectFile->description);
        }

        return $message->line('Sign in to the client portal to view or download the file.');
    }

    public function toArray(object $notifiable): array
    {
        $project = $this->projectFile->project;

        return [
            'type' => 'project_file',
            'title' => 'New project file',
            'body' => $this->projectFile->name,
            'project_file_id' => $this->projectFile->id,
            'project_id' => $project->id,
            'project_title' => $project->title,
            'url' => route('client.projects.show', $project),
        ];
    }
}
