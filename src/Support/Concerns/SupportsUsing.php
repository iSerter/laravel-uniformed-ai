<?php

namespace Iserter\UniformedAI\Support\Concerns;

use Iserter\UniformedAI\Exceptions\UniformedAIException;

/**
 * Mixin for Manager subclasses to provide a fluent using($driver) helper.
 */
trait SupportsUsing
{
    /**
     * Return the concrete driver instance for a given name with validation.
     * Allows: AI::chat()->using('openrouter')->send(...)
     */
    public function using(string $driver)
    {
        // Illuminate Support Manager already throws if driver method missing.
        try {
            return $this->driver($driver);
        } catch (\InvalidArgumentException $e) {
            throw new UniformedAIException("AI driver '{$driver}' is not registered or unsupported.", provider: $driver, status: 0, raw: [ 'reason' => $e->getMessage() ]);
        }
    }
}
