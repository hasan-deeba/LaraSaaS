<?php

namespace LaraSaaS\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Plan;
use LaraSaaS\Support\PlanFeatures;

class PlanAdminController extends Controller
{
    public function index(LaraSaaS $larasaas)
    {
        return Inertia::render($larasaas->page('Admin/Plans'), [
            'plans' => Plan::query()->orderBy('sort_order')->orderBy('id')->get(),
            'definitions' => $larasaas->planFeatureDefinitions(),
            'plans_url' => route($larasaas->routeName('admin.plans.index')),
        ]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));

        return back();
    }

    /** Archive rather than erase price mappings used by existing subscribers. */
    public function destroy(Plan $plan)
    {
        $plan->update(['active' => false]);

        return back();
    }

    protected function validated(Request $request, ?Plan $plan = null): array
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
            $rules["{$interval}.stripe_id"] = ["required_with:{$interval}", 'string', 'max:255', 'regex:/^price_[a-zA-Z0-9_]+$/', function ($attribute, $value, $fail) use ($interval, $plan) {
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
        $data = $request->validate(array_merge($rules, $catalog->rules()));
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
        $data['features'] = $catalog->values($data['features']);

        return $data;
    }
}
