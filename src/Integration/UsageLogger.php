<?php

namespace Iserter\UniformedAI\Integration;

use Iserter\UniformedAI\Logging\LogDraft;
use Iserter\UniformedAI\Logging\Usage\PricingEngine;
use Throwable;

class UsageLogger
{
    public function __construct(protected PricingEngine $pricingEngine) {}

    /**
     * Record an AI operation with usage logging and cost calculation.
     *
     * @template TResponse
     *
     * @param  string        $provider       Provider key (e.g. 'openai', 'anthropic')
     * @param  string        $model          Model identifier (e.g. 'gpt-4.1', 'claude-sonnet-4-5-20250514')
     * @param  string        $serviceType    Service type (e.g. 'chat', 'image', 'audio')
     * @param  string        $operation      Operation name (e.g. 'prompt', 'stream', 'generate')
     * @param  array         $request        Request payload to log
     * @param  callable():TResponse $execute Callable that performs the AI operation
     * @param  (callable(TResponse):array)|null $extractUsage  Callable that extracts usage from the response.
     *                                        Must return array with 'prompt_tokens' and 'completion_tokens' keys.
     * @param  (callable(TResponse):mixed)|null $extractResponse Callable to transform response for logging.
     *                                        If null, the raw response is logged.
     * @return TResponse
     */
    public function record(
        string $provider,
        string $model,
        string $serviceType,
        string $operation,
        array $request,
        callable $execute,
        ?callable $extractUsage = null,
        ?callable $extractResponse = null,
    ): mixed {
        $provider = $this->resolveProvider($provider);
        $draft = LogDraft::start($serviceType, $provider, $operation, $request, $model);

        try {
            $response = $execute();

            $responsePayload = $extractResponse ? $extractResponse($response) : $response;
            $draft->finishSuccess($responsePayload);

            if ($extractUsage) {
                $this->attachUsage($draft, $provider, $model, $serviceType, $extractUsage, $response);
            }

            return $response;
        } catch (Throwable $e) {
            $draft->finishError($e);
            throw $e;
        } finally {
            $draft->persist();
        }
    }

    /**
     * Record a streaming AI operation with usage logging and cost calculation.
     *
     * The returned generator yields deltas from the source. After the stream is fully consumed,
     * usage is extracted via $extractUsage (called with the accumulated content string) and logged.
     *
     * @param  string        $provider       Provider key
     * @param  string        $model          Model identifier
     * @param  string        $serviceType    Service type
     * @param  string        $operation      Operation name
     * @param  array         $request        Request payload to log
     * @param  iterable      $stream         The stream source (generator, iterator, etc.)
     * @param  (callable(string):array)|null $extractUsage Extracts usage from accumulated content.
     *                                        Must return array with 'prompt_tokens' and 'completion_tokens' keys.
     * @return \Generator
     */
    public function recordStream(
        string $provider,
        string $model,
        string $serviceType,
        string $operation,
        array $request,
        iterable $stream,
        ?callable $extractUsage = null,
    ): \Generator {
        $provider = $this->resolveProvider($provider);
        $draft = LogDraft::start($serviceType, $provider, $operation, $request, $model);

        try {
            foreach ($stream as $delta) {
                $draft->accumulateChunk($delta);
                yield $delta;
            }

            $draft->finishSuccessStreaming();

            if ($extractUsage) {
                $this->attachUsage($draft, $provider, $model, $serviceType, $extractUsage, $draft->getAccumulatedContent());
            }
        } catch (Throwable $e) {
            $draft->finishError($e);
            throw $e;
        } finally {
            $draft->persist();
        }
    }

    /**
     * Extract usage from the response and attach pricing to the draft.
     */
    protected function attachUsage(LogDraft $draft, string $provider, string $model, string $serviceType, callable $extractUsage, mixed $input): void
    {
        try {
            $usage = $extractUsage($input);

            if (!is_array($usage)) {
                return;
            }

            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;

            $pricing = $this->pricingEngine->price($provider, $model, $serviceType, $promptTokens, $completionTokens);

            $draft->attachUsageMetrics(array_filter([
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $usage['total_tokens'] ?? ($promptTokens + $completionTokens),
                'confidence' => $usage['confidence'] ?? 'reported',
                ...($pricing ?? []),
            ], fn ($v) => $v !== null));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Resolve provider alias to canonical provider key.
     */
    protected function resolveProvider(string $provider): string
    {
        $aliases = config('uniformed-ai.provider_aliases', []);

        return $aliases[$provider] ?? $provider;
    }
}
