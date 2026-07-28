<?php

namespace App\AI;

final class AIChatStreamProtocol
{
    public const MARKER = "\n\x1EAI_PROJECT_ASSISTANT_EVENT:";

    public static function completed(AIStreamResult $result): string
    {
        return self::event([
            'type' => 'finish',
            'truncated' => $result->truncated,
        ]);
    }

    public static function failed(): string
    {
        return self::event([
            'type' => 'error',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function event(array $payload): string
    {
        return self::MARKER.json_encode($payload, JSON_THROW_ON_ERROR)."\n";
    }
}
