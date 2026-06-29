<?php

namespace App\AI;

interface AIProvider
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function complete(string $prompt, array $options = []): string;
}
