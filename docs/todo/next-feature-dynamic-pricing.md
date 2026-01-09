# Plan: Dynamic Pricing

Some models define pricing differently for below and above 128K tokens. (or 200K)

See: https://openrouter.ai/anthropic/claude-sonnet-4.5 for example. 


## Overview
Implement context-window-based pricing (tiers) using a standard 1:N relational database design. 

## Core Decisions

1.  **New Child Table:** Create `service_pricing_tiers` to store tier definitions.
2.  **Relationship:** A `ServicePricing` record can have multiple `ServicePricingTier` records (One-to-Many).
3.  **Resolution Logic:** The `PricingRepository` will eager-load tiers. The `PricingEngine` will calculate costs based on the appropriate tier for the given usage.
4.  **Fallback:** If no tiers exist for a model, the system falls back to the base `input_cost_cents` and `output_cost_cents` on the parent `ServicePricing` record.

## Database Schema

### Table: `service_pricing_tiers`

| Column | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigIncrements` | No | PK |
| `service_pricing_id` | `foreignId` | No | FK to `service_pricings`. Cascade on delete. |
| `min_units` | `unsignedInteger` | No | Start of range (inclusive). Default `0`. |
| `max_units` | `unsignedInteger` | Yes | End of range (inclusive). `NULL` = Infinity. |
| `input_cost_cents` | `unsignedInteger` | No | Cost per unit for this tier. |
| `output_cost_cents` | `unsignedInteger` | No | Cost per unit for this tier. |
| `created_at` | `timestamp` | Yes | |
| `updated_at` | `timestamp` | Yes | |

*Note: Tiers are non-overlapping ranges defined by `min_units` and `max_units`.*

## Implementation Steps

### 1. Database Migration
Create a new migration `create_service_pricing_tiers_table`.

```php
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
```

### 2. Models
*   **Create `ServicePricingTier` Model:**
    *   Fillable: `service_pricing_id`, `min_units`, `max_units`, `input_cost_cents`, `output_cost_cents`.
    *   Casts: `min_units` => integer, `max_units` => integer.
*   **Update `ServicePricing` Model:**
    *   Add relationship: `public function tiers() { return $this->hasMany(ServicePricingTier::class)->orderBy('min_units', 'asc'); }`

### 3. Update `PricingRepository`
Modify `src/Support/PricingRepository.php`:
*   Update `resolve()` method to eager load tiers: `->with('tiers')`.
*   Update `format()` method to include the tiers in the returned array.

```php
// In format():
'tiers' => $p->tiers->map(fn($t) => [
    'min' => $t->min_units,
    'max' => $t->max_units,
    'input' => $t->input_cost_cents,
    'output' => $t->output_cost_cents,
])->all(),
```

### 4. Update `PricingEngine`
Modify `src/Logging/Usage/PricingEngine.php`:
*   Update logic to check for the presence of `tiers`.
*   Iterate through tiers to find the matching one.

```php
if (!empty($pricing['tiers'])) {
    foreach ($pricing['tiers'] as $tier) {
        $max = $tier['max'] ?? PHP_INT_MAX;
        if ($totalTokens >= $tier['min'] && $totalTokens <= $max) {
             // Use this tier's pricing
             $inputCost = $tier['input'];
             $outputCost = $tier['output'];
             break;
        }
    }
}
```

### 5. Seeding / Data Migration
*   We need a way to populate these tiers.
*   **Strategy:** Update the `database/data/service_pricing_*.json` file structure to support a `tiers` array.
*   Create a console command (or temporary migration script) that reads the JSON, finds entries with `tiers`, and populates the `service_pricing_tiers` table for the corresponding `service_pricings` records.

**JSON Structure Example:**
```json
{
    "provider": "openai",
    "model_pattern": "gpt-4-turbo",
    "tiers": [
        { "min_units": 0, "max_units": 128000, "input_cost_cents": 1000, "output_cost_cents": 3000 },
        { "min_units": 128001, "max_units": null, "input_cost_cents": 2000, "output_cost_cents": 6000 }
    ]
}
```
