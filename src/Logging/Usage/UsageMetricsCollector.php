<?php

namespace Iserter\UniformedAI\Logging\Usage;

use Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest;

class UsageMetricsCollector
{
    public function __construct(
        protected ProviderUsageExtractor $extractor,
        protected TokenEstimator $estimator,
        protected PricingEngine $pricing,
    ) {}

    public function collectChat(string $provider, string $model, ChatRequest $request, ?array $rawResponse, ?string $finalContent, string $operation, bool $wasError = false): ?UsageMetrics
    {
        if (!config('uniformed-ai.logging.usage.enabled', true)) return null;
        if (!config('uniformed-ai.logging.usage.services.chat', true)) return null;

        // Sampling (skip success path only). Errors always attempted.
        if (!$wasError) {
            $rate = (float) config('uniformed-ai.logging.usage.sampling.success_rate', 1.0);
            if ($rate < 1.0 && mt_rand() / mt_getrandmax() > $rate) return null;
        }

        $reported = $this->extractor->extract($provider, $rawResponse);
        $prompt = $reported['prompt'] ?? null;
        $completion = $reported['completion'] ?? null;
        $total = $reported['total'] ?? (($prompt!==null && $completion!==null) ? $prompt + $completion : null);

        $confidence = 'unknown'; $estimatedReason = null;
        if ($prompt !== null && $completion !== null) {
            $confidence = 'reported';
        }
        $estimateMissing = (bool) config('uniformed-ai.logging.usage.estimate_missing', true);
        if ($confidence !== 'reported' && $estimateMissing) {
            $estPrompt = $prompt ?? $this->estimator->estimatePromptTokens($request);
            $estCompletion = $completion ?? $this->estimator->estimateCompletionTokens($finalContent ?? '');
            $prompt = $estPrompt; $completion = $estCompletion; $total = $estPrompt + $estCompletion; $confidence = 'estimated';
            $estimatedReason = $reported ? 'provider_usage_partial' : 'provider_usage_missing';
        }
        if ($prompt === null && $completion === null) {
            return new UsageMetrics(null, null, $total, $confidence, $estimatedReason); // nothing to price
        }

        $pricing = $this->pricing->price($provider, $model, 'chat', $prompt ?? 0, $completion ?? 0) ?? null;
        $providerRaw = null;
        if (config('uniformed-ai.logging.usage.store_provider_raw', false)) {
            $providerRaw = $reported ? $reported : null;
        }

        return new UsageMetrics(
            $prompt,
            $completion,
            $total,
            $confidence,
            $estimatedReason,
            $pricing['input_cost_cents'] ?? null,
            $pricing['output_cost_cents'] ?? null,
            $pricing['total_cost_cents'] ?? null,
            $pricing['currency'] ?? null,
            $pricing['pricing_source'] ?? null,
            $providerRaw,
        );
    }
}
