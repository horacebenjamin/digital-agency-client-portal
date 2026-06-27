<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutVite();
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_project_manager_can_access_admin_panel(): void
    {
        $pm = User::factory()->create();
        $pm->assignRole('project_manager');

        $response = $this->actingAs($pm)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_developer_can_access_admin_panel(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $response = $this->actingAs($developer)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_client_cannot_access_admin_panel(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $response = $this->actingAs($client)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_client_can_access_dashboard(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_project_manager_cannot_access_dashboard_without_client_link(): void
    {
        $pm = User::factory()->create(['client_id' => null]);
        $pm->assignRole('project_manager');

        $response = $this->actingAs($pm)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_developer_cannot_access_dashboard_without_client_link(): void
    {
        $developer = User::factory()->create(['client_id' => null]);
        $developer->assignRole('developer');

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_project_manager_can_access_dashboard_with_client_link(): void
    {
        $client = Client::factory()->create();
        $pm = User::factory()->create(['client_id' => $client->id]);
        $pm->assignRole('project_manager');

        $response = $this->actingAs($pm)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_developer_can_access_dashboard_with_client_link(): void
    {
        $client = Client::factory()->create();
        $developer = User::factory()->create(['client_id' => $client->id]);
        $developer->assignRole('developer');

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_user_without_role_but_with_client_link_can_access_dashboard(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_admin_without_client_id_sees_empty_dashboard(): void
    {
        $admin = User::factory()->create(['client_id' => null]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('summaryCards', 5)
            ->where('summaryCards.0.value', 0)
        );
    }
}
