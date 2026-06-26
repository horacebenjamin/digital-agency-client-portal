<?php

namespace App\Notifications;

use App\Models\SupportTicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupportTicketReplyCreated extends Notification
{
    use Queueable;

    public function __construct(
        public SupportTicketComment $comment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
