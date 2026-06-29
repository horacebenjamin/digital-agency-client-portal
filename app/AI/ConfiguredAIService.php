<?php

namespace App\AI;

class ConfiguredAIService implements AIService
{
    public function __construct(private readonly AIProvider $provider)
    {
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->provider->complete($prompt, $options);
    }
}
