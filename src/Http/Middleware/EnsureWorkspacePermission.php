<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaraSaaS\Support\WorkspaceRole;

/**
 * Role-based authorization inside the workspace of the current request:
 *
 *  - `larasaas.workspace.can:members.invite,members.update` passes when the
 *    member's role holds ANY of the listed granular permissions.
 *  - `larasaas.workspace.role:admin` (same middleware) passes for roles at or
 *    above each listed role (Owner beats Admin beats Member).
 */
class EnsureWorkspacePermission
{
    public function handle(Request $request, Closure $next, string ...$abilities)
    {
        $workspace = $request->route('workspace');
        $user = $request->user();

        if ($workspace === null || $user === null) {
            abort(403, 'A workspace context is required.');
        }

        $role = $workspace->roleFor($user);

        if ($role === null) {
            abort(403, 'You are not a member of this workspace.');
        }

        $allowed = false;

        foreach ($abilities as $ability) {
            $required = WorkspaceRole::tryFromLoose(
                str_starts_with($ability, 'role:') ? substr($ability, 5) : $ability
            );

            if ($required !== null) {
                $allowed = $allowed || $role->isAtLeast($required);
            } else {
                $allowed = $allowed || $role->allows($ability);
            }
        }

        if (! $allowed) {
            abort(403, 'Your workspace role (' . $role->label() . ') cannot perform this action.');
        }

        return $next($request);
    }
}
