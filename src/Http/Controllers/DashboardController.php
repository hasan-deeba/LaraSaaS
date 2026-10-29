<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Announcement;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var LaraSaaS $larasaas */
        $larasaas = app(LaraSaaS::class);
        $user = $request->user();

        return Inertia::render($larasaas->page('Dashboard'), [
            'greeting' => __('larasaas::larasaas.dashboard.greeting', ['name' => $user?->name ?? '']),
            'stats'    => [
                'subscription'    => $larasaas->subscriptionForUi($request),
                'announcements'   => Announcement::visibleTo($user)->count(),
                'plan'            => $larasaas->subscriptionForUi($request)['plan'] ?? null,
            ],
        ]);
    }
}
