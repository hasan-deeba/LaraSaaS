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
- **Three integration modes** — Laravel + React in one project, a headless JSON API for separate SPAs/Flutter apps, or embeddable pages via `<iframe>`/WebView. Pick yours in config; switch any time. See [Which integration is right for you?](#which-integration-is-right-for-you)

## Requirements

- **PHP 8.2+** (8.2–8.5), with the **`bcmath`** extension enabled — it is required by Laravel Cashier's `moneyphp/money` dependency. Verify with `php -m | grep bcmath`; on Debian/Ubuntu install it with `sudo apt install php-bcmath` (see [Troubleshooting](#troubleshooting) if composer complains about it).
- **Laravel 10, 11 or 12** — Composer picks the matching Cashier automatically (`laravel/cashier` 15.x for Laravel 10/11, 16.x for Laravel 12).
- An existing auth setup — any Laravel auth works. A **React/Inertia** frontend (e.g. `laravel new app --breeze --react`) is only required for the `inertia` mode; the `api` and `embed` modes have no frontend build requirements at all.
- A **Stripe** account (test keys are fine)

## Which integration is right for you?

LaraSaaS adapts to how your product is built — you don't rebuild anything to use it. Pick the situation that matches yours:

| # | Your setup | Mode | What you do |
|---|-----------|------|-------------|
| **1** | **Laravel + React in the same project** (Inertia — e.g. Breeze with React) | `inertia` (default) | Install → publish → use the shipped pages/components **directly**. Nothing else to build. |
| **2** | **React on another server** (SPA talking to your Laravel API) **or Flutter / iOS / Android** | `api` | Use **only the JSON API** and design **your own tables & pricing UI** on the client. |
| **3** | Same as 2, but you'd rather **not** rebuild the UI | `embed` | Drop the package's standalone pricing/billing pages in with an **`<iframe>`** (or a mobile **WebView**). |

Choose the mode in `.env`:

```env
LARASAAS_INTEGRATION_MODE=inertia   # inertia | api | embed | all | custom
```

or in `config/larasaas.php` under `integration.mode`. **You can change it at any time** — it only decides which routes the package registers, so switching is safe and reversible. `all` enables every surface at once (handy while migrating); `custom` registers nothing but the Stripe webhook so you can wire your own routes — see [Switching modes & custom integration](#switching-modes--custom-integration).

The mode is also asked interactively by `php artisan larasaas:install` (or passed with `--mode=`), and written to your `.env` automatically.

---

### Mode 1 — Laravel + React in the same project → `inertia`

This is the classic setup (e.g. `laravel new app --breeze --react`): one codebase, one deploy. Install the package, publish the React pages/components and use them directly — `/app/pricing`, `/app/billing`, the `<AnnouncementStack />`, `<FeatureGate />`, `<PricingTable />` and the admin panels all work out of the box.

Follow the standard [Installation](#installation) below — there is nothing extra to configure.

### Mode 2 — Headless APIs (separate React SPA, Flutter, native) → `api`

When your frontend lives on another server — or is a **Flutter / native** app — you usually want **APIs only**, and you design your own tables & pricing screens in your own design system. Set `LARASAAS_INTEGRATION_MODE=api` and LaraSaaS serves exactly that: a JSON API (plus the Stripe webhook). No LaraSaaS views, no React publishing.

Every plan in `config/larasaas.php` is returned as data, so your pricing table is 100% yours — the API gives you the complete matrix to render.

#### Endpoints

All endpoints live under `/api/larasaas` (configurable via `route.api_prefix`):

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/pricing` | The full pricing matrix (plans, prices, intervals, currencies, trial days, contact-sales…) — everything your own pricing table needs |
| `GET` | `/subscription` | Current subscription state (`subscribed`, `on_trial`, `on_grace_period`, `ends_at`, `plan`, …) |
| `GET` | `/invoices` | Recent invoices (id, date, total, status, PDF URL) |
| `POST` | `/billing/subscribe/{plan}` | Start checkout → returns the Stripe Checkout `url` to open (or reports a `swap` if already subscribed). Body: `interval`, optional `success_url` / `cancel_url` |
| `POST` | `/billing/swap/{plan}` | Switch plan/interval (prorated). Body: `interval` |
| `POST` | `/billing/cancel` | Cancel at period end |
| `POST` | `/billing/resume` | Resume a canceled subscription |
| `POST` | `/billing/portal` | Returns the Stripe customer-portal `url` to open. Body: optional `success_url` |
| `GET` | `/announcements` · `/features` | The announcement & feature-flag JSON your app can consume anywhere |

#### Examples

```bash
# The plan matrix for your own pricing table
curl -s https://api.yourapp.test/api/larasaas/pricing \
     -H "Authorization: Bearer {token}"
```

```ts
// React SPA on another server
const { data: pricing } = await fetch('https://api.yourapp.test/api/larasaas/pricing', {
    headers: { Authorization: `Bearer ${token}` },
}).then(r => r.json());

// …render your own table from pricing.plans, then:
const checkout = await fetch('https://api.yourapp.test/api/larasaas/billing/subscribe/growth', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: JSON.stringify({ interval: 'monthly' }),
}).then(r => r.json());

window.location.href = checkout.url; // send the user to Stripe Checkout
```

```dart
// Flutter
final res = await http.get(
  Uri.parse('https://api.yourapp.test/api/larasaas/pricing'),
  headers: {'Authorization': 'Bearer $token'},
);
final plans = jsonDecode(res.body)['data']['plans']; // build your own pricing widgets
```

#### Auth & CORS

- `route.api_middleware` decides how API requests authenticate. For cross-server clients, point it at token auth, e.g. `'api_middleware' => ['api', 'auth:sanctum']`, and issue tokens (Sanctum, Passport, …) from your own login endpoint. Same-origin clients can keep the default `['web', 'auth']` session auth.
- Browser SPAs on a different origin need CORS: set `integration.api.cors_origins` (env `LARASAAS_API_CORS_ORIGINS=https://app.example.com`). Flutter/native clients don't need CORS at all.

### Mode 3 — Embed the UI with `<iframe>` / WebView → `embed`

If you don't want to rebuild the pricing/billing UI for your separate React SPA or Flutter app, let the **package** serve it and embed it — like a Stripe pricing table. Set `LARASAAS_INTEGRATION_MODE=embed` and LaraSaaS registers standalone pages (`/embed/pricing`, `/embed/billing`) that are **server-rendered, self-contained and need no Node/Vite build** on the Laravel side.

#### Signed embed URLs — no shared sessions

Cross-server embedding can't rely on cookies. Generate a **signed, expiring URL** per user on your Laravel side and point the frame at it:

```php
use LaraSaaS\Facades\LaraSaaS;

$src = LaraSaaS::embedUrl($user, 'pricing');          // or 'billing'
// → https://your-app.test/embed/pricing?user=42&expires=…&signature=…
```

The signature is HMAC'd with your app key and expires (default 60 minutes, tune `integration.embed.token_ttl`), so the parent app never leaks its session to the frame and links can't be forged or tampered with.

```html
<!-- Your React SPA (any server) -->
<iframe src="{{ $embedUrl }}" width="100%" height="720" style="border:0"></iframe>
```

```dart
// Flutter
WebView(initialUrl: embedUrl); // embedUrl from your API
```

Checkout and the Stripe customer portal automatically open in the **top-level window** (never trapped inside the frame) — forms post with `target="_top"`.

#### Controlling who may frame the pages

```php
// config/larasaas.php
'integration' => [
    'embed' => [
        'frame_ancestors' => ['https://app.example.com'], // CSP frame-ancestors; ['*'] = any parent
        'token_ttl'       => 60,                          // minutes
    ],
],
```

The package sends `Content-Security-Policy: frame-ancestors …` on the embed pages. Make sure your hosting/CDN doesn't inject `X-Frame-Options: DENY` on top of it. (Also available as env `LARASAAS_EMBED_FRAME_ANCESTORS`.)

### Switching modes & custom integration

- **`all`** — registers the Inertia, API and embed surfaces at once. Great while you migrate from one architecture to another, or when some screens are embedded and others are native.
- **`custom`** — registers *only* the Stripe webhook. Build whatever you want on top of the `LaraSaaS` manager / facade — your routes, your views, your mobile client:

```php
use LaraSaaS\Facades\LaraSaaS;

Route::get('/my-pricing', function () {
    return MyCoolPricingTable::make(LaraSaaS::pricingForUi());
});

LaraSaaS::billable($request)?->newSubscription('default', $priceId)->checkout([/* … */]);
LaraSaaS::subscriptionForUi($request);   // status for your UI
LaraSaaS::embedUrl($user, 'billing');    // even in custom mode, if you also enable `embed`
```

The manager works regardless of mode — the mode only controls which ready-made surfaces are registered. Change `LARASAAS_INTEGRATION_MODE` (or `integration.mode`) whenever you want; nothing is baked in at install time.

## Installation

```bash
composer require hasan-deeba/larasaas:^1.0
php artisan larasaas:install            # asks which integration mode you need
# php artisan larasaas:install --mode=api    # or pick it up front:
# php artisan larasaas:install --mode=embed
# php artisan larasaas:install --mode=inertia
```

LaraSaaS follows semantic versioning: pin the **major** version in your project (`^1.0` for the 1.x line) instead of `*`, so `composer update` only ever brings you non-breaking minor/patch releases. If you deliberately want the newest `main` build, install with `hasan-deeba/larasaas:@dev`.

The install command asks for your [integration mode](#which-integration-is-right-for-you) and writes it to `.env` as `LARASAAS_INTEGRATION_MODE`, then publishes the config, migrations and translations (plus the React components in `inertia`/`all` mode), publishes Cashier's migrations, and (optionally) runs the migrations.

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

Two one-time touches in your React entry (`resources/js/app.tsx`) — **only for `inertia` / `all` mode** (the `api` and `embed` modes don't publish React components at all):

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

Visit `/app/pricing` (or `/embed/pricing`, or hit `/api/larasaas/pricing` — depending on your mode) — you're a SaaS. The URL prefix, middleware, route names and even the domain are configurable under the `route` key.

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
// How your frontend consumes LaraSaaS — switch any time
// (inertia = Laravel+React same project | api = headless SPA/Flutter |
//  embed = iframe/WebView pages | all | custom = your own routes)
'integration' => [
    'mode' => env('LARASAAS_INTEGRATION_MODE', 'inertia'),
    'api'   => ['cors_origins' => []],                   // e.g. ['https://app.example.com']
    'embed' => ['frame_ancestors' => ['*'], 'token_ttl' => 60],
],

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
resources/views/       Inertia fallback root view + standalone embed pages
demo/                  interactive showcase site (Next.js) — DEV ONLY,
                       export-ignored via .gitattributes, never shipped
tests/                 Pest suite
```

Consumers installing through Composer receive only `composer.json`, `config/`, `src/`, `routes/`, `database/`, `resources/`, `README.md`, `CHANGELOG.md` and `LICENSE`. Everything development-facing (demo site, tests, CI) is stripped from dist archives by `.gitattributes`.

## License

MIT — see [LICENSE](./LICENSE).
