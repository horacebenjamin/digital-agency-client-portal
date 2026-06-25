<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_client_cannot_see_updates_for_another_clients_project(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Owned project update',
        ]);
        ProjectUpdate::factory()->for($otherProject)->create([
            'title' => 'Other client private update',
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

        $this->get(route('client.projects.index'))
            ->assertRedirect(route('login'));

        $this->get(route('client.projects.show', $project))
            ->assertRedirect(route('login'));
    }
}
