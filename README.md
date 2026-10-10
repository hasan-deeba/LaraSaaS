<p align="center">
  <h1 align="center">LaraSaaS</h1>
  <p align="center"><strong>The drop-in SaaS engine for Laravel + React.</strong></p>
  <p align="center">Stripe billing · dynamic pricing · announcements · feature flags — plugged into <em>your</em> User model, your auth, your theme.</p>
  <p align="center">
    <a href="https://packagist.org/packages/hasan-deeba/larasaas"><img src="https://img.shields.io/packagist/v/hasan-deeba/larasaas.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://packagist.org/packages/hasan-deeba/larasaas"><img src="https://img.shields.io/packagist/dt/hasan-deeba/larasaas.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/hasan-deeba/larasaas"><img src="https://img.shields.io/packagist/php-v/hasan-deeba/larasaas.svg?style=flat-square" alt="PHP Version"></a>
    <a href="https://github.com/hasan-deeba/LaraSaaS/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square" alt="License"></a>
  </p>
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

- **PHP 8.2+** (8.2–8.5), with the **`bcmath`** extension enabled — it is required by Laravel Cashier's `moneyphp/money` dependency. Verify with `php -m | grep bcmath`; on Debian/Ubuntu install it with `sudo apt install php-bcmath` (see [Troubleshooting](#troubleshooting) if composer complains about it).
- **Laravel 10, 11 or 12** — Composer picks the matching Cashier automatically (`laravel/cashier` 15.x for Laravel 10/11, 16.x for Laravel 12).
- An existing auth setup with a **React/Inertia** frontend — e.g. `laravel new app --breeze --react`
- A **Stripe** account (test keys are fine)

## Installation

```bash
composer require hasan-deeba/larasaas:^1.0
php artisan larasaas:install
```

LaraSaaS follows semantic versioning: pin the **major** version in your project (`^1.0` for the 1.x line) instead of `*`, so `composer update` only ever brings you non-breaking minor/patch releases. If you deliberately want the newest `main` build, install with `hasan-deeba/larasaas:@dev`.

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

### Troubleshooting

**`ext-bcmath` missing**

> `moneyphp/money … require ext-bcmath * -> it is missing from your system. Install or enable PHP's bcmath extension.`

Cashier (via its `moneyphp/money` dependency) needs the `bcmath` PHP extension. Enable it for the exact PHP version Composer runs on, then re-run `composer require` / `composer update`:

- **Debian/Ubuntu:** `sudo apt install php-bcmath` (or `php8.x-bcmath` for a pinned version)
- **RHEL/Fedora/Rocky:** `sudo dnf install php-bcmath`
- **macOS (Homebrew):** included with the `php` formula

Verify with `php -m | grep bcmath` — and make sure CLI and your web server use the **same** PHP version/extensions. Don't work around it with `--ignore-platform-req=ext-bcmath`: billing math will fatal at runtime.

**PHP 8.5 and `moneyphp/money` version conflicts**

Money v4.8+ supports PHP 8.5. If composer lists "your php version (8.5.x) does not satisfy that requirement" only for old money 4.x releases, the real blocker is usually the missing `bcmath` extension above — enable it first. If a dependency genuinely caps at PHP ≤ 8.4, run the app on PHP 8.2–8.4.

**Cashier/Laravel mismatch**

`laravel/cashier` 15.x requires Laravel 10/11 and 16.x requires Laravel 12. LaraSaaS accepts both, so Composer resolves the right one for your app — if you see an `illuminate/console` conflict, you simply have a Laravel 10/11 app (Cashier 15.x will be picked), nothing to fix.

**Composer only resolves `dev-main`**

Stable versions are published as git tags (e.g. `v1.0.0`). If no version matching your constraint exists yet, check the [releases page](https://github.com/hasan-deeba/LaraSaaS/releases) — or install the dev build with `@dev` until the next tag is pushed.

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
composer require hasan-deeba/larasaas @dev
```

## Repository layout (for contributors)

This repository **is** the composer package — `composer.json` sits at the root, exactly how Packagist expects it:

```
composer.json          the package manifest (auto-discovery wired)
config/                larasaas.php — every knob, documented
src/                   PHP namespace LaraSaaS (PSR-4)
routes/                web.php, api.php, webhook.php
database/migrations/   announcements + dismissals tables
resources/js/          React components & Inertia pages (published into host apps)
resources/lang/        EN / FR / AR translations
resources/views/       Inertia fallback root view
demo/                  interactive showcase site (Next.js) — DEV ONLY,
                       export-ignored via .gitattributes, never shipped
tests/                 Pest suite
```

Consumers installing through Composer receive only `composer.json`, `config/`, `src/`, `routes/`, `database/`, `resources/`, `README.md`, `CHANGELOG.md` and `LICENSE`. Everything development-facing (demo site, tests, CI) is stripped from dist archives by `.gitattributes`.

## License

MIT — see [LICENSE](./LICENSE).
