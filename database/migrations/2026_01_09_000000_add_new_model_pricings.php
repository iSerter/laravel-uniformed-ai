<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Prices may be different for smaller size payloads because  most of them define pricing differently for below and above 128K tokens.
//I picked the highest prices to be on the safe side.
        // Insert new model pricings from external JSON for easier maintenance.
        $jsonPath = __DIR__ . '/../data/service_pricing_20260109.json';
        if (file_exists($jsonPath)) {
            $raw = file_get_contents($jsonPath);
            $decoded = json_decode($raw, true) ?: [];
            $now = now();
            $rows = collect($decoded)
                ->filter(fn ($r) => is_array($r) && !empty($r['provider']) && !empty($r['model_pattern']))
                ->map(function (array $r) use ($now) {
                    return [
                        'provider' => $r['provider'],
                        'service_type' => $r['service_type'] ?? null,
                        'model_pattern' => $r['model_pattern'],
                        'unit' => $r['unit'] ?? '1K_tokens',
                        'input_cost_cents' => $r['input_cost_cents'] ?? null,
                        'output_cost_cents' => $r['output_cost_cents'] ?? null,
                        'currency' => $r['currency'] ?? 'USD',
                        'effective_at' => $r['effective_at'] ? ($r['effective_at'] instanceof \DateTimeInterface ? $r['effective_at'] : $now) : $now,
                        'expires_at' => $r['expires_at'] ?? null,
                        'active' => array_key_exists('active', $r) ? (bool)$r['active'] : true,
                        'meta' => isset($r['meta']) ? (is_string($r['meta']) ? $r['meta'] : json_encode($r['meta'])) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })
                ->values()
                ->all();

            if (!empty($rows)) {
                DB::table('service_pricings')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        // Remove the newly added pricings by their source metadata
        DB::table('service_pricings')
            ->where('meta->source', 'like', 'openai_pricing_2026-01-09')
            ->orWhere('meta->source', 'like', 'openrouter_pricing_2026-01-09')
            ->delete();
    }
};
