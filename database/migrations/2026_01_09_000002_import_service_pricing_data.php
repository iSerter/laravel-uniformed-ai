<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Iserter\UniformedAI\Models\ServicePricing;
use Iserter\UniformedAI\Models\ServicePricingTier;

return new class extends Migration
{
    public function up(): void
    {
        $jsonPath = __DIR__ . '/../data/service_pricing_20260109.json';
        if (!file_exists($jsonPath)) {
            return;
        }

        $raw = file_get_contents($jsonPath);
        $decoded = json_decode($raw, true) ?: [];
        $now = now();

        foreach ($decoded as $item) {
            if (empty($item['provider']) || empty($item['model_pattern'])) {
                continue;
            }

            // 1. Create or Update the Parent Pricing
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

            // 2. Sync Tiers if present
            if (!empty($item['tiers']) && is_array($item['tiers'])) {
                // Optional: clear existing tiers for this pricing to ensure sync
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
        // Remove the data imported from this specific source
        $sources = ['openai_pricing_2026-01-09', 'openrouter_pricing_2026-01-09'];
        
        foreach ($sources as $source) {
            // Tiers will cascade delete because of the foreign key constraint
            ServicePricing::where('meta->source', $source)->delete();
        }
    }
};
