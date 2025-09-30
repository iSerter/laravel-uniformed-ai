<?php

namespace Iserter\UniformedAI\Logging\Usage;

/**
 * Immutable value object representing token usage & pricing metrics.
 */
final class UsageMetrics
{
    public function __construct(
        public ?int $promptTokens,
        public ?int $completionTokens,
        public ?int $totalTokens,
        public string $confidence, // reported|estimated|unknown
        public ?string $estimatedReason = null,
        public ?int $inputCostCents = null,
        public ?int $outputCostCents = null,
        public ?int $totalCostCents = null,
        public ?string $currency = null,
        public ?string $pricingSource = null,
        public ?array $providerRaw = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'confidence' => $this->confidence,
            'estimated_reason' => $this->estimatedReason,
            'input_cost_cents' => $this->inputCostCents,
            'output_cost_cents' => $this->outputCostCents,
            'total_cost_cents' => $this->totalCostCents,
            'currency' => $this->currency,
            'pricing_source' => $this->pricingSource,
            'provider_raw' => $this->providerRaw,
        ], function($v) { return $v !== null; });
    }
}
