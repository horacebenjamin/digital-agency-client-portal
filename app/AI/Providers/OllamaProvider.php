<?php

namespace App\AI\Providers;

use App\AI\AIProvider;
use App\AI\AIProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaProvider implements AIProvider
{
    public function complete(string $prompt, array $options = []): string
    {
        $baseUrl = rtrim((string) config('ai.providers.ollama.base_url'), '/');
        $model = (string) ($options['model'] ?? config('ai.providers.ollama.model'));
        $timeout = (int) config('ai.providers.ollama.timeout', 60);

        $this->extendExecutionTime($timeout);

        $modelOptions = [
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        if (isset($options['num_predict'])) {
            $modelOptions['num_predict'] = $options['num_predict'];
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => $modelOptions,
        ];

        if (array_key_exists('think', $options)) {
            $payload['think'] = $options['think'];
        }

        try {
            $response = Http::timeout($timeout)->post("{$baseUrl}/api/generate", $payload);
        } catch (ConnectionException $exception) {
            throw new AIProviderException('Ollama is unavailable. Check that the Ollama service is running.', previous: $exception);
        } catch (Throwable $exception) {
            throw new AIProviderException('The AI provider request failed.', previous: $exception);
        }

        if ($response->failed()) {
            $providerMessage = $response->json('error') ?: $response->body();
            $message = filled($providerMessage)
                ? 'Ollama returned an error: '.$providerMessage
                : 'Ollama returned an error while generating the summary.';

            throw new AIProviderException($message);
        }

        $text = trim((string) $response->json('response'));

        if ($text === '') {
            throw new AIProviderException('Ollama returned an empty summary.');
        }

        return $text;
    }

    private function extendExecutionTime(int $timeout): void
    {
        if (! function_exists('set_time_limit')) {
            return;
        }

        @set_time_limit($timeout + 10);
    }
}
