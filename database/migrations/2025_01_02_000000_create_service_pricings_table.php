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

        // Seed baseline pricing (reference rates; adjust as needed). Values are cents per 1K tokens.
        $now = now();
        DB::table('service_pricings')->insert([
            [
                'provider' => 'openai',
                'service_type' => 'chat',
                'model_pattern' => 'gpt-4.1-mini',
                'unit' => '1K_tokens',
                'input_cost_cents' => 15,  // $0.00015 per token => $0.15 per 1K
                'output_cost_cents' => 60, // $0.00060 per token => $0.60 per 1K
                'currency' => 'USD',
                'effective_at' => $now,
                'expires_at' => null,
                'active' => true,
                'meta' => json_encode(['seed' => true, 'source' => 'baseline']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'provider' => 'openai',
                'service_type' => 'chat',
                'model_pattern' => 'gpt-4o*',
                'unit' => '1K_tokens',
                'input_cost_cents' => 500,  // example placeholder
                'output_cost_cents' => 1500, // example placeholder
                'currency' => 'USD',
                'effective_at' => $now,
                'expires_at' => null,
                'active' => true,
                'meta' => json_encode(['seed' => true, 'note' => 'wildcard GPT-4o family']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'provider' => 'google',
                'service_type' => 'chat',
                'model_pattern' => 'gemini-1.5-flash',
                'unit' => '1K_tokens',
                'input_cost_cents' => 7,   // $0.00007 per token => $0.07 per 1K (example)
                'output_cost_cents' => 30, // $0.00030 per token => $0.30 per 1K (example)
                'currency' => 'USD',
                'effective_at' => $now,
                'expires_at' => null,
                'active' => true,
                'meta' => json_encode(['seed' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'provider' => 'openrouter',
                'service_type' => 'chat',
                'model_pattern' => 'meta-llama/llama-3.1-8b-instruct',
                'unit' => '1K_tokens',
                'input_cost_cents' => 20,
                'output_cost_cents' => 40,
                'currency' => 'USD',
                'effective_at' => $now,
                'expires_at' => null,
                'active' => true,
                'meta' => json_encode(['seed' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_pricings');
    }
};
