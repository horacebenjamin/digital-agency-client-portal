<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $request->user()->client;

        $projects = $client
            ? $client->projects()
                ->withAggregate(['updates' => fn ($query) => $query->where('status', 'published')], 'created_at', 'max')
                ->latest()
                ->paginate(10)
                ->through(fn (Project $project): array => $this->serializeProject($project))
                ->withQueryString()
            : Project::query()->whereRaw('1 = 0')->paginate(10);

        return Inertia::render('Client/Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load([
            'creator:id,name',
            'files' => fn ($query) => $query->where('status', ProjectFile::STATUS_AVAILABLE)->latest(),
            'updates' => fn ($query) => $query
                ->where('status', 'published')
                ->latest()
                ->limit(5),
        ])->loadCount([
            'updates',
            'files' => fn ($query) => $query->where('status', ProjectFile::STATUS_AVAILABLE),
            'supportTickets',
        ]);

        return Inertia::render('Client/Projects/Show', [
            'project' => [
                ...$this->serializeProject($project),
                'description' => $project->description,
                'priority' => str($project->priority)->title()->toString(),
                'due_date' => $project->due_date?->format('M j, Y'),
                'started_at' => $project->started_at?->format('M j, Y'),
                'updates_count' => $project->updates_count,
                'files_count' => $project->files_count,
                'support_tickets_count' => $project->support_tickets_count,
                'files' => $project->files
                    ->map(fn (ProjectFile $file): array => $this->serializeProjectFile($file))
                    ->values(),
                'updates' => $project->updates
                    ->map(fn (ProjectUpdate $update): array => $this->serializeProjectUpdate($update))
                    ->values(),
                'timeline' => $this->projectTimeline($project),
            ],
        ]);
    }

    public function downloadFile(ProjectFile $projectFile): StreamedResponse
    {
        Gate::authorize('download', $projectFile);

        return Storage::disk($projectFile->disk)->download($projectFile->path, $projectFile->name);
    }

    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'description' => $project->description,
            'status' => $project->status,
            'status_label' => $project->status_label,
            'status_badge_classes' => $project->status_badge_classes,
            'progress_percentage' => $project->progress_percentage,
            'due_date' => $project->due_date?->format('M j, Y'),
            'is_overdue' => $project->due_date?->isPast() && $project->status !== 'completed',
            'last_update' => $project->updates_max_created_at
                ? Carbon::parse($project->updates_max_created_at)->format('M j, Y')
                : null,
            'show_url' => route('client.projects.show', $project),
        ];
    }

    private function serializeProjectUpdate(ProjectUpdate $update): array
    {
        return [
            'id' => $update->id,
            'title' => $update->title,
            'body' => $update->body,
            'summary' => Str::limit($update->body, 220),
            'status' => $update->status,
            'status_label' => $update->status ? str($update->status)->replace('_', ' ')->title()->toString() : null,
            'created_date' => $update->created_at?->format('M j, Y'),
        ];
    }

    private function serializeProjectFile(ProjectFile $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'type' => $file->mime_type,
            'uploaded_date' => $file->created_at?->format('M j, Y'),
            'download_url' => route('client.project-files.download', $file),
        ];
    }

    private function projectTimeline(Project $project): array
    {
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
            ->take(25)
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
            ->flatMap(function ($paymentRequest): array {
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
