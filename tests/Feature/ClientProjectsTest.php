<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_client_can_only_see_their_projects_on_the_index(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create(['title' => 'Client Website Refresh']);
        $otherProject = Project::factory()->for($otherClient)->create(['title' => 'Private Internal Build']);

        $response = $this->actingAs($user)->get(route('client.projects.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.title', $project->title)
            )
            ->assertDontSee($otherProject->title);
    }

    public function test_client_can_view_project_details_for_their_client_record(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create([
            'title' => 'Client Portal Launch',
            'status' => 'in_progress',
            'ai_summary_generated_at' => Carbon::parse('2026-07-22 14:30:00'),
        ]);

        $response = $this->actingAs($user)->get(route('client.projects.show', $project));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Projects/Show')
                ->where('project.title', 'Client Portal Launch')
                ->where('project.status_label', 'In Progress')
                ->where('project.progress_percentage', 50)
                ->where('project.ai_summary_generated_at', '2026-07-22T14:30:00+00:00')
                ->where('project.ai_summary_has_new_activity', false)
            );
    }

    public function test_client_is_told_when_project_activity_is_newer_than_the_saved_summary(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create([
            'ai_summary' => 'Previously generated summary.',
            'ai_summary_status' => 'completed',
            'ai_summary_generated_at' => Carbon::parse('2026-07-22 10:00:00'),
        ]);

        ProjectUpdate::factory()->for($project)->create([
            'status' => 'published',
            'created_at' => Carbon::parse('2026-07-22 11:00:00'),
            'updated_at' => Carbon::parse('2026-07-22 11:00:00'),
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.ai_summary_has_new_activity', true)
            );
    }

    public function test_client_can_see_updates_for_their_own_project(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Homepage designs approved',
            'body' => 'The homepage wireframes and visual design direction are ready for the next implementation milestone.',
            'status' => 'published',
        ]);

        $response = $this->actingAs($user)->get(route('client.projects.show', $project));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Projects/Show')
                ->has('project.updates', 1)
                ->where('project.updates.0.title', 'Homepage designs approved')
                ->where('project.updates.0.summary', 'The homepage wireframes and visual design direction are ready for the next implementation milestone.')
                ->where('project.updates.0.status_label', 'Published')
                ->where('project.updates.0.created_date', 'Jun 25, 2026')
            );

        Carbon::setTestNow();
    }

    public function test_client_only_sees_published_updates_on_project_details(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Published milestone',
            'status' => 'published',
        ]);
        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Internal draft milestone',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('project.updates', 1)
                ->where('project.updates.0.title', 'Published milestone')
            )
            ->assertDontSee('Internal draft milestone');
    }

    public function test_client_cannot_see_updates_for_another_clients_project(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Owned project update',
            'status' => 'published',
        ]);
        ProjectUpdate::factory()->for($otherProject)->create([
            'title' => 'Other client private update',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('project.updates', 1)
                ->where('project.updates.0.title', 'Owned project update')
            )
            ->assertDontSee('Other client private update');

        $this->actingAs($user)
            ->get(route('client.projects.show', $otherProject))
            ->assertForbidden();
    }

    public function test_client_can_see_files_for_their_own_project(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();

        ProjectFile::factory()->for($project)->create([
            'name' => 'Brand Guidelines.pdf',
            'path' => 'project-files/brand-guidelines.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($user)->get(route('client.projects.show', $project));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Projects/Show')
                ->has('project.files', 1)
                ->where('project.files.0.name', 'Brand Guidelines.pdf')
                ->where('project.files.0.type', 'application/pdf')
                ->where('project.files.0.uploaded_date', 'Jun 25, 2026')
                ->where('project.files.0.download_url', route('client.project-files.download', ProjectFile::first()))
                ->missing('project.files.0.path')
            );

        Carbon::setTestNow();
    }

    public function test_client_sees_their_project_activity_timeline(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $agencyUser = User::factory()->create(['name' => 'Agency Manager']);
        $project = Project::factory()->for($client)->create([
            'created_by' => $agencyUser->id,
            'title' => 'Timeline Website Build',
            'created_at' => '2026-06-25 09:00:00',
        ]);

        ProjectUpdate::factory()->for($project)->create([
            'created_by' => $agencyUser->id,
            'title' => 'Homepage designs approved',
            'status' => 'published',
            'created_at' => '2026-06-25 10:00:00',
        ]);

        ProjectFile::factory()->for($project)->create([
            'created_by' => $agencyUser->id,
            'name' => 'Launch Checklist.pdf',
            'created_at' => '2026-06-25 11:00:00',
        ]);

        $ticket = SupportTicket::factory()->for($project)->create([
            'created_by' => $user->id,
            'title' => 'Update the contact form',
            'created_at' => '2026-06-25 12:00:00',
        ]);

        SupportTicketComment::factory()->for($ticket)->create([
            'created_by' => $agencyUser->id,
            'is_internal' => false,
            'created_at' => '2026-06-25 13:00:00',
        ]);

        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Final launch invoice',
            'status' => 'sent',
            'created_at' => '2026-06-25 14:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Projects/Show')
                ->has('project.timeline', 6)
                ->where('project.timeline.0.type', 'payment_request_sent')
                ->where('project.timeline.0.label', 'Payment Request Sent')
                ->where('project.timeline.0.description', 'Final launch invoice was sent.')
                ->where('project.timeline.1.type', 'support_ticket_reply_added')
                ->where('project.timeline.1.actor', 'Agency Manager')
                ->where('project.timeline.2.type', 'support_ticket_opened')
                ->where('project.timeline.2.actor', $user->name)
                ->where('project.timeline.3.type', 'project_file_available')
                ->where('project.timeline.3.url', route('client.project-files.download', ProjectFile::first()))
                ->where('project.timeline.4.type', 'project_update_published')
                ->where('project.timeline.4.description', 'Homepage designs approved')
                ->where('project.timeline.5.type', 'project_created')
                ->where('project.timeline.5.actor', 'Agency Manager')
            );
    }

    public function test_client_cannot_see_another_clients_project_activity_in_the_timeline(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create([
            'title' => 'Owned Website',
            'created_at' => '2026-06-25 09:00:00',
        ]);
        $otherProject = Project::factory()->for($otherClient)->create(['title' => 'Private Campaign']);

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Owned timeline item',
            'status' => 'published',
            'created_at' => '2026-06-25 10:00:00',
        ]);
        ProjectUpdate::factory()->for($otherProject)->create([
            'title' => 'Other client timeline item',
            'status' => 'published',
        ]);
        PaymentRequest::factory()->for($otherClient)->for($otherProject)->create([
            'title' => 'Other client invoice',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('project.timeline')
                ->where('project.timeline.0.description', 'Owned timeline item')
            )
            ->assertDontSee('Other client timeline item')
            ->assertDontSee('Other client invoice');

        $this->actingAs($user)
            ->get(route('client.projects.show', $otherProject))
            ->assertForbidden();
    }

    public function test_internal_support_comments_are_hidden_from_the_project_timeline(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create([
            'created_at' => '2026-06-25 09:00:00',
        ]);
        $ticket = SupportTicket::factory()->for($project)->create([
            'created_at' => '2026-06-25 10:00:00',
        ]);
        $publicActor = User::factory()->create(['name' => 'Support Agent']);
        $internalActor = User::factory()->create(['name' => 'Internal Notes Writer']);

        SupportTicketComment::factory()->for($ticket)->create([
            'created_by' => $publicActor->id,
            'is_internal' => false,
            'created_at' => '2026-06-25 11:00:00',
        ]);
        SupportTicketComment::factory()->for($ticket)->create([
            'created_by' => $internalActor->id,
            'is_internal' => true,
            'created_at' => '2026-06-25 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.timeline.0.type', 'support_ticket_reply_added')
                ->where('project.timeline.0.actor', 'Support Agent')
            )
            ->assertDontSee('Internal Notes Writer');
    }

    public function test_draft_and_archived_files_are_hidden_from_the_project_timeline(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create([
            'created_at' => '2026-06-25 09:00:00',
        ]);

        ProjectFile::factory()->for($project)->create([
            'name' => 'Published Brief.pdf',
            'status' => ProjectFile::STATUS_AVAILABLE,
            'created_at' => '2026-06-25 10:00:00',
        ]);
        ProjectFile::factory()->for($project)->create([
            'name' => 'Draft Concepts.pdf',
            'status' => ProjectFile::STATUS_DRAFT,
            'created_at' => '2026-06-25 11:00:00',
        ]);
        ProjectFile::factory()->for($project)->create([
            'name' => 'Archived Contract.pdf',
            'status' => ProjectFile::STATUS_ARCHIVED,
            'created_at' => '2026-06-25 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.timeline.0.type', 'project_file_available')
                ->where('project.timeline.0.description', 'Published Brief.pdf was made available.')
            )
            ->assertDontSee('Draft Concepts.pdf')
            ->assertDontSee('Archived Contract.pdf');
    }

    public function test_paid_payment_request_appears_as_paid_activity(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create([
            'created_at' => '2026-06-25 09:00:00',
        ]);

        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Launch balance',
            'status' => 'paid',
            'created_at' => '2026-06-25 10:00:00',
            'paid_at' => '2026-06-25 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.timeline.0.type', 'payment_request_paid')
                ->where('project.timeline.0.label', 'Payment Request Paid')
                ->where('project.timeline.0.description', 'Launch balance was paid.')
                ->where('project.timeline.0.occurred_at', 'Jun 25, 2026 12:00pm')
                ->where('project.timeline.1.type', 'payment_request_sent')
            );
    }

    public function test_project_file_metadata_is_generated_from_storage(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create();

        Storage::disk('local')->put('project-files/brief.txt', 'project brief');

        $file = ProjectFile::factory()->for($project)->create([
            'name' => 'Brief.txt',
            'path' => 'project-files/brief.txt',
            'disk' => 'local',
            'mime_type' => null,
            'size' => null,
        ]);

        $file->refresh();

        $this->assertSame('text/plain', $file->mime_type);
        $this->assertSame(13, $file->size);
    }

    public function test_client_cannot_see_files_from_another_clients_project(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        ProjectFile::factory()->for($project)->create([
            'name' => 'Owned project file.pdf',
        ]);
        ProjectFile::factory()->for($otherProject)->create([
            'name' => 'Other client private file.pdf',
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('project.files', 1)
                ->where('project.files.0.name', 'Owned project file.pdf')
            )
            ->assertDontSee('Other client private file.pdf');

        $this->actingAs($user)
            ->get(route('client.projects.show', $otherProject))
            ->assertForbidden();
    }

    public function test_client_can_download_their_own_project_file(): void
    {
        Storage::fake('local');

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();

        Storage::disk('local')->put('project-files/proposal.pdf', 'proposal contents');

        $file = ProjectFile::factory()->for($project)->create([
            'name' => 'Proposal.pdf',
            'path' => 'project-files/proposal.pdf',
            'disk' => 'local',
        ]);

        $this->actingAs($user)
            ->get(route('client.project-files.download', $file))
            ->assertOk()
            ->assertDownload('Proposal.pdf');
    }

    public function test_client_cannot_download_another_clients_project_file(): void
    {
        Storage::fake('local');

        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $otherProject = Project::factory()->for($otherClient)->create();

        Storage::disk('local')->put('project-files/private.pdf', 'private contents');

        $file = ProjectFile::factory()->for($otherProject)->create([
            'name' => 'Private.pdf',
            'path' => 'project-files/private.pdf',
            'disk' => 'local',
        ]);

        $this->actingAs($user)
            ->get(route('client.project-files.download', $file))
            ->assertForbidden();
    }

    public function test_client_cannot_view_another_clients_project(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $otherProject = Project::factory()->for($otherClient)->create();

        $this->actingAs($user)
            ->get(route('client.projects.show', $otherProject))
            ->assertForbidden();
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $project = Project::factory()->create();
        $file = ProjectFile::factory()->for($project)->create();

        $this->get(route('client.projects.index'))
            ->assertRedirect(route('login'));

        $this->get(route('client.projects.show', $project))
            ->assertRedirect(route('login'));

        $this->get(route('client.project-files.download', $file))
            ->assertRedirect(route('login'));
    }
}
