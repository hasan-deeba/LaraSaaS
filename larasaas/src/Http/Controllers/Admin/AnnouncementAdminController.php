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
        ]);
    }

    public function store(Request $request): Response
    {
        Announcement::create($this->validated($request));

        return back()->with('larasaas.notice', __('larasaas::larasaas.announcements.created'));
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
