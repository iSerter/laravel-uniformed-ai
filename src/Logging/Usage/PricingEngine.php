<?php

namespace Iserter\UniformedAI\Logging\Usage;

use Iserter\UniformedAI\Support\PricingRepository;

class PricingEngine
{
    public function __construct(protected PricingRepository $repo) {}

    /**
     * Returns array with cost cents + currency + source OR null if pricing unavailable.
     */
    public function price(string $provider, string $model, string $serviceType, int $promptTokens, int $completionTokens): ?array
    {
        $pricing = $this->repo->resolve($provider, $model, $serviceType);
        if (!$pricing) return ['pricing_source' => 'unpriced'];
        if (($pricing['unit'] ?? null) !== '1K_tokens') return ['pricing_source' => $pricing['source'] ?? 'unpriced'];
        $inputPerK = $pricing['input'] ?? null; $outputPerK = $pricing['output'] ?? null;
        if ($inputPerK === null && $outputPerK === null) return ['pricing_source' => $pricing['source']];
        $round = config('uniformed-ai.logging.usage.rounding', 'bankers');
        $inputCost = $inputPerK !== null ? $this->roundCents(($promptTokens/1000)*$inputPerK, $round) : null;
        $outputCost = $outputPerK !== null ? $this->roundCents(($completionTokens/1000)*$outputPerK, $round) : null;
        $total = ($inputCost ?? 0) + ($outputCost ?? 0);
        return [
            'input_cost_cents' => $inputCost,
            'output_cost_cents' => $outputCost,
            'total_cost_cents' => $total,
            'currency' => $pricing['currency'] ?? 'USD',
            'pricing_source' => $pricing['source'] ?? 'db',
        ];
    }

    protected function roundCents(float $value, string $mode): int
    {
        return match($mode) {
            'ceil' => (int) ceil($value),
            'floor' => (int) floor($value),
            'bankers' => (int) $this->bankersRound($value),
            default => (int) round($value),
        };
    }

    protected function bankersRound(float $value): float
    {
        $floor = floor($value);
        $diff = $value - $floor;
        if ($diff > 0.5) return $floor + 1.0;
        if ($diff < 0.5) return $floor;
        // exactly .5
        return ($floor % 2 === 0) ? $floor : $floor + 1.0;
    }
}
