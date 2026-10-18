<?php

namespace LaraSaaS\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Plan;

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

    public function store(Request $request, LaraSaaS $larasaas)
    {
        $larasaas->createPlan($request->all());

        return back();
    }

    public function update(Request $request, Plan $plan, LaraSaaS $larasaas)
    {
        $larasaas->updatePlan($plan, $request->all());

        return back();
    }

    /** Archive rather than erase price mappings used by existing subscribers. */
    public function destroy(Plan $plan, LaraSaaS $larasaas)
    {
        $larasaas->archivePlan($plan);

        return back();
    }
}
