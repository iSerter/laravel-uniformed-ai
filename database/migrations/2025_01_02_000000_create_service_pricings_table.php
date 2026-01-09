<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_pricings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider', 40); // e.g. openai
            $table->string('service_type', 20)->nullable(); // chat|image|audio|music|search (nullable for generic)
            $table->string('model_pattern', 160); // exact model or wildcard pattern with * suffix
            $table->string('unit', 32)->default('1K_tokens'); // future: image, second, character
            $table->unsignedInteger('input_cost_cents')->nullable(); // per unit (e.g. per 1K prompt tokens)
            $table->unsignedInteger('output_cost_cents')->nullable(); // per unit (e.g. per 1K completion tokens)
            $table->string('currency', 8)->default('USD');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->json('meta')->nullable(); // arbitrary metadata (e.g. source, notes)
            $table->timestamps();

            $table->index(['provider', 'service_type']);
            $table->index(['provider', 'model_pattern']);
            $table->index(['active', 'effective_at']);
        });

        // Seed baseline pricing from external JSON for easier maintenance.
        $jsonPath = __DIR__ . '/../data/service_pricing_20251007.json';
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
                        'unit' => $r['unit'] ?? '1M_tokens',
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
        Schema::dropIfExists('service_pricings');
    }
};
