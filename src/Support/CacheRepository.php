<?php

namespace Iserter\UniformedAI\Support;

use Illuminate\Support\Facades\Cache;

class CacheRepository
{
    public function remember(string $key, int $ttl, \Closure $callback)
    {
        return Cache::remember($key, $ttl, $callback);
    }
}
