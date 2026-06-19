<?php

use Illuminate\Database\Migrations\Migration;
use Iserter\UniformedAI\Models\ServicePricing;

return new class extends Migration
{
    public function up(): void
    {
        $jsonPath = __DIR__ . '/../data/new_models_pricing_20260315.json';
        if (! file_exists($jsonPath)) {
            return;
        }

        $raw = file_get_contents($jsonPath);
        $decoded = json_decode($raw, true) ?: [];
        $now = now();

        foreach ($decoded as $item) {
            if (empty($item['provider']) || empty($item['model_pattern'])) {
                continue;
            }

            $pricing = ServicePricing::updateOrCreate(
                [
                    'provider' => $item['provider'],
                    'model_pattern' => $item['model_pattern'],
                    'service_type' => $item['service_type'] ?? null,
                ],
                [
                    'unit' => $item['unit'] ?? '1K_tokens',
                    'input_cost_cents' => $item['input_cost_cents'] ?? null,
                    'output_cost_cents' => $item['output_cost_cents'] ?? null,
                    'currency' => $item['currency'] ?? 'USD',
                    'effective_at' => $item['effective_at'] ?? $now,
                    'expires_at' => $item['expires_at'] ?? null,
                    'active' => $item['active'] ?? true,
                    'meta' => $item['meta'] ?? null,
                ]
            );

            if (! empty($item['tiers']) && is_array($item['tiers'])) {
                $pricing->tiers()->delete();
                foreach ($item['tiers'] as $tierData) {
                    $pricing->tiers()->create([
                        'min_units' => $tierData['min_units'],
                        'max_units' => $tierData['max_units'] ?? null,
                        'input_cost_cents' => $tierData['input_cost_cents'],
                        'output_cost_cents' => $tierData['output_cost_cents'],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        ServicePricing::where('meta->source', 'new_models_pricing_2026-03-15')->delete();
    }
};
