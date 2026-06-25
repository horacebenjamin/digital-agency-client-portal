<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
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
        ]);

        $response = $this->actingAs($user)->get(route('client.projects.show', $project));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Projects/Show')
                ->where('project.title', 'Client Portal Launch')
                ->where('project.status_label', 'In Progress')
                ->where('project.progress_percentage', 50)
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
