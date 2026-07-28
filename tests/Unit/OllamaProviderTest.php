<?php

namespace Tests\Unit;

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

            return Http::response("{\"response\":\"Test response\",\"done\":false}\n{\"response\":\"\",\"done\":true}\n");
        });

        $chunks = [];

        (new OllamaProvider)->stream(
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
                '{"response":"","done":true}',
                '',
            ])),
        ]);

        $response = '';

        (new OllamaProvider)->stream(
            'Test prompt',
            function (string $chunk) use (&$response): void {
                $response .= $chunk;
            },
        );

        $this->assertSame('project is 68% complete', $response);
    }
}
