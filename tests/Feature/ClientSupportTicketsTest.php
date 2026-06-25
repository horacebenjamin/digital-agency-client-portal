<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientSupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_clients_can_see_their_own_tickets(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create(['title' => 'Website Refresh']);
        $ticket = SupportTicket::factory()->for($project)->create([
            'title' => 'Homepage form is broken',
            'status' => 'open',
            'priority' => 'high',
            'created_at' => now(),
        ]);

        SupportTicketComment::factory()->for($ticket)->create([
            'body' => 'We are looking into this.',
            'is_internal' => false,
            'created_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->get(route('client.support-tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/SupportTickets/Index')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.subject', 'Homepage form is broken')
                ->where('tickets.data.0.status_label', 'Open')
                ->where('tickets.data.0.priority_label', 'High')
                ->where('tickets.data.0.created_date', 'Jun 25, 2026')
                ->where('tickets.data.0.latest_activity_date', 'Jun 25, 2026 11:00am')
                ->where('tickets.data.0.project_title', 'Website Refresh')
            );

        $this->actingAs($user)
            ->get(route('client.support-tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/SupportTickets/Show')
                ->where('ticket.subject', 'Homepage form is broken')
                ->where('ticket.description', $ticket->description)
                ->has('ticket.comments', 1)
                ->where('ticket.comments.0.body', 'We are looking into this.')
            );

        Carbon::setTestNow();
    }

    public function test_clients_cannot_see_another_clients_tickets(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        SupportTicket::factory()->for($project)->create(['title' => 'Owned ticket']);
        $otherTicket = SupportTicket::factory()->for($otherProject)->create(['title' => 'Other private ticket']);

        $this->actingAs($user)
            ->get(route('client.support-tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tickets.data', 1)
                ->where('tickets.data.0.subject', 'Owned ticket')
            )
            ->assertDontSee('Other private ticket');

        $this->actingAs($user)
            ->get(route('client.support-tickets.show', $otherTicket))
            ->assertForbidden();
    }

    public function test_clients_can_create_tickets_for_their_own_client_account(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        $this->actingAs($user)
            ->get(route('client.support-tickets.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/SupportTickets/Create')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
            );

        $this->actingAs($user)
            ->post(route('client.support-tickets.store'), [
                'project_id' => $project->id,
                'title' => 'Please update the launch copy',
                'description' => 'The hero copy needs a wording change.',
                'priority' => 'medium',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'project_id' => $project->id,
            'created_by' => $user->id,
            'title' => 'Please update the launch copy',
            'description' => 'The hero copy needs a wording change.',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->from(route('client.support-tickets.create'))
            ->post(route('client.support-tickets.store'), [
                'project_id' => $otherProject->id,
                'title' => 'Invalid ticket',
                'description' => 'This should not be created.',
                'priority' => 'medium',
            ])
            ->assertSessionHasErrors('project_id');

        $this->assertDatabaseMissing('support_tickets', [
            'project_id' => $otherProject->id,
            'title' => 'Invalid ticket',
        ]);
    }

    public function test_clients_can_reply_to_their_own_tickets(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();
        $ticket = SupportTicket::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('client.support-tickets.comments.store', $ticket), [
                'body' => 'Here is the extra detail you requested.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'created_by' => $user->id,
            'body' => 'Here is the extra detail you requested.',
            'is_internal' => false,
        ]);
    }

    public function test_clients_cannot_reply_to_another_clients_ticket(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $otherProject = Project::factory()->for($otherClient)->create();
        $otherTicket = SupportTicket::factory()->for($otherProject)->create();

        $this->actingAs($user)
            ->post(route('client.support-tickets.comments.store', $otherTicket), [
                'body' => 'This should not be allowed.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('support_ticket_comments', [
            'support_ticket_id' => $otherTicket->id,
            'body' => 'This should not be allowed.',
        ]);
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $ticket = SupportTicket::factory()->create();

        $this->get(route('client.support-tickets.index'))
            ->assertRedirect(route('login'));

        $this->get(route('client.support-tickets.create'))
            ->assertRedirect(route('login'));

        $this->post(route('client.support-tickets.store'), [])
            ->assertRedirect(route('login'));

        $this->get(route('client.support-tickets.show', $ticket))
            ->assertRedirect(route('login'));

        $this->post(route('client.support-tickets.comments.store', $ticket), [])
            ->assertRedirect(route('login'));
    }
}
