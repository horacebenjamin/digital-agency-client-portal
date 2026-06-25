<?php

namespace Tests\Feature;

use App\Filament\Widgets\AdminOverviewStats;
use App\Filament\Widgets\AdminRecentActivity;
use App\Filament\Widgets\ProjectsByStatusChart;
use App\Filament\Widgets\SupportTicketsByStatusChart;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_stats_show_expected_counts(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');

        $clients = Client::factory()->count(2)->create();
        $activeProject = Project::factory()->for($clients->first())->create([
            'status' => 'in_progress',
            'due_date' => now()->subDay(),
        ]);
        Project::factory()->for($clients->first())->create([
            'status' => 'completed',
            'due_date' => now()->subDay(),
        ]);

        SupportTicket::factory()->for($activeProject)->create([
            'status' => SupportTicket::STATUS_OPEN,
        ]);
        SupportTicket::factory()->for($activeProject)->create([
            'status' => SupportTicket::STATUS_CLOSED,
        ]);

        ProjectUpdate::factory()->for($activeProject)->create([
            'status' => 'published',
        ]);
        ProjectUpdate::factory()->for($activeProject)->create([
            'status' => 'draft',
        ]);

        ProjectFile::factory()->for($activeProject)->create();

        $stats = $this->stats();

        $this->assertSame('Total Clients', (string) $stats[0]->getLabel());
        $this->assertSame('2', $this->statValueText($stats[0]->getValue()));
        $this->assertSame('Active Projects', (string) $stats[1]->getLabel());
        $this->assertSame('1', $this->statValueText($stats[1]->getValue()));
        $this->assertSame('Open Support Tickets', (string) $stats[2]->getLabel());
        $this->assertSame('1', $this->statValueText($stats[2]->getValue()));
        $this->assertSame('Overdue Projects', (string) $stats[3]->getLabel());
        $this->assertSame('1', $this->statValueText($stats[3]->getValue()));
        $this->assertSame('Published Project Updates', (string) $stats[4]->getLabel());
        $this->assertSame('1', $this->statValueText($stats[4]->getValue()));
        $this->assertSame('Uploaded Project Files', (string) $stats[5]->getLabel());
        $this->assertSame('1', $this->statValueText($stats[5]->getValue()));

        Carbon::setTestNow();
    }

    public function test_admin_recent_activity_includes_tickets_updates_and_files(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');

        $client = Client::factory()->create(['company_name' => 'Acme Studio']);
        $project = Project::factory()->for($client)->create();

        SupportTicket::factory()->for($project)->create([
            'title' => 'Homepage issue',
            'created_at' => now()->subMinutes(30),
        ]);
        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Launch update',
            'created_at' => now()->subMinutes(20),
        ]);
        ProjectFile::factory()->for($project)->create([
            'name' => 'Brand Guide.pdf',
            'created_at' => now()->subMinutes(10),
        ]);

        $activity = app(AdminRecentActivity::class)->getRecentActivity();

        $this->assertCount(3, $activity);
        $this->assertSame('Project File', $activity[0]['type']);
        $this->assertSame('Brand Guide.pdf', $activity[0]['title']);
        $this->assertSame('Acme Studio', $activity[0]['context']);
        $this->assertSame('Project Update', $activity[1]['type']);
        $this->assertSame('Launch update', $activity[1]['title']);
        $this->assertSame('Support Ticket', $activity[2]['type']);
        $this->assertSame('Homepage issue', $activity[2]['title']);

        Carbon::setTestNow();
    }

    public function test_admin_status_charts_use_project_and_ticket_status_counts(): void
    {
        $client = Client::factory()->create();
        $planningProject = Project::factory()->for($client)->create(['status' => 'planning']);
        Project::factory()->for($client)->create(['status' => 'in_progress']);
        Project::factory()->for($client)->create(['status' => 'completed']);

        SupportTicket::factory()->for($planningProject)->create(['status' => SupportTicket::STATUS_OPEN]);
        SupportTicket::factory()->for($planningProject)->create(['status' => SupportTicket::STATUS_OPEN]);
        SupportTicket::factory()->for($planningProject)->create(['status' => SupportTicket::STATUS_CLOSED]);

        $projectsData = $this->chartData(ProjectsByStatusChart::class);
        $ticketsData = $this->chartData(SupportTicketsByStatusChart::class);

        $this->assertSame(['Planning', 'In Progress', 'On Hold', 'Completed'], $projectsData['labels']);
        $this->assertSame([1, 1, 0, 1], $projectsData['datasets'][0]['data']);
        $this->assertSame(array_values(SupportTicket::statuses()), $ticketsData['labels']);
        $this->assertSame([2, 0, 0, 0, 1], $ticketsData['datasets'][0]['data']);
    }

    private function stats(): array
    {
        $method = new ReflectionMethod(AdminOverviewStats::class, 'getStats');
        $method->setAccessible(true);

        return $method->invoke(app(AdminOverviewStats::class));
    }

    private function chartData(string $widgetClass): array
    {
        $method = new ReflectionMethod($widgetClass, 'getData');
        $method->setAccessible(true);

        return $method->invoke(app($widgetClass));
    }

    private function statValueText(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
