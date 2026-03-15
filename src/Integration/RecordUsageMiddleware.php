<?php

namespace Iserter\UniformedAI\Integration;

use Closure;
use Iserter\UniformedAI\Integration\Concerns\ExtractsUsage;

/**
 * Agent middleware that automatically logs usage and calculates cost.
 *
 * Compatible with laravel/ai's HasMiddleware pattern. Uses duck typing —
 * no dependency on any laravel/ai class.
 *
 * Usage:
 *   class MyAgent implements Agent, HasMiddleware {
 *       use Promptable;
 *
 *       public function middleware(): array {
 *           return [
 *               RecordUsageMiddleware::make(provider: 'anthropic', model: 'claude-sonnet-4-5-20250514'),
 *           ];
 *       }
 *   }
 */
class RecordUsageMiddleware
{
    use ExtractsUsage;

    public function __construct(
        protected ?string $provider = null,
        protected ?string $model = null,
        protected string $serviceType = 'chat',
        protected ?Closure $extractUsage = null,
    ) {}

    public static function make(
        ?string $provider = null,
        ?string $model = null,
        string $serviceType = 'chat',
        ?Closure $extractUsage = null,
    ): self {
        return new self($provider, $model, $serviceType, $extractUsage);
    }

    /**
     * Handle the agent prompt. Uses duck typing on $prompt — no type hints.
     */
    public function handle(mixed $prompt, Closure $next): mixed
    {
        $provider = $this->provider ?? $this->resolveProvider($prompt);
        $model = $this->model ?? $this->resolveModel($prompt);
        $promptText = $this->resolvePromptText($prompt);

        return app(UsageLogger::class)->record(
            provider: $provider,
            model: $model,
            serviceType: $this->serviceType,
            operation: 'prompt',
            request: ['prompt' => $promptText],
            execute: fn () => $next($prompt),
            extractUsage: $this->extractUsage ?? fn (mixed $r) => $this->extractUsageFromResponse($r),
            extractResponse: fn (mixed $r) => $this->extractResponsePayload($r),
        );
    }

    /**
     * Duck-type provider from the prompt object. Handles enums.
     */
    protected function resolveProvider(mixed $prompt): string
    {
        if (!is_object($prompt) || !property_exists($prompt, 'provider')) {
            return 'unknown';
        }

        $p = $prompt->provider;

        if (is_string($p)) {
            return $p;
        }

        if (is_object($p)) {
            if (property_exists($p, 'value')) {
                return strtolower((string) $p->value);
            }
            if (property_exists($p, 'name')) {
                return strtolower($p->name);
            }

            return strtolower((string) $p);
        }

        return 'unknown';
    }

    /**
     * Duck-type model from the prompt object.
     */
    protected function resolveModel(mixed $prompt): string
    {
        if (is_object($prompt) && property_exists($prompt, 'model') && is_string($prompt->model)) {
            return $prompt->model;
        }

        return 'unknown';
    }

    /**
     * Duck-type prompt text from the prompt object.
     */
    protected function resolvePromptText(mixed $prompt): string
    {
        if (is_string($prompt)) {
            return $prompt;
        }

        if (is_object($prompt) && property_exists($prompt, 'prompt')) {
            return (string) $prompt->prompt;
        }

        return '';
    }
}
