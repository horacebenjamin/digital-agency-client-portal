<?php

namespace App\Services;

use App\AI\AIService;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use Illuminate\Support\Str;

class AIProjectSummaryService
{
    public function __construct(
        private readonly AIService $ai,
        private readonly ProjectActivityTimeline $timeline,
    ) {
    }

    public function generate(Project $project): string
    {
        return $this->ai->generateText($this->buildPrompt($project), [
            'temperature' => 0.2,
            'num_predict' => 350,
            'think' => false,
        ]);
    }

    public function buildPrompt(Project $project): string
    {
        $context = $this->projectContext($project);

        return <<<PROMPT
You are writing a concise project summary for a digital agency client portal.

Use only the project data provided. Do not invent facts, dates, blockers, payments, or commitments.
Write in a clear, professional tone suitable for both a project manager and the client.
Keep the summary around 150-250 words.

Include these sections:
- Overall project status
- Important recent progress
- Outstanding issues
- Payment status
- Suggested next actions

Project data:
{$context}
PROMPT;
    }

    private function projectContext(Project $project): string
    {
        return collect([
            'Project Information' => $this->projectInformation($project),
            'Recent Project Updates' => $this->recentProjectUpdates($project),
            'Recent Project Files' => $this->recentProjectFiles($project),
            'Open Support Tickets' => $this->openSupportTickets($project),
            'Recent Public Ticket Replies' => $this->recentPublicTicketReplies($project),
            'Outstanding Payment Requests' => $this->outstandingPaymentRequests($project),
            'Recent Activity Timeline' => $this->recentActivityTimeline($project),
        ])->map(fn (array $lines, string $heading): string => $this->section($heading, $lines))
            ->implode("\n\n");
    }

    /**
     * @return array<int, string>
     */
    private function projectInformation(Project $project): array
    {
        return [
            "Title: {$project->title}",
            'Status: '.$project->status_label,
            "Progress: {$project->progress_percentage}%",
            'Priority: '.str($project->priority)->replace('_', ' ')->title(),
            'Due date: '.($project->due_date?->format('M j, Y') ?? 'Not set'),
            'Started: '.($project->started_at?->format('M j, Y') ?? 'Not started'),
            'Description: '.($project->description ? Str::limit($project->description, 700) : 'No description provided.'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function recentProjectUpdates(Project $project): array
    {
        return $project->updates()
            ->where('status', 'published')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProjectUpdate $update): string => sprintf(
                '%s: %s - %s',
                $update->created_at?->format('M j, Y') ?? 'Date unknown',
                $update->title,
                Str::limit($update->body, 240),
            ))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function recentProjectFiles(Project $project): array
    {
        return $project->files()
            ->where('status', ProjectFile::STATUS_AVAILABLE)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProjectFile $file): string => sprintf(
                '%s: %s%s',
                $file->created_at?->format('M j, Y') ?? 'Date unknown',
                $file->name,
                $file->description ? ' - '.Str::limit($file->description, 180) : '',
            ))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function openSupportTickets(Project $project): array
    {
        return $project->supportTickets()
            ->whereIn('status', [
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_WAITING_ON_CLIENT,
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (SupportTicket $ticket): string => sprintf(
                '%s: %s (%s, %s priority) - %s',
                $ticket->created_at?->format('M j, Y') ?? 'Date unknown',
                $ticket->title,
                SupportTicket::statusLabel($ticket->status),
                str($ticket->priority)->title(),
                Str::limit($ticket->description, 220),
            ))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function recentPublicTicketReplies(Project $project): array
    {
        return SupportTicketComment::query()
            ->where('is_internal', false)
            ->whereHas('supportTicket', fn ($query) => $query->where('project_id', $project->id))
            ->with('supportTicket:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (SupportTicketComment $comment): string => sprintf(
                '%s on %s: %s',
                $comment->created_at?->format('M j, Y') ?? 'Date unknown',
                $comment->supportTicket?->title ?? 'ticket',
                Str::limit($comment->body, 220),
            ))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function outstandingPaymentRequests(Project $project): array
    {
        return $project->paymentRequests()
            ->whereIn('status', ['draft', 'sent'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PaymentRequest $paymentRequest): string => sprintf(
                '%s: %s for %s, due %s',
                str($paymentRequest->status)->title(),
                $paymentRequest->title,
                '£'.number_format($paymentRequest->amount / 100, 2),
                $paymentRequest->due_date?->format('M j, Y') ?? 'not set',
            ))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function recentActivityTimeline(Project $project): array
    {
        return collect($this->timeline->forProject($project, 10))
            ->map(fn (array $item): string => sprintf(
                '%s: %s - %s%s',
                $item['occurred_at'] ?? 'Date unknown',
                $item['label'],
                $item['description'],
                filled($item['actor'] ?? null) ? " (by {$item['actor']})" : '',
            ))
            ->all();
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function section(string $heading, array $lines): string
    {
        if ($lines === []) {
            $lines = ['None.'];
        }

        return $heading.":\n- ".implode("\n- ", $lines);
    }
}
