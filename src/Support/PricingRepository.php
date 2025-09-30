<?php

namespace Iserter\UniformedAI\Support;

use Illuminate\Support\Facades\Cache;
use Iserter\UniformedAI\Models\ServicePricing;

class PricingRepository
{
    /**
     * Retrieve pricing definition for provider + model (+ optional service) from DB patterns.
     * Resolution precedence:
     * 1. Exact match with service_type
     * 2. Exact match without service_type
     * 3. Wildcard (service) patterns in insertion/newest order
     * 4. Wildcard (global) patterns
     */
    public function resolve(string $provider, string $model, ?string $serviceType = null): ?array
    {
        $cacheKey = "uniformed-ai:pricing:{$provider}:{$serviceType}:{$model}";
        return Cache::remember($cacheKey, 300, function () use ($provider, $model, $serviceType) {
            $rows = ServicePricing::query()->current()
                ->where('provider', $provider)
                ->orderByDesc('updated_at') // latest takes precedence
                ->get();

            $exactService = $rows->first(fn($r) => $serviceType && $r->service_type === $serviceType && $r->model_pattern === $model);
            if ($exactService) return $this->format($exactService);

            $exactGlobal = $rows->first(fn($r) => $r->service_type === null && $r->model_pattern === $model);
            if ($exactGlobal) return $this->format($exactGlobal);

            // Wildcards (service)
            if ($serviceType) {
                $wildService = $rows->first(function ($r) use ($serviceType, $model) {
                    return $r->service_type === $serviceType && str_ends_with($r->model_pattern, '*')
                        && str_starts_with($model, substr($r->model_pattern, 0, -1));
                });
                if ($wildService) return $this->format($wildService);
            }

            // Wildcards (global)
            $wildGlobal = $rows->first(function ($r) use ($model) {
                return $r->service_type === null && str_ends_with($r->model_pattern, '*')
                    && str_starts_with($model, substr($r->model_pattern, 0, -1));
            });
            if ($wildGlobal) return $this->format($wildGlobal);

            return null;
        });
    }

    protected function format(ServicePricing $p): array
    {
        return [
            'unit' => $p->unit,
            'input' => $p->input_cost_cents, // per unit in cents
            'output' => $p->output_cost_cents, // per unit in cents
            'currency' => $p->currency,
            'effective_at' => optional($p->effective_at)->toIso8601String(),
            'source' => 'db:'.$p->id,
            'pattern' => $p->model_pattern,
        ];
    }
}
