<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Notifications\ProjectFileUploaded;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectFileStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_project_file_defaults_to_available(): void
    {
        $file = ProjectFile::create([
            'project_id' => Project::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
            'name' => 'test.pdf',
            'path' => 'project-files/test.pdf',
            'disk' => 'public',
        ]);

        $this->assertEquals(ProjectFile::STATUS_AVAILABLE, $file->refresh()->status);
    }

    public function test_admin_can_create_project_file_with_various_statuses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $project = Project::factory()->create();

        foreach ([ProjectFile::STATUS_DRAFT, ProjectFile::STATUS_AVAILABLE, ProjectFile::STATUS_ARCHIVED] as $status) {
            $file = ProjectFile::create([
                'project_id' => $project->id,
                'created_by' => $admin->id,
                'name' => "test_{$status}.pdf",
                'path' => "project-files/test_{$status}.pdf",
                'status' => $status,
            ]);

            $this->assertEquals($status, $file->status);
        }
    }

    public function test_client_only_sees_available_files(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        ProjectFile::factory()->create(['project_id' => $project->id, 'status' => ProjectFile::STATUS_AVAILABLE, 'name' => 'available.pdf']);
        ProjectFile::factory()->create(['project_id' => $project->id, 'status' => ProjectFile::STATUS_DRAFT, 'name' => 'draft.pdf']);
        ProjectFile::factory()->create(['project_id' => $project->id, 'status' => ProjectFile::STATUS_ARCHIVED, 'name' => 'archived.pdf']);

        $response = $this->actingAs($user)->get(route('client.projects.show', $project));

        $response->assertStatus(200);
        $projectData = $response->viewData('page')['props']['project'];

        $this->assertCount(1, $projectData['files']);
        $this->assertEquals('available.pdf', $projectData['files'][0]['name']);
    }

    public function test_client_cannot_download_draft_or_archived_files(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        $availableFile = ProjectFile::factory()->create(['project_id' => $project->id, 'status' => ProjectFile::STATUS_AVAILABLE]);
        $draftFile = ProjectFile::factory()->create(['project_id' => $project->id, 'status' => ProjectFile::STATUS_DRAFT]);
        $archivedFile = ProjectFile::factory()->create(['project_id' => $project->id, 'status' => ProjectFile::STATUS_ARCHIVED]);

        // Create dummy files in storage
        Storage::disk('public')->put($availableFile->path, 'content');
        Storage::disk('public')->put($draftFile->path, 'content');
        Storage::disk('public')->put($archivedFile->path, 'content');

        $this->actingAs($user)->get(route('client.project-files.download', $availableFile))->assertStatus(200);
        $this->actingAs($user)->get(route('client.project-files.download', $draftFile))->assertStatus(403);
        $this->actingAs($user)->get(route('client.project-files.download', $archivedFile))->assertStatus(403);
    }

    public function test_available_file_triggers_client_notification(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        ProjectFile::factory()->create([
            'project_id' => $project->id,
            'status' => ProjectFile::STATUS_AVAILABLE,
        ]);

        Notification::assertSentTo($user, ProjectFileUploaded::class);
    }

    public function test_draft_file_does_not_trigger_client_notification(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        ProjectFile::factory()->create([
            'project_id' => $project->id,
            'status' => ProjectFile::STATUS_DRAFT,
        ]);

        Notification::assertNothingSent();
    }

    public function test_draft_changed_to_available_triggers_one_notification(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        $file = ProjectFile::factory()->create([
            'project_id' => $project->id,
            'status' => ProjectFile::STATUS_DRAFT,
        ]);

        Notification::assertNothingSent();

        $file->update(['status' => ProjectFile::STATUS_AVAILABLE]);

        Notification::assertSentTo($user, ProjectFileUploaded::class);
    }

    public function test_editing_available_file_again_does_not_create_duplicate_notification(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        $file = ProjectFile::factory()->create([
            'project_id' => $project->id,
            'status' => ProjectFile::STATUS_AVAILABLE,
        ]);

        // We check if a notification exists in DB because Notification::fake() doesn't persist them
        $this->assertEquals(1, $user->notifications()->where('type', ProjectFileUploaded::class)->count());

        $file->update(['description' => 'updated description']);

        $this->assertEquals(1, $user->notifications()->where('type', ProjectFileUploaded::class)->count());
    }
}
