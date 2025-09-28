<?php

namespace Iserter\UniformedAI\Support;

use Illuminate\Support\Facades\Cache;
use Iserter\UniformedAI\Exceptions\RateLimitException;

class RateLimiter
{
    public function throttle(string $provider, int $limitPerMinute): void
    {
        if ($limitPerMinute <= 0) return;
        $key = 'uniformed-ai:rl:' . $provider . ':' . now()->format('YmdHi');
        $count = Cache::increment($key);
        if ($count === 1) Cache::put($key, $count, 65); // expire in ~1 minute
        if ($count > $limitPerMinute) {
            throw new RateLimitException("Rate limit exceeded for {$provider}", provider: $provider, status: 429);
        }
    }
}
