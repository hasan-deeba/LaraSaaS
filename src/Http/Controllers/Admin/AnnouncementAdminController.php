<?php

namespace LaraSaaS\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Announcement;
use Symfony\Component\HttpFoundation\Response;

class AnnouncementAdminController extends Controller
{
    public function __construct(protected LaraSaaS $larasaas) {}

    public function index(): \Inertia\Response
    {
        return Inertia::render($this->larasaas->page('Admin/Announcements'), [
            'announcements' => Announcement::latest()->get()->map->toUiArray(),
            'types'         => $this->larasaas->announcementTypes(),
            'notice'        => session('larasaas.notice'),
        ]);
    }

    public function store(Request $request): Response
    {
        Announcement::create($this->validated($request));

        return back(303)->with('larasaas.notice', __('larasaas::larasaas.announcements.created'));
    }

    public function update(Request $request, Announcement $announcement): Response
    {
        $announcement->update($this->validated($request, partial: true));

        return back()->with('larasaas.notice', __('larasaas::larasaas.announcements.updated'));
    }

    public function destroy(Announcement $announcement): Response
    {
        $announcement->delete();

        return back()->with('larasaas.notice', __('larasaas::larasaas.announcements.deleted'));
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        // Optional form controls submit empty strings. Don't rely on the host
        // app's ConvertEmptyStringsToNull middleware for validation to accept them.
        foreach (['color', 'icon', 'action_label', 'action_url', 'starts_at', 'ends_at'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && trim($value) === '') {
                $request->merge([$field => null]);
            }
        }

        $types = array_keys($this->larasaas->announcementTypes());
        $rule  = fn (string $inner) => $partial ? "sometimes|$inner" : $inner;

        return $request->validate([
            'title'        => [$rule('required'), 'string', 'max:140'],
            'body'         => [$rule('required'), 'string', 'max:2000'],
            'type'         => [$rule('required'), Rule::in($types)],
            'color'        => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'icon'         => ['nullable', 'string', 'max:60'],
            'action_label' => ['nullable', 'string', 'max:60'],
            'action_url'   => ['nullable', 'url', 'max:255'],
            'dismissible'  => ['sometimes', 'boolean'],
            'active'       => ['sometimes', 'boolean'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date', 'after_or_equal:starts_at'],
            'audience'     => ['nullable', 'array'],
            'audience.emails'      => ['nullable', 'array'],
            'audience.workspaces'  => ['nullable', 'array'],
        ]);
    }
}
