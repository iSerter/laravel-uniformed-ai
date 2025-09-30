<?php

namespace Iserter\UniformedAI\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $provider
 * @property string|null $service_type
 * @property string $model_pattern
 * @property string $unit
 * @property int|null $input_cost_cents
 * @property int|null $output_cost_cents
 * @property string $currency
 * @property \Carbon\Carbon|null $effective_at
 * @property \Carbon\Carbon|null $expires_at
 * @property bool $active
 * @property array|null $meta
 */
class ServicePricing extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'effective_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Scope: only active & currently effective rows (time window inclusive start, exclusive end)
     */
    public function scopeCurrent(Builder $query): Builder
    {
        $now = now();
        return $query->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_at')->orWhere('effective_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });
    }
}
