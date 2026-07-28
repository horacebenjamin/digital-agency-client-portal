<?php

namespace Tests\Feature;

use App\AI\AIChatStreamProtocol;
use App\AI\AIProjectChatLock;
use App\AI\AIProviderException;
use App\AI\AIService;
use App\AI\AIStreamCancelledException;
use App\AI\AIStreamResult;
use App\Http\Requests\ProjectChatRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
            ->andReturnUsing(function ($prompt, $onChunk): AIStreamResult {
                $onChunk('Test response chunk');

                return AIStreamResult::completed();
            });

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello assistant'],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(
            'Test response chunk'.AIChatStreamProtocol::completed(AIStreamResult::completed()),
            $response->streamedContent(),
        );
        $this->assertChatLockIsAvailable();
    }

    public function test_chat_accepts_the_exact_vue_transport_payload_with_history(): void
    {
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->with(
                Mockery::on(fn (string $prompt): bool => str_contains($prompt, '"role": "assistant"')
                    && str_contains($prompt, '"content": "Previous answer"')
                    && str_contains($prompt, 'Summarise this project')),
                Mockery::type('callable'),
                Mockery::any(),
            )
            ->andReturnUsing(function ($prompt, $onChunk): AIStreamResult {
                $onChunk('Current answer');

                return AIStreamResult::completed();
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
        $this->assertStringStartsWith('Current answer', $response->streamedContent());
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

    public function test_empty_user_message_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => ''],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.0.content']);
    }

    public function test_whitespace_only_user_message_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => " \n\t "],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.0.content']);
    }

    public function test_overlong_user_message_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => str_repeat('a', ProjectChatRequest::MAX_USER_MESSAGE_LENGTH + 1),
                    ],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'messages.0.content' => 'Messages must be 2,000 characters or fewer.',
            ]);
    }

    public function test_concurrent_duplicate_request_is_rejected(): void
    {
        $lock = Cache::lock(
            "ai-project-chat:{$this->user->getAuthIdentifier()}:{$this->project->getKey()}",
            10,
        );
        $this->assertTrue($lock->get());

        try {
            $response = $this->actingAs($this->user)
                ->postJson(route('client.projects.chat', $this->project), [
                    'messages' => [
                        ['role' => 'user', 'content' => 'What is next?'],
                    ],
                ]);

            $response->assertConflict();
            $this->assertSame(
                'The assistant is already responding. Please wait and try again.',
                $response->getContent(),
            );
        } finally {
            $lock->release();
        }
    }

    public function test_provider_failure_returns_only_a_safe_stream_event(): void
    {
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->andThrow(new AIProviderException('Ollama model qwen3 failed at http://internal-host'));

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is next?'],
                ],
            ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertSame(AIChatStreamProtocol::failed(), $content);
        $this->assertStringNotContainsString('qwen3', $content);
        $this->assertStringNotContainsString('internal-host', $content);
        $this->assertChatLockIsAvailable();
    }

    public function test_timeout_releases_the_chat_lock(): void
    {
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->andThrow(new AIProviderException('The provider timed out.'));

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is next?'],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(AIChatStreamProtocol::failed(), $response->streamedContent());
        $this->assertChatLockIsAvailable();
    }

    public function test_interrupted_stream_releases_the_chat_lock(): void
    {
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->andThrow(new AIStreamCancelledException('Client disconnected.'));

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is next?'],
                ],
            ]);

        $response->assertOk();
        $this->assertSame('', $response->streamedContent());
        $this->assertChatLockIsAvailable();
    }

    public function test_client_cancellation_releases_the_chat_lock(): void
    {
        $requestId = Str::uuid()->toString();

        $activeResponse = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'request_id' => $requestId,
                'messages' => [
                    ['role' => 'user', 'content' => 'Write a long response.'],
                ],
            ]);

        $activeResponse->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('client.projects.chat.cancel', $this->project), [
                'request_id' => $requestId,
            ])
            ->assertNoContent();

        $this->assertChatLockIsAvailable();
    }

    public function test_new_chat_cancellation_allows_an_immediate_new_request(): void
    {
        $this->assertCancellationAllowsImmediateRequest('Start a new chat.');
    }

    public function test_panel_close_cancellation_allows_a_later_request(): void
    {
        $this->assertCancellationAllowsImmediateRequest('Close the chat panel.');
    }

    public function test_stale_request_cannot_release_another_requests_lock(): void
    {
        $firstRequestId = Str::uuid()->toString();
        $secondRequestId = Str::uuid()->toString();
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->andReturnUsing(function (string $prompt, callable $onChunk): AIStreamResult {
                $onChunk('Old response');

                return AIStreamResult::completed();
            });

        $this->app->instance(AIService::class, $mockAi);

        $firstResponse = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'request_id' => $firstRequestId,
                'messages' => [
                    ['role' => 'user', 'content' => 'First request'],
                ],
            ]);

        $this->actingAs($this->user)
            ->postJson(route('client.projects.chat.cancel', $this->project), [
                'request_id' => $firstRequestId,
            ])
            ->assertNoContent();

        $secondResponse = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'request_id' => $secondRequestId,
                'messages' => [
                    ['role' => 'user', 'content' => 'Second request'],
                ],
            ]);
        $secondResponse->assertOk();

        $this->assertStringStartsWith('Old response', $firstResponse->streamedContent());

        $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'request_id' => Str::uuid()->toString(),
                'messages' => [
                    ['role' => 'user', 'content' => 'Third request'],
                ],
            ])
            ->assertConflict();

        $this->actingAs($this->user)
            ->postJson(route('client.projects.chat.cancel', $this->project), [
                'request_id' => $secondRequestId,
            ])
            ->assertNoContent();
    }

    public function test_truncated_response_keeps_partial_content_and_reports_length_limit(): void
    {
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->andReturnUsing(function (string $prompt, callable $onChunk): AIStreamResult {
                $onChunk('Partial response that remains visible.');

                return AIStreamResult::lengthLimited();
            });

        $this->app->instance(AIService::class, $mockAi);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'messages' => [
                    ['role' => 'user', 'content' => 'Give me every detail.'],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(
            'Partial response that remains visible.'
                .AIChatStreamProtocol::completed(AIStreamResult::lengthLimited()),
            $response->streamedContent(),
        );
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
            ->andReturnUsing(function ($prompt, $onChunk): AIStreamResult {
                $onChunk('Ok');

                return AIStreamResult::completed();
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
            ->andReturnUsing(function ($prompt, $onChunk): AIStreamResult {
                $onChunk('Ok');

                return AIStreamResult::completed();
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
            ->andReturnUsing(function (string $prompt, callable $onChunk) use (&$capturedPrompt): AIStreamResult {
                $capturedPrompt = $prompt;
                $onChunk('Test response');

                return AIStreamResult::completed();
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
        $this->assertStringContainsString("User Question:\n\"{$question}\"", $capturedPrompt);

        return $capturedPrompt;
    }

    private function assertCancellationAllowsImmediateRequest(string $cancelledQuestion): void
    {
        $cancelledRequestId = Str::uuid()->toString();
        $mockAi = Mockery::mock(AIService::class);
        $mockAi->shouldReceive('streamText')
            ->once()
            ->andReturnUsing(function (string $prompt, callable $onChunk): AIStreamResult {
                $onChunk('New response');

                return AIStreamResult::completed();
            });

        $this->app->instance(AIService::class, $mockAi);

        $activeResponse = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'request_id' => $cancelledRequestId,
                'messages' => [
                    ['role' => 'user', 'content' => $cancelledQuestion],
                ],
            ]);
        $activeResponse->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('client.projects.chat.cancel', $this->project), [
                'request_id' => $cancelledRequestId,
            ])
            ->assertNoContent();

        $nextResponse = $this->actingAs($this->user)
            ->postJson(route('client.projects.chat', $this->project), [
                'request_id' => Str::uuid()->toString(),
                'messages' => [
                    ['role' => 'user', 'content' => 'Can I send now?'],
                ],
            ]);

        $nextResponse->assertOk();
        $this->assertStringStartsWith('New response', $nextResponse->streamedContent());
    }

    private function assertChatLockIsAvailable(): void
    {
        $lock = Cache::lock(
            AIProjectChatLock::lockName(
                $this->user->getAuthIdentifier(),
                $this->project->getKey(),
            ),
            10,
        );

        $this->assertTrue($lock->get());
        $lock->release();
    }
}
