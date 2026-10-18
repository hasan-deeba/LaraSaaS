<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaraSaaS\LaraSaaS;
use Laravel\Pennant\Feature;

class FeatureFlagController extends Controller
{
    /**
     * Every config-defined flag, evaluated against the current user
     * (and current workspace when the host provides one).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $scope = $user && method_exists($user, 'currentWorkspace') && $user->currentWorkspace()
            ? [$user, $user->currentWorkspace()]
            : $user;

        $flags = collect(app(LaraSaaS::class)->featureDefinitions())
            ->mapWithKeys(fn ($definition, $name) => [$name => Feature::for($scope)->active($name)]);

        return response()->json(['data' => $flags]);
    }
}
