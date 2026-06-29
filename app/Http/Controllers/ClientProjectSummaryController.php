<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateProjectSummary;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ClientProjectSummaryController extends Controller
{
    public function store(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        if ($project->ai_summary_status !== 'generating') {
            $project->forceFill([
                'ai_summary_status' => 'generating',
                'ai_summary_error' => null,
                'ai_summary_requested_at' => now(),
            ])->save();

            GenerateProjectSummary::dispatch($project->id);
        }

        return response()->json($this->summaryPayload($project->refresh()), 202);
    }

    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json($this->summaryPayload($project));
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(Project $project): array
    {
        return [
            'status' => $project->ai_summary_status,
            'summary' => $project->ai_summary,
            'message' => $project->ai_summary_error,
            'requested_at' => $project->ai_summary_requested_at?->toIso8601String(),
            'generated_at' => $project->ai_summary_generated_at?->toIso8601String(),
        ];
    }
}
