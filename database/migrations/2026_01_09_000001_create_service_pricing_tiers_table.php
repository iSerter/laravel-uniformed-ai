<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_pricing_id')->constrained('service_pricings')->cascadeOnDelete();
            $table->unsignedInteger('min_units')->default(0)->comment('Start of range (inclusive)');
            $table->unsignedInteger('max_units')->nullable()->comment('End of range (inclusive). Null for infinity.');
            $table->unsignedInteger('input_cost_cents');
            $table->unsignedInteger('output_cost_cents');
            $table->timestamps();

            // Ensure no overlapping ambiguous tiers for the same pricing (optional but good practice)
            $table->index(['service_pricing_id', 'min_units']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_pricing_tiers');
    }
};
