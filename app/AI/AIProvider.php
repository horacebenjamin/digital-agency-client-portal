<?php

namespace App\AI;

interface AIProvider
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function complete(string $prompt, array $options = []): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function stream(string $prompt, callable $onChunk, array $options = []): void;
}
