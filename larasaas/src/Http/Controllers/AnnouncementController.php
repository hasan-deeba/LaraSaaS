<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaraSaaS\Models\Announcement;

class AnnouncementController extends Controller
{
    /**
     * Active, in-window, audience-matching announcements the current user
     * has not dismissed — exactly what <AnnouncementStack /> renders.
     */
    public function index(Request $request): JsonResponse
    {
        $announcements = Announcement::visibleTo($request->user())
            ->notDismissedBy($request->user())
            ->latest('starts_at')
            ->limit(10)
            ->get()
            ->map(fn (Announcement $announcement) => $announcement->toUiArray());

        return response()->json(['data' => $announcements]);
    }

    /**
     * Persist the dismissal — the banner stays gone across sessions/devices.
     */
    public function dismiss(Request $request, Announcement $announcement): JsonResponse
    {
        abort_if(! $announcement->dismissible, 403);

        $announcement->dismissals()->firstOrCreate(
            ['user_id' => $request->user()->getAuthIdentifier()],
        );

        return response()->json(['dismissed' => true]);
    }
}
