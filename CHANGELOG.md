# Changelog

All notable changes to `hasan-deeba/larasaas` will be documented in this file.

## 2.0.0 — 2026-10-14

### Breaking changes

- Plans move from `pricing.plans` config to `larasaas_plans`. Admins manage the catalog at `/app/admin/plans`; new installations start with no seeded plans. See README for a non-destructive 1.x upgrade and one-time legacy plan import.
- Migrations are explicitly published by the installer, not auto-loaded from vendor. Fresh installs publish one consolidated LaraSaaS schema migration; legacy installs receive the additive plans migration. Cashier migrations remain separate.
- Public web/API/embed pricing is separate from authenticated billing routes. Embed writes require signed user URLs even for session users.

### Added

- Developer-owned typed `plan_features` catalog, validated per-plan boolean/integer/number values, display labels and entitlement/limit data.
- Server-side `entitlement()` and `allows()` helpers with user/workspace billing support; these check limits but do not track usage.
- Admin create/edit/archive/restore controls, immutable plan keys/Stripe IDs, and retained mappings for archived subscribers.
- Optional session/token identity for API pricing, guest embed links via `embedUrl()`, and guest sign-in CTAs without billing navigation.
- Regression coverage for plan management, validation, entitlements, public/private access, signed embed security, migration installation/reinstallation/rollback and upgrades.
- Updated README, demo guidance, and AI contributor instructions.

## 1.1.0 — 2026-10-13

### Added

- **Integration modes** — choose how your frontend consumes LaraSaaS via `integration.mode` / `LARASAAS_INTEGRATION_MODE` (switchable at any time):
  - `inertia` (default) — Laravel + React in the same project; the existing drop-in experience.
  - `api` — headless JSON surface for React SPAs on other servers, Flutter / iOS / Android apps: the full pricing matrix, subscription state, invoices, subscribe/swap/cancel/resume and the Stripe portal URL. Design your own tables & pricing UI on the client.
  - `embed` — standalone, self-contained pricing/billing pages (no Node/Vite build) for `<iframe>` / mobile WebView embedding, authenticated through signed expiring URLs (`LaraSaaS::embedUrl()`), with configurable CSP `frame-ancestors`.
  - `all` — every surface at once; `custom` — only the Stripe webhook, build your own on the `LaraSaaS` manager.
- New headless API endpoints under `/api/larasaas`: `GET /pricing`, `GET /invoices`, `POST /billing/subscribe/{plan}`, `POST /billing/swap/{plan}`, `POST /billing/cancel`, `POST /billing/resume`, `POST /billing/portal`. All headless responses use a `{"data": …}` envelope (`{"url": …}` for checkout/portal); `GET /subscription` now lives on the headless controller and follows the same envelope.
- Optional CORS support for browser SPAs (`integration.api.cors_origins`).
- `larasaas:install` now asks for the integration mode (`--mode=`), writes `LARASAAS_INTEGRATION_MODE` to `.env`, and only publishes React components when they are needed.
- Shared `LaraSaaS\Support\BillingActions` used by every surface; `LaraSaaS::integrationMode()`, `servesInertia()`, `servesApi()`, `servesEmbed()`, `embedUrl()` on the manager/facade.

### Fixed

- Require Cashier's `Laravel\Cashier\Billable` trait on the resolved billing model with an actionable setup error instead of undefined billing-method errors. Supports inherited/nested traits and both user and workspace billing.
- Document the required trait, custom Cashier customer-model registration and migration setup; remind users during installation without modifying host model files.

## 1.0.0 — Initial release

### Added

- Composer-installable core with Laravel auto-discovery (`LaraSaaSServiceProvider`, `LaraSaaS` facade).
- Config-driven everything: user model, routing (prefix/domain/middleware/names), billing, pricing, announcements, feature flags, theme and locales.
- **Stripe Billing via Laravel Cashier** — subscription provisioning through Stripe Checkout, plan swaps with proration, cancel/resume, invoices, secure customer portal, webhooks with `SubscriptionChanged` domain event. Bill the user or a workspace.
- **Dynamic pricing table** — plans, intervals, currencies, badges and trials rendered as a React `<PricingTable />`.
- **Announcements system** — colored typings, scheduling windows, audience targeting, dismissible persistence, admin panel and JSON API.
- **Feature flags via Laravel Pennant** — config-resolved strategies (enabled / percentage / workspaces / emails / closure) with workspace-scoped activation endpoints and `larasaas.feature:*` middleware.
- `larasaas.subscribed` middleware (with optional price pinning).
- Shipped React components (`PricingTable`, `AnnouncementStack`, `FeatureGate`) and Inertia pages (Pricing, Billing, Dashboard, Admin) — light/dark aware, RTL-ready, EN/FR/AR translations.
- `php artisan larasaas:install` installer (publishes config, migrations, Cashier migrations, React assets, translations).
