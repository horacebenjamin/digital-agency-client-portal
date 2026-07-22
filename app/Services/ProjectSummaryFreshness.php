<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\SupportTicketComment;
use Illuminate\Support\Carbon;

class ProjectSummaryFreshness
{
    public function hasNewActivity(Project $project): bool
    {
        if (! $project->ai_summary || ! $project->ai_summary_generated_at) {
            return false;
        }

        $latestActivityAt = collect([
            $project->updates()->where('status', 'published')->max('updated_at'),
            $project->files()->where('status', ProjectFile::STATUS_AVAILABLE)->max('updated_at'),
            $project->supportTickets()->max('updated_at'),
            SupportTicketComment::query()
                ->where('is_internal', false)
                ->whereHas('supportTicket', fn ($query) => $query->where('project_id', $project->id))
                ->max('updated_at'),
            $project->paymentRequests()->max('updated_at'),
        ])->filter()
            ->map(fn (string $updatedAt): Carbon => Carbon::parse($updatedAt))
            ->max();

        return $latestActivityAt?->isAfter($project->ai_summary_generated_at) ?? false;
    }
}
