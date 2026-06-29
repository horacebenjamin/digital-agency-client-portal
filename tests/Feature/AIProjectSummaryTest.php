<?php

namespace Tests\Feature;

use App\AI\AIProvider;
use App\AI\AIProviderException;
use App\AI\AIService;
use App\AI\ConfiguredAIService;
use App\Jobs\GenerateProjectSummary;
use App\Models\Client;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use App\Services\AIProjectSummaryService;
use App\Services\ProjectActivityTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AIProjectSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_prompt_generation_includes_client_safe_project_context(): void
    {
        [$project, $publicReply, $internalReply] = $this->projectWithSummaryContext();

        $service = new AIProjectSummaryService($this->fakeAIService('Unused'), app(ProjectActivityTimeline::class));

        $prompt = $service->buildPrompt($project);

        $this->assertStringContainsString('Keep the summary around 150-250 words.', $prompt);
        $this->assertStringContainsString('Overall project status', $prompt);
        $this->assertStringContainsString('Important recent progress', $prompt);
        $this->assertStringContainsString('Outstanding issues', $prompt);
        $this->assertStringContainsString('Payment status', $prompt);
        $this->assertStringContainsString('Suggested next actions', $prompt);
        $this->assertStringContainsString('Client Portal Launch', $prompt);
        $this->assertStringContainsString('Homepage build complete', $prompt);
        $this->assertStringContainsString('Launch Checklist.pdf', $prompt);
        $this->assertStringContainsString('Contact form issue', $prompt);
        $this->assertStringContainsString($publicReply->body, $prompt);
        $this->assertStringContainsString('Launch balance', $prompt);
        $this->assertStringContainsString('Recent Activity Timeline', $prompt);
        $this->assertStringNotContainsString('Draft Sitemap.pdf', $prompt);
        $this->assertStringNotContainsString($internalReply->body, $prompt);
    }

    public function test_configured_ai_service_delegates_to_provider_abstraction(): void
    {
        $provider = Mockery::mock(AIProvider::class);
        $provider
            ->shouldReceive('complete')
            ->once()
            ->with('Summarize this project.', ['temperature' => 0.1])
            ->andReturn('Provider summary.');

        $service = new ConfiguredAIService($provider);

        $this->assertSame('Provider summary.', $service->generateText('Summarize this project.', [
            'temperature' => 0.1,
        ]));
    }

    public function test_summary_service_generates_summary_with_ai_service(): void
    {
        [$project] = $this->projectWithSummaryContext();
        $capture = new class
        {
            public ?string $prompt = null;
        };
        $ai = new class($capture) implements AIService
        {
            public function __construct(private readonly object $capture) {}

            public function generateText(string $prompt, array $options = []): string
            {
                $this->capture->prompt = $prompt;

                return 'The project is progressing well with launch items outstanding.';
            }
        };

        $service = new AIProjectSummaryService($ai, app(ProjectActivityTimeline::class));

        $summary = $service->generate($project);

        $this->assertSame('The project is progressing well with launch items outstanding.', $summary);
        $this->assertStringContainsString('Client Portal Launch', $capture->prompt);
        $this->assertStringContainsString('Suggested next actions', $capture->prompt);
    }

    public function test_client_can_generate_ai_project_summary(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create();

        Queue::fake();

        $this->actingAs($user)
            ->postJson(route('client.projects.ai-summary', $project))
            ->assertAccepted()
            ->assertJson([
                'status' => 'generating',
            ]);

        Queue::assertPushed(GenerateProjectSummary::class, fn (GenerateProjectSummary $job): bool => $job->projectId === $project->id);

        $project->refresh();

        $this->assertSame('generating', $project->ai_summary_status);
        $this->assertNull($project->ai_summary_error);
        $this->assertNotNull($project->ai_summary_requested_at);
    }

    public function test_client_cannot_generate_summary_for_another_clients_project(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $otherProject = Project::factory()->for($otherClient)->create();

        $this->app->bind(AIService::class, fn () => $this->fakeAIService('Should not be returned.'));

        $this->actingAs($user)
            ->postJson(route('client.projects.ai-summary', $otherProject))
            ->assertForbidden();
    }

    public function test_client_can_check_ai_project_summary_status(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->for($client)->create([
            'ai_summary' => 'AI generated project summary.',
            'ai_summary_status' => 'completed',
            'ai_summary_generated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('client.projects.ai-summary.show', $project))
            ->assertOk()
            ->assertJson([
                'status' => 'completed',
                'summary' => 'AI generated project summary.',
            ]);
    }

    public function test_summary_job_stores_generated_summary(): void
    {
        $project = Project::factory()->create([
            'ai_summary_status' => 'generating',
        ]);

        $this->app->bind(AIService::class, fn () => $this->fakeAIService('AI generated project summary.'));

        (new GenerateProjectSummary($project->id))->handle(app(AIProjectSummaryService::class));

        $project->refresh();

        $this->assertSame('completed', $project->ai_summary_status);
        $this->assertSame('AI generated project summary.', $project->ai_summary);
        $this->assertNull($project->ai_summary_error);
        $this->assertNotNull($project->ai_summary_generated_at);
    }

    public function test_summary_job_stores_failure_when_ai_provider_fails(): void
    {
        $project = Project::factory()->create([
            'ai_summary_status' => 'generating',
        ]);

        $this->app->bind(AIService::class, fn () => new class implements AIService
        {
            public function generateText(string $prompt, array $options = []): string
            {
                throw new AIProviderException('Ollama is unavailable. Check that the Ollama service is running.');
            }
        });

        (new GenerateProjectSummary($project->id))->handle(app(AIProjectSummaryService::class));

        $project->refresh();

        $this->assertSame('failed', $project->ai_summary_status);
        $this->assertSame('Ollama is unavailable. Check that the Ollama service is running.', $project->ai_summary_error);
    }

    private function fakeAIService(string $summary): AIService
    {
        return new class($summary) implements AIService
        {
            public function __construct(private readonly string $summary) {}

            public function generateText(string $prompt, array $options = []): string
            {
                return $this->summary;
            }
        };
    }

    /**
     * @return array{0: Project, 1: SupportTicketComment, 2: SupportTicketComment}
     */
    private function projectWithSummaryContext(): array
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create([
            'title' => 'Client Portal Launch',
            'description' => 'Build and launch the client portal for agency customers.',
            'status' => 'in_progress',
            'priority' => 'high',
            'created_at' => '2026-06-25 09:00:00',
        ]);

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Homepage build complete',
            'body' => 'The homepage implementation is complete and ready for client review.',
            'status' => 'published',
            'created_at' => '2026-06-25 10:00:00',
        ]);

        ProjectFile::factory()->for($project)->create([
            'name' => 'Launch Checklist.pdf',
            'description' => 'Checklist for launch readiness.',
            'status' => ProjectFile::STATUS_AVAILABLE,
            'created_at' => '2026-06-25 11:00:00',
        ]);
        ProjectFile::factory()->for($project)->create([
            'name' => 'Draft Sitemap.pdf',
            'status' => ProjectFile::STATUS_DRAFT,
            'created_at' => '2026-06-25 12:00:00',
        ]);

        $ticket = SupportTicket::factory()->for($project)->create([
            'title' => 'Contact form issue',
            'description' => 'The contact form confirmation message needs review.',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'high',
            'created_at' => '2026-06-25 13:00:00',
        ]);

        $publicReply = SupportTicketComment::factory()->for($ticket)->create([
            'body' => 'We have replicated the contact form issue and are preparing a fix.',
            'is_internal' => false,
            'created_at' => '2026-06-25 14:00:00',
        ]);
        $internalReply = SupportTicketComment::factory()->for($ticket)->create([
            'body' => 'Internal-only escalation notes for the support team.',
            'is_internal' => true,
            'created_at' => '2026-06-25 15:00:00',
        ]);

        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Launch balance',
            'status' => 'sent',
            'amount' => 125000,
            'created_at' => '2026-06-25 16:00:00',
        ]);

        return [$project, $publicReply, $internalReply];
    }
}
