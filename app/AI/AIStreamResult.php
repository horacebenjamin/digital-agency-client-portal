<?php

namespace App\AI;

final readonly class AIStreamResult
{
    public function __construct(public bool $truncated) {}

    public static function completed(): self
    {
        return new self(truncated: false);
    }

    public static function lengthLimited(): self
    {
        return new self(truncated: true);
    }
}
