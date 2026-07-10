<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $client = $request->user()->client;

        if (! $client) {
            return Inertia::render('Dashboard', [
                'summaryCards' => $this->emptySummary($request),
                'recentActivity' => [],
                'focusProject' => null,
                'latestUpdates' => [],
                'latestFiles' => [],
            ]);
        }

        return Inertia::render('Dashboard', [
            'summaryCards' => [
                [
                    'label' => 'Active Projects',
                    'value' => $client->projects()
                        ->where('status', '!=', 'completed')
                        ->count(),
                    'description' => 'Projects currently in motion',
                ],
                [
                    'label' => 'Open Support Tickets',
                    'value' => SupportTicket::query()
                        ->whereHas('project', fn ($query) => $query->where('client_id', $client->id))
                        ->whereNotIn('status', [
                            SupportTicket::STATUS_RESOLVED,
                            SupportTicket::STATUS_CLOSED,
                        ])
                        ->count(),
                    'description' => 'Tickets awaiting resolution',
                ],
                [
                    'label' => 'Unread Notifications',
                    'value' => $request->user()->unreadNotifications()->count(),
                    'description' => 'Items not yet reviewed',
                ],
                [
                    'label' => 'Recent Project Updates',
                    'value' => ProjectUpdate::query()
                        ->where('status', 'published')
                        ->whereHas('project', fn ($query) => $query->where('client_id', $client->id))
                        ->count(),
                    'description' => 'Published updates from your team',
                ],
                [
                    'label' => 'Available Project Files',
                    'value' => ProjectFile::query()
                        ->where('status', ProjectFile::STATUS_AVAILABLE)
                        ->whereHas('project', fn ($query) => $query->where('client_id', $client->id))
                        ->count(),
                    'description' => 'Files ready to download',
                ],
            ],
            'recentActivity' => $this->recentActivity($client->id),
            'focusProject' => $this->focusProject($client->id),
            'latestUpdates' => $this->latestUpdates($client->id),
            'latestFiles' => $this->latestFiles($client->id),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int, description: string}>
     */
    private function emptySummary(Request $request): array
    {
        return [
            [
                'label' => 'Active Projects',
                'value' => 0,
                'description' => 'Projects currently in motion',
            ],
            [
                'label' => 'Open Support Tickets',
                'value' => 0,
                'description' => 'Tickets awaiting resolution',
            ],
            [
                'label' => 'Unread Notifications',
                'value' => $request->user()->unreadNotifications()->count(),
                'description' => 'Items not yet reviewed',
            ],
            [
                'label' => 'Recent Project Updates',
                'value' => 0,
                'description' => 'Published updates from your team',
            ],
            [
                'label' => 'Available Project Files',
                'value' => 0,
                'description' => 'Files ready to download',
            ],
        ];
    }

    /**
     * @return array<int, array{type: string, title: string, context: string|null, date: string|null, href: string}>
     */
    private function recentActivity(int $clientId): array
    {
        return collect()
            ->merge($this->recentProjectUpdates($clientId))
            ->merge($this->recentProjectFiles($clientId))
            ->merge($this->recentTicketReplies($clientId))
            ->sortByDesc('sort_date')
            ->take(8)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'title' => $item['title'],
                'context' => $item['context'],
                'date' => $item['date'],
                'href' => $item['href'],
            ])
            ->values()
            ->all();
    }

    private function recentProjectUpdates(int $clientId): Collection
    {
        return ProjectUpdate::query()
            ->where('status', 'published')
            ->whereHas('project', fn ($query) => $query->where('client_id', $clientId))
            ->with('project:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProjectUpdate $update): array => [
                'type' => 'Project Update',
                'title' => $update->title,
                'context' => $update->project?->title,
                'date' => $update->created_at?->format('M j, Y g:ia'),
                'sort_date' => $update->created_at,
                'href' => route('client.projects.show', $update->project_id),
            ]);
    }

    private function recentProjectFiles(int $clientId): Collection
    {
        return ProjectFile::query()
            ->where('status', ProjectFile::STATUS_AVAILABLE)
            ->whereHas('project', fn ($query) => $query->where('client_id', $clientId))
            ->with('project:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProjectFile $file): array => [
                'type' => 'Project File',
                'title' => $file->name,
                'context' => $file->project?->title,
                'date' => $file->created_at?->format('M j, Y g:ia'),
                'sort_date' => $file->created_at,
                'href' => route('client.projects.show', $file->project_id),
            ]);
    }

    private function recentTicketReplies(int $clientId): Collection
    {
        return SupportTicketComment::query()
            ->where('is_internal', false)
            ->whereHas('supportTicket.project', fn ($query) => $query->where('client_id', $clientId))
            ->with('supportTicket:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (SupportTicketComment $comment): array => [
                'type' => 'Ticket Reply',
                'title' => $comment->supportTicket?->title ?? 'Support ticket reply',
                'context' => str($comment->body)->limit(90)->toString(),
                'date' => $comment->created_at?->format('M j, Y g:ia'),
                'sort_date' => $comment->created_at,
                'href' => route('client.support-tickets.show', $comment->support_ticket_id),
            ]);
    }

    /**
     * @return array{id: int, title: string, status_label: string, progress_percentage: int, show_url: string}|null
     */
    private function focusProject(int $clientId): ?array
    {
        $project = Project::query()
            ->where('client_id', $clientId)
            ->where('status', '!=', 'completed')
            ->latest()
            ->first()
            ?? Project::query()
                ->where('client_id', $clientId)
                ->latest()
                ->first();

        if (! $project) {
            return null;
        }

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status_label' => $project->status_label,
            'progress_percentage' => $project->progress_percentage,
            'show_url' => route('client.projects.show', $project),
        ];
    }

    /**
     * @return array<int, array{id: int, title: string, project_title: string|null, date: string|null, show_url: string}>
     */
    private function latestUpdates(int $clientId): array
    {
        return ProjectUpdate::query()
            ->where('status', 'published')
            ->whereHas('project', fn ($query) => $query->where('client_id', $clientId))
            ->with('project:id,title')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (ProjectUpdate $update): array => [
                'id' => $update->id,
                'title' => $update->title,
                'project_title' => $update->project?->title,
                'date' => $update->created_at?->format('M j, Y'),
                'show_url' => route('client.projects.show', $update->project_id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, project_title: string|null, date: string|null, download_url: string}>
     */
    private function latestFiles(int $clientId): array
    {
        return ProjectFile::query()
            ->where('status', ProjectFile::STATUS_AVAILABLE)
            ->whereHas('project', fn ($query) => $query->where('client_id', $clientId))
            ->with('project:id,title')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (ProjectFile $file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'project_title' => $file->project?->title,
                'date' => $file->created_at?->format('M j, Y'),
                'download_url' => route('client.project-files.download', $file),
            ])
            ->values()
            ->all();
    }
}
