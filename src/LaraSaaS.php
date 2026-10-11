<?php

namespace LaraSaaS;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;

/**
 * The central LaraSaaS manager — resolves the host application's models,
 * shapes config-driven plans/announcement typings for the React UI, and
 * knows who the "billable" entity is.
 */
class LaraSaaS
{
    protected ?string $userModel = null;

    /** Override the user model at runtime (useful in tests / multi-tenant apps). */
    public function useUserModel(string $model): static
    {
        $this->userModel = $model;

        return $this;
    }

    public function userModel(): string
    {
        return $this->userModel ?? config('larasaas.user_model', 'App\\Models\\User');
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    public function usersQuery()
    {
        return $this->newUserModel()->newQuery();
    }

    public function newUserModel(): Model
    {
        $class = $this->userModel();

        return new $class;
    }

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    */

    public function subscriptionName(): string
    {
        return config('larasaas.billing.subscription', 'default');
    }

    /**
     * Resolve the entity Cashier bills for this request — the authenticated
     * user by default, or the resolved workspace when configured.
     */
    public function billable(Request $request): ?Model
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if (config('larasaas.billing.billable', 'user') === 'workspace') {
            $resolver = config('larasaas.billing.workspace_resolver');

            if (is_callable($resolver)) {
                return $resolver($user);
            }

            if (method_exists($user, 'currentWorkspace')) {
                return $user->currentWorkspace();
            }
        }

        return $user;
    }

    public function billableType(): string
    {
        if (config('larasaas.billing.billable') === 'workspace' && config('larasaas.billing.workspace_model')) {
            return (string) config('larasaas.billing.workspace_model');
        }

        return $this->userModel();
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    public function plans(): array
    {
        return config('larasaas.pricing.plans', []);
    }

    public function plan(?string $key): ?array
    {
        return $key === null ? null : Arr::get($this->plans(), $key);
    }

    public function planKeys(): array
    {
        return array_keys($this->plans());
    }

    /**
     * Serialize the whole pricing matrix exactly how the React
     * <PricingTable /> wants to consume it.
     */
    public function pricingForUi(): array
    {
        $pricing = config('larasaas.pricing', []);

        return [
            'currency'            => $pricing['default_currency'] ?? 'usd',
            'currencies'          => $pricing['currencies'] ?? ['usd' => '$'],
            'intervals'           => $pricing['intervals'] ?? ['monthly', 'yearly'],
            'default_interval'    => $pricing['default_interval'] ?? 'monthly',
            'yearly_savings_badge'=> $pricing['yearly_savings_badge'] ?? null,
            'contact_sales'       => $pricing['contact_sales'] ?? null,
            'trial_days'          => config('larasaas.billing.trial_days'),
            'plans'               => $this->plans(),
        ];
    }

    /**
     * Current subscription state for the React billing page.
     */
    public function subscriptionForUi(Request $request): array
    {
        $billable = $this->billable($request);

        if (! $billable) {
            return ['subscribed' => false];
        }

        $name = $this->subscriptionName();
        $subscription = $billable->subscription($name);

        $onGrace = (bool) ($subscription?->onGracePeriod());

        return [
            'subscribed'       => $billable->subscribed($name),
            'on_grace_period'  => $onGrace,
            'on_trial'         => (bool) ($subscription?->onTrial()),
            'ends_at'          => $subscription?->ends_at?->toDateString(),
            'trial_ends_at'    => $subscription?->trial_ends_at?->toDateString(),
            'stripe_status'    => $subscription?->stripe_status,
            'current_price_id' => $subscription?->stripe_price,
            'plan'             => $subscription ? $this->planKeyForPrice($subscription->stripe_price) : null,
        ];
    }

    public function planKeyForPrice(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        foreach ($this->plans() as $key => $plan) {
            foreach (['monthly', 'yearly'] as $interval) {
                if (($plan[$interval]['stripe_id'] ?? null) === $priceId) {
                    return $key;
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Announcements
    |--------------------------------------------------------------------------
    */

    public function announcementTypes(): array
    {
        return config('larasaas.announcements.types', []);
    }

    public function announcementType(string $type): array
    {
        return $this->announcementTypes()[$type] ?? ['color' => '#3b82f6', 'icon' => 'info'];
    }

    /*
    |--------------------------------------------------------------------------
    | Integration mode
    |--------------------------------------------------------------------------
    */

    /** The configured integration mode: inertia | api | embed | all | custom. */
    public function integrationMode(): string
    {
        $mode = config('larasaas.integration.mode', 'inertia');

        return in_array($mode, ['inertia', 'api', 'embed', 'all', 'custom'], true) ? $mode : 'inertia';
    }

    /** Laravel + React/Inertia in the same project (the published pages). */
    public function servesInertia(): bool
    {
        return in_array($this->integrationMode(), ['inertia', 'all'], true);
    }

    /** JSON API for headless clients (separate SPA, Flutter, native…). */
    public function servesApi(): bool
    {
        return $this->integrationMode() !== 'custom';
    }

    /** Standalone embeddable pages for <iframe> / mobile WebView. */
    public function servesEmbed(): bool
    {
        return in_array($this->integrationMode(), ['embed', 'all'], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Embed surface (iframe / WebView)
    |--------------------------------------------------------------------------
    */

    public function embedRouteName(string $page): string
    {
        return $this->routeName('embed.') . $page;
    }

    /**
     * Signed, expiring URL for one of the embed pages ("pricing" | "billing"),
     * already authenticated for the given user — no shared session required.
     * Hand this URL to your <iframe> / WebView and LaraSaaS does the rest.
     */
    public function embedUrl($user, string $page = 'pricing', array $params = [], ?int $minutes = null): string
    {
        $ttl = $minutes ?? (int) config('larasaas.integration.embed.token_ttl', 60);

        return URL::temporarySignedRoute(
            $this->embedRouteName($page),
            now()->addMinutes(max(1, $ttl)),
            array_merge(['user' => $user->getAuthIdentifier()], $params),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Flags / admin / misc
    |--------------------------------------------------------------------------
    */

    public function featureDefinitions(): array
    {
        return config('larasaas.features', []);
    }

    public function isAdmin(Authenticatable $user): bool
    {
        $emails = config('larasaas.admin_emails', []);

        if (! empty($emails)) {
            return in_array($user->email, $emails, true);
        }

        if (isset($user->larasaas_admin)) {
            return (bool) $user->larasaas_admin;
        }

        return false;
    }

    public function routeName(string $name): string
    {
        return config('larasaas.route.name_prefix', 'larasaas.') . $name;
    }

    public function apiBase(): string
    {
        return '/' . ltrim(config('larasaas.route.api_prefix', 'api/larasaas'), '/');
    }

    public function accentColor(): string
    {
        return config('larasaas.theme.accent', '#ff2d20');
    }

    public function page(string $name): string
    {
        return trim(config('larasaas.inertia.pages_dir', 'LaraSaaS'), '/') . '/' . $name;
    }
}
