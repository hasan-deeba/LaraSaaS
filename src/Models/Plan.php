<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Model;
use LaraSaaS\Support\PlanFeatures;

class Plan extends Model
{
    protected $table = 'larasaas_plans';

    protected $fillable = [
        'key', 'name', 'description', 'active', 'highlight', 'badge',
        'trial_days', 'monthly', 'yearly', 'features', 'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean', 'highlight' => 'boolean', 'trial_days' => 'integer',
        'monthly' => 'array', 'yearly' => 'array', 'features' => 'array', 'sort_order' => 'integer',
    ];

    public function forPricing(): array
    {
        $values = app(PlanFeatures::class)->values($this->features ?? []);

        return array_merge($this->only(['name', 'description', 'highlight', 'badge', 'trial_days', 'monthly', 'yearly']), [
            'entitlements' => $values,
            'limits' => array_filter($values, fn ($value) => ! is_bool($value)),
            'features' => app(PlanFeatures::class)->labels($values),
        ]);
    }
}
