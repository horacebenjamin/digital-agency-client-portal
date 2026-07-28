<?php

namespace Tests\Unit;

use App\AI\AIProviderException;
use App\AI\AIStreamCancelledException;
use App\AI\Providers\OllamaProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaProviderTest extends TestCase
{
    public function test_stream_forwards_bounded_non_thinking_generation_options(): void
    {
        $originalExecutionTimeLimit = ini_get('max_execution_time');

        config()->set('ai.providers.ollama.base_url', 'http://ollama.test');
        config()->set('ai.providers.ollama.model', 'qwen3:8b');
        config()->set('ai.providers.ollama.timeout', 10);

        Http::fake(function (Request $request) {
            $this->assertSame('http://ollama.test/api/generate', $request->url());
            $this->assertSame('qwen3:8b', $request['model']);
            $this->assertTrue($request['stream']);
            $this->assertFalse($request['think']);
            $this->assertSame(500, $request['options']['num_predict']);
            $this->assertSame(0.1, $request['options']['temperature']);

            return Http::response("{\"response\":\"Test response\",\"done\":false}\n{\"response\":\"\",\"done\":true,\"done_reason\":\"stop\",\"eval_count\":2}\n");
        });

        $chunks = [];

        $result = (new OllamaProvider)->stream(
            'Test prompt',
            function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
            [
                'temperature' => 0.1,
                'num_predict' => 500,
                'think' => false,
            ],
        );

        $this->assertSame(['Test response'], $chunks);
        $this->assertFalse($result->truncated);
        $this->assertSame($originalExecutionTimeLimit, ini_get('max_execution_time'));
    }

    public function test_stream_preserves_whitespace_only_response_chunks(): void
    {
        config()->set('ai.providers.ollama.base_url', 'http://ollama.test');
        config()->set('ai.providers.ollama.model', 'qwen3:8b');
        config()->set('ai.providers.ollama.timeout', 10);

        Http::fake([
            'http://ollama.test/api/generate' => Http::response(implode("\n", [
                '{"response":"project","done":false}',
                '{"response":" ","done":false}',
                '{"response":"is","done":false}',
                '{"response":" ","done":false}',
                '{"response":"68%","done":false}',
                '{"response":" ","done":false}',
                '{"response":"complete","done":false}',
                '{"response":"","done":true,"done_reason":"stop","eval_count":7}',
                '',
            ])),
        ]);

        $response = '';

        $result = (new OllamaProvider)->stream(
            'Test prompt',
            function (string $chunk) use (&$response): void {
                $response .= $chunk;
            },
        );

        $this->assertSame('project is 68% complete', $response);
        $this->assertFalse($result->truncated);
    }

    public function test_stream_detects_length_limited_response_and_keeps_partial_content(): void
    {
        config()->set('ai.providers.ollama.base_url', 'http://ollama.test');

        Http::fake([
            'http://ollama.test/api/generate' => Http::response(implode("\n", [
                '{"response":"Partial response","done":false}',
                '{"response":"","done":true,"done_reason":"length","eval_count":1200}',
                '',
            ])),
        ]);

        $response = '';
        $result = (new OllamaProvider)->stream(
            'Test prompt',
            function (string $chunk) use (&$response): void {
                $response .= $chunk;
            },
            ['num_predict' => 1200],
        );

        $this->assertSame('Partial response', $response);
        $this->assertTrue($result->truncated);
    }

    public function test_stream_uses_evaluated_token_count_when_done_reason_is_missing(): void
    {
        config()->set('ai.providers.ollama.base_url', 'http://ollama.test');

        Http::fake([
            'http://ollama.test/api/generate' => Http::response(implode("\n", [
                '{"response":"Partial response","done":false}',
                '{"response":"","done":true,"eval_count":1200}',
                '',
            ])),
        ]);

        $result = (new OllamaProvider)->stream(
            'Test prompt',
            fn (string $chunk) => null,
            ['num_predict' => 1200],
        );

        $this->assertTrue($result->truncated);
    }

    public function test_stream_rejects_unexpected_termination_after_retaining_partial_content(): void
    {
        config()->set('ai.providers.ollama.base_url', 'http://ollama.test');

        Http::fake([
            'http://ollama.test/api/generate' => Http::response(
                "{\"response\":\"Partial response\",\"done\":false}\n",
            ),
        ]);

        $response = '';

        try {
            (new OllamaProvider)->stream(
                'Test prompt',
                function (string $chunk) use (&$response): void {
                    $response .= $chunk;
                },
            );

            $this->fail('An interrupted stream should throw an exception.');
        } catch (AIProviderException $exception) {
            $this->assertSame(
                'Ollama ended the stream before reporting completion.',
                $exception->getMessage(),
            );
        }

        $this->assertSame('Partial response', $response);
    }

    public function test_stream_stops_when_the_client_cancels_generation(): void
    {
        config()->set('ai.providers.ollama.base_url', 'http://ollama.test');

        Http::fake([
            'http://ollama.test/api/generate' => Http::response(implode("\n", [
                '{"response":"First chunk","done":false}',
                '{"response":"Second chunk","done":false}',
                '{"response":"","done":true,"done_reason":"stop"}',
                '',
            ])),
        ]);

        $receivedChunks = 0;

        try {
            (new OllamaProvider)->stream(
                'Test prompt',
                function () use (&$receivedChunks): void {
                    $receivedChunks++;

                    throw new AIStreamCancelledException('Client disconnected.');
                },
            );

            $this->fail('Client cancellation should stop the provider stream.');
        } catch (AIStreamCancelledException) {
            $this->assertSame(1, $receivedChunks);
        }

    }
}
