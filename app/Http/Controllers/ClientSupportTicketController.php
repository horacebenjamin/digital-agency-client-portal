<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientSupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $request->user()->client;

        $tickets = $client
            ? SupportTicket::query()
                ->whereHas('project', fn ($query) => $query->where('client_id', $client->id))
                ->with('project:id,title')
                ->withMax([
                    'comments as latest_public_comment_at' => fn ($query) => $query->where('is_internal', false),
                ], 'created_at')
                ->latest()
                ->paginate(10)
                ->through(fn (SupportTicket $ticket): array => $this->serializeTicket($ticket))
                ->withQueryString()
            : SupportTicket::query()->whereRaw('1 = 0')->paginate(10);

        return Inertia::render('Client/SupportTickets/Index', [
            'tickets' => $tickets,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Client/SupportTickets/Create', [
            'projects' => $this->projectOptions($request),
            'priorities' => $this->priorities(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')->where('client_id', $request->user()->client_id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
        ]);

        $ticket = SupportTicket::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'open',
        ]);

        return redirect()->route('client.support-tickets.show', $ticket);
    }

    public function show(SupportTicket $supportTicket): Response
    {
        Gate::authorize('view', $supportTicket);

        $supportTicket->load([
            'project:id,title',
            'comments' => fn ($query) => $query
                ->where('is_internal', false)
                ->with('creator:id,name')
                ->oldest(),
        ])->loadMax([
            'comments as latest_public_comment_at' => fn ($query) => $query->where('is_internal', false),
        ], 'created_at');

        return Inertia::render('Client/SupportTickets/Show', [
            'ticket' => [
                ...$this->serializeTicket($supportTicket),
                'description' => $supportTicket->description,
                'comments' => $supportTicket->comments
                    ->map(fn (SupportTicketComment $comment): array => [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'created_by' => $comment->creator?->name ?? 'Support team',
                        'created_date' => $comment->created_at?->format('M j, Y g:ia'),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function storeComment(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('reply', $supportTicket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $supportTicket->comments()->create([
            'created_by' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => false,
        ]);

        return back();
    }

    private function serializeTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->title,
            'status' => $ticket->status,
            'status_label' => $this->label($ticket->status),
            'priority' => $ticket->priority,
            'priority_label' => $this->label($ticket->priority),
            'project_title' => $ticket->project?->title,
            'created_date' => $ticket->created_at?->format('M j, Y'),
            'latest_activity_date' => $ticket->latest_public_comment_at
                ? Carbon::parse($ticket->latest_public_comment_at)->format('M j, Y g:ia')
                : null,
            'show_url' => route('client.support-tickets.show', $ticket),
        ];
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function projectOptions(Request $request): array
    {
        return Project::query()
            ->where('client_id', $request->user()->client_id)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function priorities(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    private function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
