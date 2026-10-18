<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LaraSaaS\LaraSaaS;

/**
 * A global broadcast: colored typing, optional CTA, scheduling window,
 * audience targeting and per-user dismissible state.
 */
class Announcement extends Model
{
    protected $table = 'larasaas_announcements';

    protected $guarded = ['id'];

    protected $casts = [
        'dismissible' => 'boolean',
        'active'      => 'boolean',
        'audience'    => 'array',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
    ];

    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class, 'announcement_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeVisibleTo(Builder $query, $user): Builder
    {
        $workspaceIds = $this->workspaceIdsFor($user);

        return $query
            ->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(function (Builder $q) use ($user, $workspaceIds) {
                $q->whereNull('audience');

                if ($user?->email) {
                    $q->orWhereJsonContains('audience->emails', $user->email);
                }

                foreach ($workspaceIds as $id) {
                    $q->orWhereJsonContains('audience->workspaces', $id);
                }
            });
    }

    public function scopeNotDismissedBy(Builder $query, $user): Builder
    {
        if (! $user) {
            return $query;
        }

        return $query->whereDoesntHave('dismissals', fn (Builder $q) => $q->where('user_id', $user->getAuthIdentifier()));
    }

    public function isDismissedBy($user): bool
    {
        return $user && $this->dismissals()->where('user_id', $user->getAuthIdentifier())->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function color(): string
    {
        return $this->color ?: app(LaraSaaS::class)->announcementType($this->type)['color'];
    }

    public function icon(): string
    {
        return $this->icon ?: app(LaraSaaS::class)->announcementType($this->type)['icon'];
    }

    public function isLive(): bool
    {
        $now = now();

        return $this->active
            && (! $this->starts_at || $this->starts_at->lte($now))
            && (! $this->ends_at || $this->ends_at->gte($now));
    }

    /** Shape consumed by the React <AnnouncementStack />. */
    public function toUiArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'body'        => $this->body,
            'type'        => $this->type,
            'color'       => $this->color(),
            'icon'        => $this->icon(),
            'dismissible' => (bool) $this->dismissible,
            'active'      => (bool) $this->active,
            'live'        => $this->isLive(),
            'action'      => $this->action_label && $this->action_url
                ? ['label' => $this->action_label, 'url' => $this->action_url]
                : null,
            'starts_at'   => $this->starts_at?->toIso8601String(),
            'ends_at'     => $this->ends_at?->toIso8601String(),
        ];
    }

    protected function workspaceIdsFor($user): array
    {
        if (! $user) {
            return [];
        }

        $resolver = config('larasaas.billing.workspace_resolver');

        if (is_callable($resolver) && ($workspace = $resolver($user))) {
            return [$workspace->getKey()];
        }

        if (method_exists($user, 'currentWorkspace') && ($workspace = $user->currentWorkspace())) {
            return [$workspace->getKey()];
        }

        return [];
    }
}
