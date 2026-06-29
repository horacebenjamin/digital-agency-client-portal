<?php

namespace App\Services;

use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProjectActivityTimeline
{
    public function forProject(Project $project, int $limit = 25): array
    {
        $project->loadMissing('creator:id,name');

        return collect()
            ->push($this->timelineItem(
                type: 'project_created',
                label: 'Project Created',
                description: "{$project->title} was created.",
                occurredAt: $project->created_at,
                actor: $project->creator?->name,
            ))
            ->merge($this->projectUpdateTimeline($project))
            ->merge($this->projectFileTimeline($project))
            ->merge($this->supportTicketTimeline($project))
            ->merge($this->supportTicketCommentTimeline($project))
            ->merge($this->paymentRequestTimeline($project))
            ->sortByDesc('sort_date')
            ->take($limit)
            ->values()
            ->map(fn (array $item): array => collect($item)->except('sort_date')->all())
            ->all();
    }

    private function projectUpdateTimeline(Project $project): Collection
    {
        return $project->updates()
            ->where('status', 'published')
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (ProjectUpdate $update): array => $this->timelineItem(
                type: 'project_update_published',
                label: 'Project Update Published',
                description: $update->title,
                occurredAt: $update->created_at,
                actor: $update->creator?->name,
            ));
    }

    private function projectFileTimeline(Project $project): Collection
    {
        return $project->files()
            ->where('status', ProjectFile::STATUS_AVAILABLE)
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (ProjectFile $file): array => $this->timelineItem(
                type: 'project_file_available',
                label: 'Project File Available',
                description: "{$file->name} was made available.",
                occurredAt: $file->created_at,
                actor: $file->creator?->name,
                url: route('client.project-files.download', $file),
                linkLabel: 'Download file',
            ));
    }

    private function supportTicketTimeline(Project $project): Collection
    {
        return $project->supportTickets()
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (SupportTicket $ticket): array => $this->timelineItem(
                type: 'support_ticket_opened',
                label: 'Support Ticket Opened',
                description: $ticket->title,
                occurredAt: $ticket->created_at,
                actor: $ticket->creator?->name,
                url: route('client.support-tickets.show', $ticket),
                linkLabel: 'View ticket',
            ));
    }

    private function supportTicketCommentTimeline(Project $project): Collection
    {
        return SupportTicketComment::query()
            ->where('is_internal', false)
            ->whereHas('supportTicket', fn ($query) => $query->where('project_id', $project->id))
            ->with(['creator:id,name', 'supportTicket:id,title'])
            ->latest()
            ->get()
            ->map(fn (SupportTicketComment $comment): array => $this->timelineItem(
                type: 'support_ticket_reply_added',
                label: 'Support Ticket Reply Added',
                description: "Reply added to {$comment->supportTicket->title}.",
                occurredAt: $comment->created_at,
                actor: $comment->creator?->name,
                url: route('client.support-tickets.show', $comment->supportTicket),
                linkLabel: 'View reply',
            ));
    }

    private function paymentRequestTimeline(Project $project): Collection
    {
        return $project->paymentRequests()
            ->whereIn('status', ['sent', 'paid'])
            ->latest()
            ->get()
            ->flatMap(function (PaymentRequest $paymentRequest): array {
                $items = [
                    $this->timelineItem(
                        type: 'payment_request_sent',
                        label: 'Payment Request Sent',
                        description: "{$paymentRequest->title} was sent.",
                        occurredAt: $paymentRequest->created_at,
                        url: route('client.billing.index'),
                        linkLabel: 'View billing',
                    ),
                ];

                if ($paymentRequest->status === 'paid') {
                    $items[] = $this->timelineItem(
                        type: 'payment_request_paid',
                        label: 'Payment Request Paid',
                        description: "{$paymentRequest->title} was paid.",
                        occurredAt: $paymentRequest->paid_at ?? $paymentRequest->updated_at,
                        url: route('client.billing.index'),
                        linkLabel: 'View receipt',
                    );
                }

                return $items;
            });
    }

    private function timelineItem(
        string $type,
        string $label,
        string $description,
        ?Carbon $occurredAt,
        ?string $actor = null,
        ?string $url = null,
        ?string $linkLabel = null,
    ): array {
        return [
            'type' => $type,
            'label' => $label,
            'description' => $description,
            'occurred_at' => $occurredAt?->format('M j, Y g:ia'),
            'actor' => $actor,
            'url' => $url,
            'link_label' => $linkLabel,
            'sort_date' => $occurredAt,
        ];
    }
}
