<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The authenticated user must be a member of the `{workspace}` being
 * requested — otherwise 403 (the workspace id/slug must never leak data).
 */
class EnsureWorkspaceMember
{
    public function handle(Request $request, Closure $next)
    {
        $workspace = $request->route('workspace');
        $user = $request->user();

        if ($workspace === null || $user === null || ! $workspace->hasMember($user)) {
            abort(403, 'You are not a member of this workspace.');
        }

        return $next($request);
    }
}
