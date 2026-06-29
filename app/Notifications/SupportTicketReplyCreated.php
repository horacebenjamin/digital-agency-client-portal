<?php

namespace App\Notifications;

use App\Models\SupportTicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class SupportTicketReplyCreated extends Notification
{
    use Queueable;

    public function __construct(
        public SupportTicketComment $comment,
    ) {}

    public function via(object $notifiable): array
    {
        if ($this->comment->is_internal) {
            return [];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->comment->supportTicket;
        $project = $ticket->project;
        $message = (new MailMessage)
            ->subject('New support ticket reply: '.$ticket->title)
            ->greeting('New support ticket reply')
            ->line("There is a new reply on {$ticket->title} for {$project->title}.")
            ->action('View support ticket', route('client.support-tickets.show', $ticket));

        if (filled($this->comment->body)) {
            $message->line(Str::limit(strip_tags($this->comment->body), 220));
        }

        return $message->line('Sign in to the client portal to reply or review the full conversation.');
    }

    public function toArray(object $notifiable): array
    {
        $ticket = $this->comment->supportTicket;
        $project = $ticket->project;

        return [
            'type' => 'support_ticket_reply',
            'title' => 'New support ticket reply',
            'body' => $ticket->title,
            'support_ticket_id' => $ticket->id,
            'support_ticket_comment_id' => $this->comment->id,
            'project_id' => $project->id,
            'project_title' => $project->title,
            'url' => route('client.support-tickets.show', $ticket),
        ];
    }
}
