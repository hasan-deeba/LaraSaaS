<p align="center">
  <h1 align="center">LaraSaaS</h1>
  <p align="center"><strong>The drop-in SaaS engine for Laravel + React.</strong></p>
  <p align="center">Stripe billing · dynamic pricing · announcements · feature flags — plugged into <em>your</em> User model, your auth, your theme.</p>
</p>

---

LaraSaaS is a **composer package** (not a fork-the-repo starter kit). It adds everything a SaaS needs to an **existing** Laravel application without touching your authentication, your models or your design system:

- **Your auth stays yours** — login/registration live in your project. LaraSaaS resolves the user model from `config('larasaas.user_model')` (`App\Models\User` by default) and never ships its own.
- **Stripe Billing via Laravel Cashier** — subscription provisioning through Stripe Checkout, plan swaps with proration, cancel/resume, invoices, and the secure customer portal. Bill the **user** or a **workspace**.
- **Config-driven pricing table** — every card, badge, CTA, interval and currency comes from `config/larasaas.php` and renders as a polished React `<PricingTable />` that follows your dark/light theme.
- **Announcements system** — global broadcasts with colored typings, scheduling windows, audience targeting, CTAs and per-user dismissible persistence.
- **Feature flags via Laravel Pennant** — database-driven toggles with percentage rollouts, workspace targeting and e-mail targeting, all declared in config.
- **Theme & i18n native** — components read your CSS variables, follow your `.dark` class automatically, ship EN/FR/AR translations and are fully RTL-aware.

## Requirements

- PHP 8.2+, Laravel 10/11/12
- An existing auth setup with a React/Inertia frontend — e.g. `laravel new app --breeze --react`
- Stripe account (test keys are fine)

## Installation

```bash
composer require larasaas/larasaas
php artisan larasaas:install
```

The install command publishes the config, migrations, translations and the React components, publishes Cashier's migrations, and (optionally) runs the migrations.

Add your Stripe credentials to `.env`:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

STRIPE_PRICE_STARTER_MONTHLY=price_...
STRIPE_PRICE_STARTER_YEARLY=price_...
STRIPE_PRICE_GROWTH_MONTHLY=price_...
STRIPE_PRICE_GROWTH_YEARLY=price_...
STRIPE_PRICE_SCALE_MONTHLY=price_...
STRIPE_PRICE_SCALE_YEARLY=price_...
```

Two one-time touches in your React entry (`resources/js/app.tsx`):

```tsx
// 1. Let Inertia resolve the published pages
resolve: (name) =>
    resolvePageComponent(
        `./${name}.tsx`,
        import.meta.glob(['./**/*.tsx'], { eager: true }),
        // LaraSaaS pages were published to resources/js/LaraSaaS — nothing else to do
    ),
```

```css
/* 2. resources/css/app.css — after your tailwind import */
@import '../js/LaraSaaS/larasaas.css';
```

Visit `/app/pricing` — you're a SaaS. The URL prefix, middleware, route names and even the domain are configurable under the `route` key.

### Stripe webhook

1. Add a webhook in the Stripe dashboard pointing to `https://your-app.test/larasaas/webhooks/stripe` (events: `customer.subscription.*`, `invoice.payment_succeeded`).
2. Exclude the path from CSRF verification:

```php
// Laravel 11/12 — bootstrap/app.php
->withMiddleware(fn ($middleware) => $middleware->validateCsrfTokens(except: [
    'larasaas/webhooks/stripe',
]));
```

3. Listen to `LaraSaaS\Events\SubscriptionChanged` to react on lifecycle events (seat limits, e-mails, cache warm-up, …).

## Configuration highlights

Everything lives in `config/larasaas.php`:

```php
// Which model represents your users (login/register stay in YOUR app)
'user_model' => App\Models\User::class,
// 'user_model' => App\Models\Member::class, // anything Eloquent

// Bill the user or a workspace per-subscription
'billing' => [
    'billable' => 'workspace',
    'workspace_model' => App\Models\Workspace::class,
    'workspace_resolver' => fn ($user) => $user->currentWorkspace(),
],

// The whole pricing table is data
'pricing' => [
    'default_currency' => 'usd',
    'currencies' => ['usd' => '$', 'eur' => '€', 'sar' => 'ر.س'],
    'plans' => [
        'growth' => [
            'name' => 'Growth',
            'highlight' => true,
            'badge' => 'Most popular',
            'trial_days' => 14,
            'monthly' => ['price' => 29, 'stripe_id' => env('STRIPE_PRICE_GROWTH_MONTHLY')],
            'yearly'  => ['price' => 290, 'stripe_id' => env('STRIPE_PRICE_GROWTH_YEARLY')],
            'features' => ['5 workspaces', 'Priority support', '…'],
        ],
        // add / remove plans freely — the UI follows
    ],
],

// Announcement typings → colors + icons consumed by the React stack
'announcements' => ['types' => ['info' => ['color' => '#3b82f6'], /* … */]],

// Pennant flags, resolved from config
'features' => [
    'ai-assistant'  => ['enabled' => false],
    'new-dashboard' => ['percentage' => 50],
    'beta-reports'  => ['workspaces' => [1, 4, 9]],
    'concierge'     => ['emails' => ['vip@yourapp.test']],
    'labs'          => ['resolve' => fn ($scope) => $scope?->isFounder()],
],
```

## Using the pieces

### Gate routes behind a subscription

```php
Route::middleware('larasaas.subscribed')->group(/* … */);
Route::get('/reports', ReportController::class)->middleware('larasaas.subscribed:price_123');
```

### Gate code/UI behind a flag

```php
use Laravel\Pennant\Feature;

if (Feature::for($request->user())->active('ai-assistant')) { /* … */ }
```

```tsx
<LaraSaaSFeaturesProvider>
    <FeatureGate flag="ai-assistant" fallback={<UpgradeCard />}>
        <AssistantPanel />
    </FeatureGate>
</LaraSaaSFeaturesProvider>
```

### Mount announcements anywhere

```tsx
import AnnouncementStack from './LaraSaaS/components/AnnouncementStack';

<AnnouncementStack variant="floating" />  {/* or "inline" */}
```

## Theming & i18n

- Tokens (`--ls-*`) are defined for **light and dark** and switch with your existing `.dark` class (or `prefers-color-scheme` when no class is present). No extra wiring.
- Set your brand color once: `'theme' => ['accent' => '#7c3aed']` — or override `--larasaas-accent` in your CSS.
- Components follow the host locale automatically (`locale => null`) and are RTL-aware. Publish translations with `--tag=larasaas-lang` and translate away.

## Developing the package locally

```json
// host composer.json
"repositories": [{ "type": "path", "url": "./packages/larasaas", "options": { "symlink": true } }]
```

```bash
composer require larasaas/larasaas @dev
```

## License

MIT — see [LICENSE](./LICENSE).
