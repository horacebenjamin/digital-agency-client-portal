<?php

namespace App\AI\Providers;

use App\AI\AIProvider;
use App\AI\AIProviderException;
use App\AI\AIStreamCancelledException;
use App\AI\AIStreamResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;
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

    public function stream(string $prompt, callable $onChunk, array $options = []): AIStreamResult
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
            'stream' => true,
            'options' => $modelOptions,
        ];

        if (array_key_exists('think', $options)) {
            $payload['think'] = $options['think'];
        }

        try {
            $response = Http::timeout($timeout)
                ->withOptions(['stream' => true])
                ->post("{$baseUrl}/api/generate", $payload);

            if ($response->failed()) {
                throw new AIProviderException('Ollama returned an error: '.$response->body());
            }

            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $completed = false;
            $finishReason = null;
            $evaluatedTokens = null;
            $hasContent = false;

            $consumeLine = function (string $line) use (
                $onChunk,
                &$completed,
                &$finishReason,
                &$evaluatedTokens,
                &$hasContent,
            ): void {
                $line = trim($line);

                if ($line === '') {
                    return;
                }

                try {
                    $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new AIProviderException('Ollama returned an invalid streaming response.', previous: $exception);
                }

                if (! is_array($decoded)) {
                    throw new AIProviderException('Ollama returned an invalid streaming response.');
                }

                if (is_string($decoded['response'] ?? null) && $decoded['response'] !== '') {
                    $hasContent = true;
                    $onChunk($decoded['response']);
                }

                if (($decoded['done'] ?? false) === true) {
                    $completed = true;
                    $finishReason = is_string($decoded['done_reason'] ?? null)
                        ? $decoded['done_reason']
                        : null;
                    $evaluatedTokens = is_int($decoded['eval_count'] ?? null)
                        ? $decoded['eval_count']
                        : null;
                }
            };

            while (! $body->eof()) {
                $buffer .= $body->read(1024);

                while (($newlinePosition = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newlinePosition);
                    $buffer = substr($buffer, $newlinePosition + 1);
                    $consumeLine($line);
                }
            }

            $consumeLine($buffer);

            if (! $completed) {
                throw new AIProviderException('Ollama ended the stream before reporting completion.');
            }

            if (! $hasContent) {
                throw new AIProviderException('Ollama returned an empty streaming response.');
            }

            $configuredTokenLimit = isset($options['num_predict'])
                ? (int) $options['num_predict']
                : null;
            $reachedLengthLimit = $finishReason === 'length'
                || ($finishReason === null
                    && $configuredTokenLimit !== null
                    && $configuredTokenLimit > 0
                    && $evaluatedTokens !== null
                    && $evaluatedTokens >= $configuredTokenLimit);

            return $reachedLengthLimit
                ? AIStreamResult::lengthLimited()
                : AIStreamResult::completed();
        } catch (AIStreamCancelledException|AIProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AIProviderException('The AI provider streaming request failed.', previous: $exception);
        }
    }

    private function extendExecutionTime(int $timeout): void
    {
        if (! function_exists('set_time_limit') || app()->runningInConsole()) {
            return;
        }

        @set_time_limit($timeout + 10);
    }
}
