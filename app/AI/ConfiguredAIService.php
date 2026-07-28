<?php

namespace App\AI;

class ConfiguredAIService implements AIService
{
    public function __construct(private readonly AIProvider $provider) {}

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->provider->complete($prompt, $options);
    }

    public function streamText(string $prompt, callable $onChunk, array $options = []): AIStreamResult
    {
        return $this->provider->stream($prompt, $onChunk, $options);
    }
}
