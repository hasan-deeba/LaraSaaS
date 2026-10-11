# Changelog

All notable changes to `larasaas/larasaas` will be documented in this file.

## 1.1.0 — Unreleased

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
