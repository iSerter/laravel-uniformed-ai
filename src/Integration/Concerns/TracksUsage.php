<?php

namespace Iserter\UniformedAI\Integration\Concerns;

use Generator;
use Iserter\UniformedAI\Integration\UsageLogger;

/**
 * Add to Agent classes to enable usage logging and cost calculation.
 *
 * This trait has zero dependency on laravel/ai — it works with any class
 * that has prompt()/stream() methods or any AI client.
 *
 * Usage:
 *   class MyAgent implements Agent {
 *       use Promptable, TracksUsage;
 *
 *       protected function usageProvider(): string { return 'anthropic'; }
 *       protected function usageModel(): string { return 'claude-sonnet-4-5-20250514'; }
 *   }
 *
 *   // Then:
 *   $response = $agent->trackedPrompt('Hello', fn () => $agent->prompt('Hello'));
 */
trait TracksUsage
{
    use ExtractsUsage;

    /**
     * Override to set the provider key for logging/pricing.
     * Default: tries to read $this->provider (handles enum-like objects).
     */
    protected function usageProvider(): string
    {
        if (property_exists($this, 'provider')) {
            return $this->resolveProviderString($this->provider);
        }

        return 'unknown';
    }

    /**
     * Override to set the model identifier for logging/pricing.
     * Default: tries to read $this->model.
     */
    protected function usageModel(): string
    {
        if (property_exists($this, 'model') && is_string($this->model)) {
            return $this->model;
        }

        return 'unknown';
    }

    /**
     * Override to set the service type for logging/pricing.
     */
    protected function usageServiceType(): string
    {
        return 'chat';
    }

    /**
     * Perform an AI operation with usage tracking.
     *
     * @template TResponse
     * @param  string   $promptText  The prompt text (for logging)
     * @param  callable():TResponse $execute Callable that performs the AI operation
     * @param  ?string  $provider    Override provider (otherwise uses usageProvider())
     * @param  ?string  $model       Override model (otherwise uses usageModel())
     * @param  ?string  $operation   Operation name (default: 'prompt')
     * @return TResponse
     */
    public function trackedPrompt(
        string $promptText,
        callable $execute,
        ?string $provider = null,
        ?string $model = null,
        ?string $operation = null,
    ): mixed {
        return $this->resolveUsageLogger()->record(
            provider: $provider ?? $this->usageProvider(),
            model: $model ?? $this->usageModel(),
            serviceType: $this->usageServiceType(),
            operation: $operation ?? 'prompt',
            request: ['prompt' => $promptText],
            execute: $execute,
            extractUsage: fn (mixed $r) => $this->extractUsageFromResponse($r),
            extractResponse: fn (mixed $r) => $this->extractResponsePayload($r),
        );
    }

    /**
     * Perform a streaming AI operation with usage tracking.
     *
     * @param  string   $promptText   The prompt text (for logging)
     * @param  callable():iterable $execute Callable that returns a stream (generator/iterator)
     * @param  ?string  $provider     Override provider
     * @param  ?string  $model        Override model
     * @param  ?string  $operation    Operation name (default: 'stream')
     * @return Generator
     */
    public function trackedStream(
        string $promptText,
        callable $execute,
        ?string $provider = null,
        ?string $model = null,
        ?string $operation = null,
    ): Generator {
        $stream = $execute();

        return $this->resolveUsageLogger()->recordStream(
            provider: $provider ?? $this->usageProvider(),
            model: $model ?? $this->usageModel(),
            serviceType: $this->usageServiceType(),
            operation: $operation ?? 'stream',
            request: ['prompt' => $promptText],
            stream: $stream,
        );
    }

    /**
     * Resolve a provider value to a string. Handles enums, objects, and strings.
     */
    private function resolveProviderString(mixed $provider): string
    {
        if (is_string($provider)) {
            return $provider;
        }

        if (is_object($provider)) {
            if (property_exists($provider, 'value')) {
                return strtolower((string) $provider->value);
            }
            if (property_exists($provider, 'name')) {
                return strtolower($provider->name);
            }

            return strtolower((string) $provider);
        }

        return 'unknown';
    }

    protected function resolveUsageLogger(): UsageLogger
    {
        return app(UsageLogger::class);
    }
}
