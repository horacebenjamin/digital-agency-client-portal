<?php

namespace App\AI;

interface AIService
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generateText(string $prompt, array $options = []): string;
}
