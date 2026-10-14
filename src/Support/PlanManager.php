<?php

namespace LaraSaaS\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LaraSaaS\Models\Plan;

/**
 * Validated catalog writes for any host dashboard, command or seeder.
 * The caller owns authorization; this service does not depend on HTTP or embeds.
 */
class PlanManager
{
    public function create(array $attributes): Plan
    {
        return Plan::create($this->validated(array_replace([
            'active' => true, 'highlight' => false, 'sort_order' => 0,
            'features' => [], 'monthly' => null, 'yearly' => null,
        ], $attributes)));
    }

    /** Omitted fields are preserved; supplied feature/interval maps replace the old map. */
    public function update(Plan $plan, array $attributes): Plan
    {
        $plan->fill($this->validated(array_replace($plan->only($plan->getFillable()), $attributes), $plan));
        $plan->save();

        return $plan;
    }

    /** Keep existing subscription price mappings and entitlements intact. */
    public function archive(Plan $plan): Plan
    {
        $plan->update(['active' => false]);

        return $plan;
    }

    protected function validated(array $attributes, ?Plan $plan = null): array
    {
        $catalog = app(PlanFeatures::class);
        $rules = [
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('larasaas_plans', 'key')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'],
            'highlight' => ['required', 'boolean'],
            'badge' => ['nullable', 'string', 'max:80'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
        // Plan keys and Stripe prices identify subscriptions. Once created, keep
        // price IDs stable: archive the plan and create another to change prices.
        if ($plan) {
            $rules['key'][] = Rule::in([$plan->key]);
        }
        foreach (['monthly', 'yearly'] as $interval) {
            $rules[$interval] = ['nullable', 'array:price,stripe_id'];
            $rules["{$interval}.price"] = ["required_with:{$interval}", 'numeric', 'min:0'];
            $rules["{$interval}.stripe_id"] = ['bail', "required_with:{$interval}", 'string', 'max:255', 'regex:/^price_[a-zA-Z0-9_]+$/', function ($attribute, $value, $fail) use ($plan) {
                $query = Plan::query()->where(function ($query) use ($value) {
                    $query->where('monthly->stripe_id', $value)->orWhere('yearly->stripe_id', $value);
                });
                if ($plan) {
                    $query->where('id', '!=', $plan->id);
                }
                if ($query->exists()) {
                    $fail('This Stripe price already belongs to another plan.');
                }
            }];
        }
        $data = Validator::make($attributes, array_merge($rules, $catalog->rules()))->validate();
        $errors = [];
        if (empty($data['monthly']) && empty($data['yearly'])) {
            $errors['monthly'] = 'Configure at least one Stripe price.';
        }
        if (! empty($data['monthly']) && ! empty($data['yearly']) && $data['monthly']['stripe_id'] === $data['yearly']['stripe_id']) {
            $errors['yearly.stripe_id'] = 'Monthly and yearly prices must be different.';
        }
        if ($plan) {
            foreach (['monthly', 'yearly'] as $interval) {
                if (($plan->getAttribute($interval)['stripe_id'] ?? null) !== ($data[$interval]['stripe_id'] ?? null)) {
                    $errors["{$interval}.stripe_id"] = 'Archive this plan and create a new plan to change Stripe prices.';
                }
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        foreach (['monthly', 'yearly'] as $interval) {
            if (! empty($data[$interval])) {
                $data[$interval]['price'] = (float) $data[$interval]['price'];
            }
        }
        // Laravel can omit an empty parent array when only nested rules remain.
        $data['features'] = $catalog->values($data['features'] ?? []);

        return $data;
    }
}
