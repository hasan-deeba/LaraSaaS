<p align="center">
  <h1 align="center">LaraSaaS</h1>
  <p align="center"><strong>The drop-in SaaS engine for Laravel + React.</strong></p>
  <p align="center">Stripe billing · plans & limits · teams · announcements · feature flags — plugged into <em>your</em> app, your auth and your design.</p>
  <p align="center">
    <a href="https://packagist.org/packages/hasan-deeba/larasaas"><img src="https://img.shields.io/packagist/v/hasan-deeba/larasaas.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://packagist.org/packages/hasan-deeba/larasaas"><img src="https://img.shields.io/packagist/dt/hasan-deeba/larasaas.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/hasan-deeba/larasaas"><img src="https://img.shields.io/packagist/php-v/hasan-deeba/larasaas.svg?style=flat-square" alt="PHP Version"></a>
    <a href="https://github.com/hasan-deeba/LaraSaaS/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square" alt="License"></a>
  </p>
</p>

---

LaraSaaS is a **Composer package** that adds everything a SaaS needs to an **existing** Laravel app. It never touches your login, your models or your design.

## Contents

- [Why LaraSaaS](#why-larasaas)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Choose how your frontend connects](#choose-how-your-frontend-connects)
- [Plans & limits](#plans--limits)
- [Subscriptions](#subscriptions)
- [Multi-tenant workspaces & teams](#multi-tenant-workspaces--teams)
- [Announcements](#announcements)
- [Feature flags](#feature-flags)
- [Admin panels](#admin-panels)
- [Theming & translations](#theming--translations)
- [Configuration reference](#configuration-reference)
- [Artisan commands](#artisan-commands)
- [Troubleshooting](#troubleshooting)
- [Versioning](#versioning)
- [Contributing](#contributing)

---

## Why LaraSaaS

| | |
|---|---|
| 🔐 **Your auth stays yours** | No login screens shipped. Works with Breeze, Jetstream, Fortify, Sanctum, Passport or your own. |
| 💳 **Stripe billing** | Checkout, plan swaps with proration, cancel/resume, invoices, customer portal and webhooks, all through Laravel Cashier. |
| 📦 **Plans & limits** | Create plans from a dashboard. Define features like `storage_gb` or `uploads` once and check them on the server with one line. |
| 🏢 **Multi-tenant (optional)** | Workspaces, Owner/Admin/Member roles and e-mail invitations. Bill each user or each company. |
| 📣 **Announcements** | Scheduled, colored, dismissible banners managed from an admin panel. |
| 🚩 **Feature flags** | Percentage rollouts and targeting by workspace or e-mail, built on Laravel Pennant. |
| 🔌 **Any frontend** | Laravel + React (Inertia), a JSON API for SPAs and Flutter/mobile, or embeddable `<iframe>` pages. |
| 🎨 **Fits your design** | Light/dark aware, uses your CSS variables, zero npm dependencies, EN/FR/AR translations, RTL ready. |
| ⚙️ **Everything configurable** | URLs, middleware, models, roles, guards: every setting is in one config file. |

---

## Requirements

- **PHP 8.2+** with the **`bcmath`** extension (`php -m | grep bcmath`)
- **Laravel 10, 11 or 12** (Composer picks Cashier 15 or 16 for you)
- Any **existing auth** setup
- A **Stripe** account (test keys are fine)
- **React + Inertia** only if you use the `inertia` mode (e.g. `laravel new app --breeze --react`)

---

## Quick start

From zero to a working pricing page.

### 1. Install the package

```bash
composer require hasan-deeba/larasaas:^1.0
```

### 2. Run the installer

```bash
php artisan larasaas:install
```

It asks two questions and writes the answers to `.env`:

- **Integration mode:** how your frontend talks to LaraSaaS. Not sure? Keep `inertia`. See [Choose how your frontend connects](#choose-how-your-frontend-connects).
- **Multi-tenant?** Should users belong to workspaces/companies? Not sure? Answer **no**; you can enable it later.

It then publishes the config, migrations and translations, plus the React pages in `inertia` mode.

> Skip the questions: `php artisan larasaas:install --mode=inertia --no-multi-tenant`

### 3. Add the `Billable` trait to your User model

```php
// app/Models/User.php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

**Your User model lives somewhere else** (`App\Models\AccessManagement\User`, a domain folder, …)? Point LaraSaaS at it:

```env
# Unquoted or 'single quotes' — NEVER "double quotes": dotenv reads \M as an
# escape sequence and refuses to load the whole .env file.
LARASAAS_USER_MODEL=App\Models\AccessManagement\User
```

Then clear the config cache: `php artisan config:clear`.

LaraSaaS points Cashier at that class too (`Cashier::useCustomerModel`), so Stripe webhooks resolve customers with **your** model instead of Cashier's hardcoded `App\Models\User` default. Without it, Checkout still works but `customer.subscription.*` and `invoice.*` webhooks answer 500 with `class_parents(): Class App\Models\User does not exist` — and no subscription row is ever written.

### 4. Add your Stripe keys to `.env`

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### 5. Run the migrations

```bash
php artisan migrate
```

### 6. Connect the Stripe webhook

The webhook keeps subscriptions in sync with Stripe. No CSRF exclusion is needed.

```bash
# Production: create the webhook in Stripe with all required events
php artisan cashier:webhook --url="https://yourapp.com/larasaas/webhooks/stripe"

# Local development: forward events with the Stripe CLI
stripe listen --forward-to http://localhost:8000/larasaas/webhooks/stripe
```

Copy the `whsec_...` secret it shows into `STRIPE_WEBHOOK_SECRET`.

### 7. Make yourself an admin

```env
LARASAAS_ADMIN_EMAILS=you@yourapp.com
```

### 8. Create your first plan

1. In Stripe: **Product catalog → Add product** and add a recurring monthly price. Copy its `price_...` ID.
2. In LaraSaaS: open **`/app/admin/plans`** (inertia mode), or run:

```bash
php artisan tinker
```

```php
LaraSaaS\Facades\LaraSaaS::createPlan([
    'key'     => 'starter',
    'name'    => 'Starter',
    'monthly' => ['price' => 9, 'stripe_id' => 'price_your_monthly_id'],
]);
```

### 9. Connect your frontend (inertia mode only)

Let Inertia find the LaraSaaS pages in `resources/js/app.tsx`:

```tsx
resolve: (name) => {
    const pages = import.meta.glob(['./Pages/**/*.tsx', './LaraSaaS/Pages/**/*.tsx']);

    return resolvePageComponent(
        name.startsWith('LaraSaaS/')
            ? `./LaraSaaS/Pages/${name.slice('LaraSaaS/'.length)}.tsx`
            : `./Pages/${name}.tsx`,
        pages,
    );
},
```

> Laravel 12 starter kits use a lowercase `pages` folder. Change `./Pages/` to `./pages/` for your own pages.

Then add the styles in `resources/css/app.css`:

```css
@import '../js/LaraSaaS/larasaas.css';
```

### 10. Open it 🎉

| Mode | URL |
|---|---|
| `inertia` | `/app/pricing`, `/app/billing`, `/app/dashboard` |
| `api` | `GET /api/larasaas/pricing` |
| `embed` | `/embed/pricing` |

Something not working? Run `php artisan larasaas:doctor`.

---

## Choose how your frontend connects

| Your setup | Mode | What you get |
|---|---|---|
| Laravel + React in **one project** (Inertia) | `inertia` (default) | Ready-made pages and React components |
| React SPA on **another server**, or **Flutter / iOS / Android** | `api` | A JSON API. You build your own UI |
| Same as above, but you **don't want to build the UI** | `embed` | Pricing/billing pages you place in an `<iframe>` or WebView |
| Several of the above | `all` | Every surface at once |
| Fully custom | `custom` | Only the Stripe webhook; build on the `LaraSaaS` facade |

```env
LARASAAS_INTEGRATION_MODE=inertia
```

You can switch at any time. The mode only decides which routes are registered.

### Inertia mode

Pages: `/app/pricing`, `/app/billing`, `/app/dashboard`, `/app/admin/*` and `/app/workspaces` (multi-tenant).

Use the components in your own pages:

```tsx
import AnnouncementStack from './LaraSaaS/components/AnnouncementStack';
import { FeatureGate, LaraSaaSFeaturesProvider } from './LaraSaaS/components/FeatureGate';

<AnnouncementStack variant="floating" />   {/* or "inline" */}

<LaraSaaSFeaturesProvider>
    <FeatureGate flag="ai-assistant" fallback={<UpgradeCard />}>
        <AssistantPanel />
    </FeatureGate>
</LaraSaaSFeaturesProvider>
```

### API mode

All endpoints live under `/api/larasaas`:

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/pricing` | **Public.** Every plan, price, interval and feature |
| `GET` | `/subscription` | Current subscription state |
| `GET` | `/invoices` | Recent invoices with PDF links |
| `POST` | `/billing/subscribe/{plan}` | Returns a Stripe Checkout `url`. Body: `interval` |
| `POST` | `/billing/swap/{plan}` | Change plan (prorated). Body: `interval` |
| `POST` | `/billing/cancel` | Cancel at period end |
| `POST` | `/billing/resume` | Resume a cancelled subscription |
| `POST` | `/billing/portal` | Returns the Stripe customer portal `url` |
| `GET` | `/announcements` · `/features` | Announcements and feature flags |

**Authentication.** Choose the row that matches your app in `config/larasaas.php`:

| Your auth | `route.api_middleware` | `route.public_api_middleware` | `route.pricing_guard` |
|---|---|---|---|
| Session (same project) | `['web', 'auth']` | `['web']` | `null` |
| Sanctum (SPA or mobile tokens) | `['api', 'auth:sanctum']` | `['api']` | `'sanctum'` |
| Passport | `['api', 'auth:api']` | `['api']` | `'api'` |

**CORS** is only for browser SPAs on another domain (Flutter/mobile don't need it):

```env
LARASAAS_API_CORS_ORIGINS=https://app.example.com
```

**Example:**

```ts
const { data: pricing } = await fetch('https://api.yourapp.com/api/larasaas/pricing')
    .then(r => r.json());

const { url } = await fetch('https://api.yourapp.com/api/larasaas/billing/subscribe/starter', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: JSON.stringify({ interval: 'monthly' }),
}).then(r => r.json());

window.location.href = url; // go to Stripe Checkout
```

### Embed mode

Server-rendered pages with no Node/Vite build: `/embed/pricing` and `/embed/billing`.

```php
use LaraSaaS\Facades\LaraSaaS;

$pricing = LaraSaaS::embedUrl();                            // public, no login needed
$billing = LaraSaaS::embedUrl($request->user(), 'billing'); // signed link, expires in 60 min
```

```html
<iframe src="{{ $billing }}" width="100%" height="720" style="border:0"></iframe>
```

- Generate signed links **on your server** for the logged-in user, never from a user ID sent by the client.
- Checkout and the Stripe portal open in the full window, not inside the frame.
- Existing subscribers review a Stripe invoice preview before switching plans: the page shows the estimated amount due now and the new recurring plan price. The switch takes effect immediately; any downgrade credit is applied to future invoices, not refunded to the card.
- Limit which sites may embed the pages:

```env
LARASAAS_EMBED_FRAME_ANCESTORS=https://app.example.com
```

> Embeds are for customers only. Never embed admin pages.

---

## Plans & limits

**Who does what:**

- **Developer:** defines the available features in `config/larasaas.php`.
- **Admin:** creates plans and sets feature values (in your dashboard or `/app/admin/plans`).
- **Your app:** enforces the limits on the server.

### 1. Define features (config)

```php
'plan_features' => [
    'uploads'    => ['label' => 'File uploads', 'type' => 'boolean', 'default' => false],
    'storage_gb' => ['label' => 'Storage', 'type' => 'integer', 'unit' => 'GB', 'default' => 0, 'allow_unlimited' => true],
],
```

Types: `boolean`, `integer`, `number`. `-1` means unlimited when `allow_unlimited` is true.

### 2. Manage plans (from any dashboard)

```php
use LaraSaaS\Facades\LaraSaaS;

$plan = LaraSaaS::createPlan([
    'key'      => 'pro',
    'name'     => 'Pro',
    'monthly'  => ['price' => 29,  'stripe_id' => 'price_monthly_id'],
    'yearly'   => ['price' => 290, 'stripe_id' => 'price_yearly_id'],
    'features' => ['uploads' => true, 'storage_gb' => 100],
]);

LaraSaaS::updatePlan($plan, ['name' => 'Pro Plus']); // partial update
LaraSaaS::archivePlan($plan);                        // hide from new customers
LaraSaaS::updatePlan($plan, ['active' => true]);     // restore
```

- Protect these calls with your own admin check (e.g. `can:larasaas-admin`).
- Plan `key` and Stripe price IDs can't change after creation. To change a price, archive the plan and create a new one.
- Archived plans keep working for existing subscribers.
- LaraSaaS never creates Stripe products or prices. Create them in Stripe first.

### 3. Enforce limits (server side)

```php
abort_unless(LaraSaaS::allows($request, 'uploads'), 403);
abort_unless(LaraSaaS::allows($request, 'storage_gb', $usedGb + $newFileGb), 403);

$limit = LaraSaaS::entitlement($request, 'storage_gb'); // e.g. 100, -1 or 0
```

> LaraSaaS checks limits; it doesn't track usage. Your app counts usage.

---

## Subscriptions

**Protect routes:**

```php
Route::middleware('larasaas.subscribed')->group(/* ... */);
Route::get('/reports', ReportController::class)->middleware('larasaas.subscribed:price_123'); // specific price
```

**React to changes** (welcome e-mails, seat limits, caches…):

```php
use LaraSaaS\Events\SubscriptionChanged;

Event::listen(SubscriptionChanged::class, function (SubscriptionChanged $event) {
    // ...
});
```

**Trials, promo codes and VAT IDs** are set in `config/larasaas.php` → `billing`. Trials are given on the first subscription only.

---

## Multi-tenant workspaces & teams

Turn it on **before** running migrations:

```bash
php artisan larasaas:install --multi-tenant
php artisan migrate
```

**What you get:** workspaces, Owner/Admin/Member roles, e-mail invitations with expiring links, the `/app/workspaces` pages and a JSON API.

**What your app still does:**

1. Adds a `workspace_id` column to your own tables and filters by it.
2. Sends the invitation e-mail. LaraSaaS creates the invitation and its token.

```php
$workspace = LaraSaaS::createWorkspace($user, ['name' => 'Acme Inc']); // creator becomes Owner

$invitation = LaraSaaS::inviteMember($workspace, $user, 'sara@acme.com', 'admin');
Mail::to($invitation->email)->send(new TeamInvite($invitation)); // your mailable

LaraSaaS::acceptInvitation($token, $sara);
LaraSaaS::hasWorkspaceRole($workspace, $sara, 'admin'); // true for admin and owner
```

| | Owner | Admin | Member |
|---|---|---|---|
| Invite people | ✓ any role | ✓ except owner | ✗ |
| Change roles | ✓ | ✗ | ✗ |
| Remove people | ✓ except the last owner | members only | ✗ |

**Bill the company instead of each user:**

```php
// app/Models/Workspace.php — must EXTEND the package model
class Workspace extends \LaraSaaS\Models\Workspace
{
    use \Laravel\Cashier\Billable;
}
```

```env
LARASAAS_WORKSPACE_MODEL=App\Models\Workspace
LARASAAS_BILLABLE=workspace
```

That is all: LaraSaaS points Cashier at the paying workspace itself. Only add the call below if you bill a model LaraSaaS does not know about. Put it in `AppServiceProvider::boot()` — host providers boot after package providers, so your call has the final say:

```php
// AppServiceProvider::boot() — optional
\Laravel\Cashier\Cashier::useCustomerModel(\App\Models\Workspace::class);
```

<details>
<summary>Workspace API endpoints</summary>

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/larasaas/workspaces` | The user's workspaces |
| `POST` | `/api/larasaas/workspaces` | Create a workspace |
| `GET` | `/api/larasaas/workspaces/{id}` | Workspace details |
| `GET` | `/api/larasaas/workspaces/{id}/members` | Members and roles |
| `POST` | `/api/larasaas/workspaces/{id}/invitations` | Invite (`email`, `role`) |
| `GET` | `/api/larasaas/workspaces/{id}/invitations` | List invitations |
| `DELETE` | `/api/larasaas/workspaces/{id}/invitations/{invitation}` | Revoke an invitation |
| `GET` | `/api/larasaas/invitations/{token}` | Preview an invitation |
| `POST` | `/api/larasaas/invitations/{token}/accept` | Accept an invitation |
| `PUT` | `/api/larasaas/workspaces/{id}/members/{user}/role` | Change a role (Owners) |
| `DELETE` | `/api/larasaas/workspaces/{id}/members/{user}` | Remove a member |

</details>

---

## Announcements

Admins post banners from **`/app/admin/announcements`**: types, colors, schedules, call-to-action buttons and dismissible messages.

```tsx
<AnnouncementStack variant="floating" />
```

Add your own type in `config/larasaas.php`:

```php
'announcements' => [
    'poll_seconds' => 60, // 0 = no polling
    'types' => [
        'maintenance' => ['color' => '#64748b', 'icon' => 'alert-triangle'],
    ],
],
```

---

## Feature flags

Roll features out gradually, separately from paid plans:

```php
'features' => [
    'ai-assistant'  => ['enabled' => false],
    'new-dashboard' => ['percentage' => 50],
    'beta-reports'  => ['workspaces' => [1, 4]],
    'concierge'     => ['emails' => ['vip@yourapp.com']],
],
```

```php
use Laravel\Pennant\Feature;

if (Feature::for($request->user())->active('ai-assistant')) { /* ... */ }

Route::get('/beta', BetaController::class)->middleware('larasaas.feature:beta-reports');
```

Toggle flags from **`/app/admin/features`**.

---

## Admin panels

`/app/admin/plans`, `/app/admin/announcements` and `/app/admin/features` (inertia mode) are protected by the `larasaas-admin` Gate:

| # | Condition | Who gets in |
|---|---|---|
| 1 | `LARASAAS_ADMIN_EMAILS` is set | Only those e-mails (exact match) |
| 2 | Otherwise | Users with a truthy `larasaas_admin` attribute |
| 3 | Neither | Nobody (403) |

**Already have roles?** Redefine the Gate:

```php
// AppServiceProvider::boot()
Gate::define('larasaas-admin', fn ($user) => $user->hasRole('admin'));
```

---

## Theming & translations

### Theme

- Follows your `.dark` class (or the system setting) automatically.
- Brand color: `'theme' => ['accent' => '#7c3aed']`, or `--larasaas-accent` in your CSS.

### Translations

LaraSaaS ships **English, French and Arabic** and follows your app's locale (`App::setLocale()`). Missing languages fall back to your `app.fallback_locale`.

**Add a language:**

```bash
php artisan vendor:publish --tag=larasaas-lang
cp -r lang/vendor/larasaas/en lang/vendor/larasaas/nl
```

Translate the values in `lang/vendor/larasaas/nl/larasaas.php` (keep the keys). That's it.

**Change existing wording:** edit the published file, e.g. `lang/vendor/larasaas/en/larasaas.php`.

**Right-to-left:** `ar`, `he`, `fa` and `ur` (including `ar_SA` etc.) are RTL automatically. Add more with `'rtl_locales' => [...]`.

---

## Configuration reference

Everything lives in `config/larasaas.php`, with comments and examples for every key.

| Section | What it controls |
|---|---|
| `user_model` | Your user model (default `App\Models\User`) |
| `admin_emails` | Who can open the admin panels |
| `route` | URLs, domain, route names and middleware for every surface |
| `integration` | Mode, CORS origins, embed framing and link lifetime |
| `multi_tenant` | Workspaces, models, roles and invitations |
| `billing` | Who pays, trials, promo codes, VAT IDs |
| `plan_features` | Your feature catalog |
| `pricing` | Currencies, intervals and display options |
| `announcements` | Polling and announcement types |
| `features` | Feature flags |
| `theme` · `rtl_locales` | Look, feel and text direction |

**Common `.env` keys:**

```env
LARASAAS_INTEGRATION_MODE=inertia
LARASAAS_MULTI_TENANT=false
LARASAAS_BILLABLE=user
LARASAAS_ADMIN_EMAILS=you@yourapp.com
LARASAAS_PREFIX=app
LARASAAS_API_CORS_ORIGINS=
LARASAAS_EMBED_FRAME_ANCESTORS=*

# Only when your model is not App\Models\User. Unquoted or 'single quotes' —
# "double quotes" make dotenv fail on the \M escape sequence.
LARASAAS_USER_MODEL=App\Models\AccessManagement\User
```

---

## Artisan commands

| Command | Purpose |
|---|---|
| `php artisan larasaas:install` | Set up the package (`--mode=`, `--multi-tenant`, `--no-multi-tenant`, `--migrate`, `--force`) |
| `php artisan larasaas:doctor` | Check your setup, e.g. missing tables, or a user model changed after migrating |
| `php artisan vendor:publish --tag=larasaas-lang` | Publish translations |

---

## Troubleshooting

**`ext-bcmath` is missing**
Install it for the PHP version Composer uses: `sudo apt install php-bcmath` (Debian/Ubuntu) or `sudo dnf install php-bcmath` (Fedora/RHEL). Homebrew PHP already includes it. Don't use `--ignore-platform-req`, because billing would fail at runtime.

**"model must use the Laravel\Cashier\Billable trait"**
Add `use Billable;` to the model that pays: the User, or your Workspace with `LARASAAS_BILLABLE=workspace`.

**Webhooks return 500: `class_parents(): Class App\Models\User does not exist`**
Cashier looks webhook customers up with its own static customer model, which defaults to a hardcoded `App\Models\User` — Checkout works, but `customer.subscription.*` and `invoice.*` events crash, so no subscription row is written. Set `LARASAAS_USER_MODEL=App\Models\...` (or `LARASAAS_BILLABLE=workspace` + `LARASAAS_WORKSPACE_MODEL`) to your real model and run `php artisan config:clear`; LaraSaaS points Cashier at it on boot. Still failing? Something else sets it — check `Cashier::useCustomerModel(...)` in `AppServiceProvider::boot()`, and run `php artisan larasaas:doctor`, which reports any mismatch. Because the webhook answers 500, Stripe keeps retrying the event: in production it replays by itself once the model resolves. With `stripe listen` the retries do not reach your machine — resend the event from **Developers → Events** in test mode, or simply cancel and re-subscribe.

**Admin pages return 403**
Set `LARASAAS_ADMIN_EMAILS` or redefine the `larasaas-admin` Gate. See [Admin panels](#admin-panels).

**Subscriptions don't update after payment**
Check the webhook URL and that `STRIPE_WEBHOOK_SECRET` matches. Locally, keep `stripe listen` running.

**Inertia says the page was not found**
Update the `resolve` function in `app.tsx` (Quick start, step 9).

**Changed the user model after migrating**
Run `php artisan larasaas:doctor`. It tells you exactly what to fix.

**Composer only finds `dev-main`**
Use `composer require hasan-deeba/larasaas:^1.0`, and check the [releases page](https://github.com/hasan-deeba/LaraSaaS/releases).

---

## Versioning

LaraSaaS follows [semantic versioning](https://semver.org). Require `^1.0` so `composer update` only brings non-breaking updates. See [CHANGELOG.md](CHANGELOG.md).

```bash
composer update hasan-deeba/larasaas
```

---

## Contributing

**Use a local copy in a host app:**

```json
"repositories": [{ "type": "path", "url": "./packages/larasaas", "options": { "symlink": true } }]
```

```bash
composer require hasan-deeba/larasaas @dev
```

**Repository layout:**

```
config/              larasaas.php: every setting, documented
src/                 PHP source (namespace LaraSaaS)
routes/              web, api, embed, workspaces and webhook routes
database/migrations/ core schema + optional workspace schema
resources/js/        React pages & components (published into host apps)
resources/lang/      EN / FR / AR translations
resources/views/     embed pages
tests/               PHPUnit test suite
demo/                showcase site (development only, not shipped)
```

Composer installs ship only `config/`, `src/`, `routes/`, `database/`, `resources/`, `README.md`, `CHANGELOG.md` and `LICENSE`.

---

## License

MIT. See [LICENSE](./LICENSE).