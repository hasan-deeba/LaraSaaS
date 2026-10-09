<?php

namespace LaraSaaS\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LaraSaaS\LaraSaaS;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlagAdminController extends Controller
{
    public function __construct(protected LaraSaaS $larasaas) {}

    public function index(): \Inertia\Response
    {
        return Inertia::render($this->larasaas->page('Admin/Features'), [
            'definitions' => $this->larasaas->featureDefinitions(),
            'strategies'  => ['enabled', 'percentage', 'workspaces', 'emails', 'resolve'],
        ]);
    }

    /**
     * Activate a flag globally or for a specific workspace.
     */
    public function activate(Request $request, string $flag): Response
    {
        $this->authorizeFlag($flag);
        $this->set($request, $flag, active: true);

        return back()->with('larasaas.notice', __('larasaas::larasaas.flags.activated', ['flag' => $flag]));
    }

    public function deactivate(Request $request, string $flag): Response
    {
        $this->authorizeFlag($flag);
        $this->set($request, $flag, active: false);

        return back()->with('larasaas.notice', __('larasaas::larasaas.flags.deactivated', ['flag' => $flag]));
    }

    protected function authorizeFlag(string $flag): void
    {
        abort_unless(array_key_exists($flag, $this->larasaas->featureDefinitions()), 404);
    }

    protected function set(Request $request, string $flag, bool $active): void
    {
        $validated = $request->validate([
            'scope_type' => ['nullable', 'in:everyone,workspace,user'],
            'scope_id'   => ['nullable', 'string'],
        ]);

        $scopeType = $validated['scope_type'] ?? 'everyone';
        $scopeId   = $validated['scope_id'] ?? null;

        if ($scopeType === 'everyone') {
            $active ? Feature::activateForEveryone($flag) : Feature::deactivateForEveryone($flag);

            return;
        }

        $model = $scopeType === 'workspace'
            ? config('larasaas.billing.workspace_model')
            : $this->larasaas->userModel();

        abort_unless($model && $scopeId, 422);

        $scope = $model::query()->findOrFail($scopeId);

        $active
            ? Feature::for($scope)->activate($flag)
            : Feature::for($scope)->deactivate($flag);
    }
}
