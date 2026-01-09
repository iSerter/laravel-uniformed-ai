<?php

namespace Iserter\UniformedAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $service_pricing_id
 * @property int $min_units
 * @property int|null $max_units
 * @property int $input_cost_cents
 * @property int $output_cost_cents
 */
class ServicePricingTier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_units' => 'integer',
        'max_units' => 'integer',
        'input_cost_cents' => 'integer',
        'output_cost_cents' => 'integer',
    ];

    public function servicePricing(): BelongsTo
    {
        return $this->belongsTo(ServicePricing::class);
    }
}
