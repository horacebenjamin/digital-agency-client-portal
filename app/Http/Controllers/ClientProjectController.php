<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Services\ProjectActivityTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function show(Project $project, ProjectActivityTimeline $timeline): Response
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
                'ai_summary_url' => route('client.projects.ai-summary', $project),
                'ai_summary_status_url' => route('client.projects.ai-summary.show', $project),
                'ai_summary' => $project->ai_summary,
                'ai_summary_status' => $project->ai_summary_status,
                'ai_summary_error' => $project->ai_summary_error,
                'ai_summary_generated_at' => $project->ai_summary_generated_at?->toIso8601String(),
                'files' => $project->files
                    ->map(fn (ProjectFile $file): array => $this->serializeProjectFile($file))
                    ->values(),
                'updates' => $project->updates
                    ->map(fn (ProjectUpdate $update): array => $this->serializeProjectUpdate($update))
                    ->values(),
                'timeline' => $timeline->forProject($project),
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
}
