<?php

namespace App\AI;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class AIProjectChatLock
{
    private bool $released = false;

    private function __construct(
        private readonly Lock $lock,
        private readonly Repository $cache,
        private readonly string $ownerCacheKey,
        private readonly string $owner,
    ) {}

    public static function acquire(
        int|string $userId,
        int|string $projectId,
        string $requestId,
        int $seconds,
    ): ?self {
        $cache = Cache::store();
        $lock = $cache->lock(self::lockName($userId, $projectId), $seconds);

        if (! $lock->get()) {
            return null;
        }

        $ownerCacheKey = self::ownerCacheKey($userId, $projectId, $requestId);
        $owner = $lock->owner();
        $cache->put($ownerCacheKey, $owner, $seconds);

        return new self($lock, $cache, $ownerCacheKey, $owner);
    }

    public static function cancel(
        int|string $userId,
        int|string $projectId,
        string $requestId,
    ): bool {
        $cache = Cache::store();
        $owner = $cache->pull(self::ownerCacheKey($userId, $projectId, $requestId));

        if (! is_string($owner) || $owner === '') {
            return false;
        }

        return $cache->restoreLock(self::lockName($userId, $projectId), $owner)->release();
    }

    public function release(): bool
    {
        if ($this->released) {
            return false;
        }

        $released = $this->lock->release();
        $this->released = true;

        if ($this->cache->get($this->ownerCacheKey) === $this->owner) {
            $this->cache->forget($this->ownerCacheKey);
        }

        return $released;
    }

    public static function lockName(int|string $userId, int|string $projectId): string
    {
        return "ai-project-chat:{$userId}:{$projectId}";
    }

    private static function ownerCacheKey(
        int|string $userId,
        int|string $projectId,
        string $requestId,
    ): string {
        return "ai-project-chat-owner:{$userId}:{$projectId}:{$requestId}";
    }
}
