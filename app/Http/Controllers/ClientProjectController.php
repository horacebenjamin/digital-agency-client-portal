<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $request->user()->client;

        $projects = $client
            ? $client->projects()
                ->latest()
                ->paginate(9)
                ->through(fn (Project $project): array => $this->serializeProject($project))
                ->withQueryString()
            : Project::query()->whereRaw('1 = 0')->paginate(9);

        return Inertia::render('Client/Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load([
            'updates' => fn ($query) => $query->latest()->limit(5),
        ])->loadCount(['updates', 'files', 'supportTickets']);

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
                'updates' => $project->updates
                    ->map(fn (ProjectUpdate $update): array => $this->serializeProjectUpdate($update))
                    ->values(),
            ],
        ]);
    }

    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status,
            'status_label' => $project->status_label,
            'status_badge_classes' => $project->status_badge_classes,
            'progress_percentage' => $project->progress_percentage,
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
}
