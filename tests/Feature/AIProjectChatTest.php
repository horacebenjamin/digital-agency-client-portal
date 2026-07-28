<?php

namespace Tests\Feature;

use App\AI\AIService;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AIProjectChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create();
        $this->user = User::factory()->create(['client_id' => $client->id]);
        $this->project = Project::factory()->create(['client_id' => $client->id]);
    }

    public function test_authorized_client_can_chat(): void
    {
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->with(
                Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Hello assistant')),
                Mockery::type('callable'),
                [
                    'temperature' => 0.1,
                    'num_predict' => 1200,
                    'think' => false,
                ],
            )
            ->andReturnUsing(function ($prompt, $onChunk) {
                $onChunk('Test response chunk');
            });

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello assistant'],
                ],
            ]);

        $response->assertOk();
        $this->assertEquals('Test response chunk', $response->streamedContent());
    }

    public function test_chat_accepts_the_exact_vue_transport_payload_with_history(): void
    {
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->with(
                Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Assistant: Previous answer')
                    && str_contains($prompt, 'Summarise this project')),
                Mockery::type('callable'),
                Mockery::any(),
            )
            ->andReturnUsing(function ($prompt, $onChunk) {
                $onChunk('Current answer');
            });

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'Previous question'],
                    ['role' => 'assistant', 'content' => 'Previous answer'],
                    ['role' => 'user', 'content' => 'Summarise this project'],
                ],
            ]);

        $response->assertOk();
        $this->assertSame('Current answer', $response->streamedContent());
    }

    public function test_next_actions_request_is_not_labelled_client_update(): void
    {
        $prompt = $this->capturePromptFor('What should the team focus on next?');

        $this->assertStringContainsString(
            'Next actions or priorities: Recommended Next Actions',
            $prompt,
        );
        $this->assertStringContainsString(
            'A request about project status, priorities, or next actions is not by itself a request for a client update.',
            $prompt,
        );
    }

    public function test_client_facing_update_may_use_client_update_heading(): void
    {
        $prompt = $this->capturePromptFor('Write an update I can send to the client.');

        $this->assertStringContainsString(
            'Client-facing message: Client Update',
            $prompt,
        );
        $this->assertStringContainsString(
            'When asked for a client update, use 2-3 short prose paragraphs',
            $prompt,
        );
        $this->assertStringContainsString('## Client Update', $prompt);
    }

    public function test_short_factual_answer_may_contain_no_heading(): void
    {
        $prompt = $this->capturePromptFor('When is the project due?');

        $this->assertStringContainsString(
            'Headings are optional. Short factual answers should normally have no heading.',
            $prompt,
        );
        $this->assertStringContainsString(
            'Do not force a heading or template onto simple questions',
            $prompt,
        );
    }

    public function test_any_heading_used_must_match_the_requested_intent(): void
    {
        $prompt = $this->capturePromptFor('Summarise the project risks and blockers.');

        $this->assertStringContainsString(
            "Any heading must match the user's requested intent.",
            $prompt,
        );
        $this->assertStringContainsString('Current project status: Project Status', $prompt);
        $this->assertStringContainsString('Payments: Payment Status', $prompt);
        $this->assertStringContainsString('Risks or blockers: Project Risks', $prompt);
        $this->assertStringContainsString('Support tickets: Support Ticket Summary', $prompt);
        $this->assertStringContainsString(
            'Completed work: Recent Progress or Completed Features',
            $prompt,
        );
    }

    public function test_unauthorized_access_blocked(): void
    {
        $otherClient = Client::factory()->create();
        $otherUser = User::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->actingAs($otherUser)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_chat_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [],
            ]);

        $response->assertJsonValidationErrors(['messages']);
    }

    public function test_chat_uses_stored_summary_when_available(): void
    {
        $this->project->update(['ai_summary' => 'This is a stored summary']);

        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->with(
                Mockery::on(function ($prompt) {
                    return str_contains($prompt, 'This is a stored summary') &&
                           str_contains($prompt, 'Latest AI Project Summary (Internal)');
                }),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturnUsing(function ($prompt, $onChunk) {
                $onChunk('Ok');
            });

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is the summary?'],
                ],
            ]);

        $response->assertOk();
        $response->streamedContent();
    }

    public function test_chat_falls_back_to_project_context_if_summary_missing(): void
    {
        $this->project->update(['ai_summary' => null]);

        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->with(
                Mockery::on(function ($prompt) {
                    return ! str_contains($prompt, 'Latest AI Project Summary (Internal)');
                }),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturnUsing(function ($prompt, $onChunk) {
                $onChunk('Ok');
            });

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is the summary?'],
                ],
            ]);

        $response->assertOk();
        $response->streamedContent();
    }

    private function capturePromptFor(string $question): string
    {
        $capturedPrompt = null;
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->andReturnUsing(function (string $prompt, callable $onChunk) use (&$capturedPrompt): void {
                $capturedPrompt = $prompt;
                $onChunk('Test response');
            });

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => $question],
                ],
            ]);

        $response->assertOk();
        $response->streamedContent();

        $this->assertIsString($capturedPrompt);
        $this->assertStringContainsString("User Question:\n{$question}", $capturedPrompt);

        return $capturedPrompt;
    }
}
