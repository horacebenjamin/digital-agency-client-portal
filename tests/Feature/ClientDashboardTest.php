<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_shows_counts_scoped_to_the_client(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');

        $client = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $activeProject = Project::factory()->for($client)->create([
            'title' => 'Client Website Refresh',
            'status' => 'in_progress',
        ]);
        Project::factory()->for($client)->create(['status' => 'completed']);

        SupportTicket::factory()->for($activeProject)->create([
            'title' => 'Open homepage issue',
            'status' => SupportTicket::STATUS_OPEN,
        ]);
        SupportTicket::factory()->for($activeProject)->create([
            'status' => SupportTicket::STATUS_RESOLVED,
        ]);

        ProjectUpdate::factory()->for($activeProject)->create([
            'title' => 'Design review published',
            'status' => 'published',
            'created_at' => now()->subMinutes(30),
        ]);
        ProjectUpdate::factory()->for($activeProject)->create([
            'title' => 'Internal draft update',
            'status' => 'draft',
        ]);

        $availableFile = ProjectFile::factory()->for($activeProject)->create([
            'name' => 'Brand Guidelines.pdf',
            'created_at' => now()->subMinutes(20),
        ]);
        ProjectFile::factory()->for($activeProject)->create([
            'name' => 'Draft Wireframes.pdf',
            'status' => ProjectFile::STATUS_DRAFT,
            'created_at' => now()->subMinutes(15),
        ]);
        ProjectFile::factory()->for($activeProject)->create([
            'name' => 'Archived Assets.zip',
            'status' => ProjectFile::STATUS_ARCHIVED,
            'created_at' => now()->subMinutes(14),
        ]);

        $ticket = SupportTicket::factory()->for($activeProject)->create([
            'title' => 'Navigation feedback',
            'status' => SupportTicket::STATUS_OPEN,
        ]);
        SupportTicketComment::factory()->for($ticket)->create([
            'body' => 'We updated the menu copy.',
            'is_internal' => false,
            'created_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('summaryCards', 5)
                ->where('summaryCards.0.label', 'Active Projects')
                ->where('summaryCards.0.value', 1)
                ->where('summaryCards.1.label', 'Open Support Tickets')
                ->where('summaryCards.1.value', 2)
                ->where('summaryCards.2.label', 'Unread Notifications')
                ->where('summaryCards.2.value', 3)
                ->where('summaryCards.3.label', 'Recent Project Updates')
                ->where('summaryCards.3.value', 1)
                ->where('summaryCards.4.label', 'Available Project Files')
                ->where('summaryCards.4.value', 1)
                ->where('focusProject.title', 'Client Website Refresh')
                ->where('focusProject.progress_percentage', 50)
                ->where('focusProject.show_url', route('client.projects.show', $activeProject))
                ->has('latestUpdates', 1)
                ->where('latestUpdates.0.title', 'Design review published')
                ->where('latestUpdates.0.project_title', 'Client Website Refresh')
                ->where('latestUpdates.0.show_url', route('client.projects.show', $activeProject))
                ->has('latestFiles', 1)
                ->where('latestFiles.0.name', 'Brand Guidelines.pdf')
                ->where('latestFiles.0.project_title', 'Client Website Refresh')
                ->where('latestFiles.0.download_url', route('client.project-files.download', $availableFile))
                ->has('recentActivity', 3)
                ->where('recentActivity.0.type', 'Ticket Reply')
                ->where('recentActivity.0.title', 'Navigation feedback')
                ->where('recentActivity.1.type', 'Project File')
                ->where('recentActivity.1.title', 'Brand Guidelines.pdf')
                ->where('recentActivity.2.type', 'Project Update')
                ->where('recentActivity.2.title', 'Design review published')
            )
            ->assertDontSee('Internal draft update')
            ->assertDontSee('Draft Wireframes.pdf')
            ->assertDontSee('Archived Assets.zip');

        Carbon::setTestNow();
    }

    public function test_dashboard_does_not_include_another_clients_data(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create([
            'title' => 'Owned project',
            'status' => 'in_progress',
        ]);
        $otherProject = Project::factory()->for($otherClient)->create([
            'title' => 'Private other project',
            'status' => 'in_progress',
        ]);

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Owned published update',
            'status' => 'published',
        ]);
        ProjectUpdate::factory()->for($otherProject)->create([
            'title' => 'Private other update',
            'status' => 'published',
        ]);
        ProjectFile::factory()->for($otherProject)->create([
            'name' => 'Private other file.pdf',
        ]);
        $otherTicket = SupportTicket::factory()->for($otherProject)->create([
            'title' => 'Private other ticket',
        ]);
        SupportTicketComment::factory()->for($otherTicket)->create([
            'body' => 'Private other reply',
            'is_internal' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summaryCards.0.value', 1)
                ->where('summaryCards.1.value', 0)
                ->where('summaryCards.3.value', 1)
                ->where('summaryCards.4.value', 0)
                ->where('focusProject.title', 'Owned project')
                ->has('latestUpdates', 1)
                ->where('latestUpdates.0.title', 'Owned published update')
                ->has('latestFiles', 0)
                ->has('recentActivity', 1)
                ->where('recentActivity.0.title', 'Owned published update')
            )
            ->assertDontSee('Private other project')
            ->assertDontSee('Private other update')
            ->assertDontSee('Private other file.pdf')
            ->assertDontSee('Private other ticket')
            ->assertDontSee('Private other reply');
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
