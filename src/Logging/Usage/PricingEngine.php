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
        if (!$pricing) {
            return ['pricing_source' => 'unpriced'];
        }

        $unit = $pricing['unit'] ?? null;
        $divisor = $this->getTokenDivisor($unit);

        if ($divisor === null) {
            return ['pricing_source' => $pricing['source'] ?? 'unpriced'];
        }

        $inputPerUnit = $pricing['input'] ?? null;
        $outputPerUnit = $pricing['output'] ?? null;

        // Dynamic Pricing (Tiers)
        if (!empty($pricing['tiers'])) {
            $totalUsage = $promptTokens + $completionTokens;
            foreach ($pricing['tiers'] as $tier) {
                $max = $tier['max'] ?? PHP_INT_MAX;
                if ($totalUsage >= $tier['min'] && $totalUsage <= $max) {
                    $inputPerUnit = $tier['input'];
                    $outputPerUnit = $tier['output'];
                    break;
                }
            }
        }

        if ($inputPerUnit === null && $outputPerUnit === null) {
            return ['pricing_source' => $pricing['source']];
        }

        $round = config('uniformed-ai.logging.usage.rounding', 'bankers');
        $inputCost = $inputPerUnit !== null ? $this->roundCents(($promptTokens / $divisor) * $inputPerUnit, $round) : null;
        $outputCost = $outputPerUnit !== null ? $this->roundCents(($completionTokens / $divisor) * $outputPerUnit, $round) : null;
        $total = ($inputCost ?? 0) + ($outputCost ?? 0);

        return [
            'input_cost_cents' => $inputCost,
            'output_cost_cents' => $outputCost,
            'total_cost_cents' => $total,
            'currency' => $pricing['currency'] ?? 'USD',
            'pricing_source' => $pricing['source'] ?? 'db',
        ];
    }

    /**
     * Get the token divisor based on the pricing unit.
     */
    protected function getTokenDivisor(?string $unit): ?int
    {
        return match ($unit) {
            '1K_tokens' => 1_000,
            '1M_tokens' => 1_000_000,
            default => null,
        };
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
