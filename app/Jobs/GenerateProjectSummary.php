<?php

namespace App\Jobs;

use App\AI\AIProviderException;
use App\Models\Project;
use App\Services\AIProjectSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateProjectSummary implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $projectId) {}

    public function handle(AIProjectSummaryService $summaryService): void
    {
        $project = Project::find($this->projectId);

        if (! $project) {
            return;
        }

        try {
            $summary = $summaryService->generate($project);

            $project->forceFill([
                'ai_summary' => $summary,
                'ai_summary_status' => 'completed',
                'ai_summary_error' => null,
                'ai_summary_generated_at' => now(),
            ])->save();
        } catch (AIProviderException $exception) {
            $this->markAsFailed($project, $exception->getMessage());

            report($exception);
        } catch (Throwable $exception) {
            $this->markAsFailed($project, 'The AI summary could not be generated right now.');

            report($exception);
        }
    }

    private function markAsFailed(Project $project, string $message): void
    {
        $project->forceFill([
            'ai_summary_status' => 'failed',
            'ai_summary_error' => $message,
        ])->save();
    }
}
