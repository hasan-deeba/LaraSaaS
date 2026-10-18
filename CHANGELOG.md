# Changelog

All notable changes to `hasan-deeba/larasaas` will be documented in this file.

## 1.0.0 — 2026-10-18

First stable release.

### Integration modes

- Choose how your frontend consumes LaraSaaS with `integration.mode` / `LARASAAS_INTEGRATION_MODE`, switchable at any time:
    - `inertia` (default): Laravel + React in the same project, with publishable Inertia pages and components.
    - `api`: headless JSON API for React SPAs on other servers and Flutter / iOS / Android apps, with optional CORS (`integration.api.cors_origins`).
    - `embed`: standalone pricing/billing pages for `<iframe>` / WebView, authenticated by signed expiring URLs (`LaraSaaS::embedUrl()`) with configurable CSP `frame-ancestors`.
    - `all`: every surface at once. `custom`: only the Stripe webhook, so you build your own UI on the `LaraSaaS` manager.

### Billing (Laravel Cashier + Stripe)

- Stripe Checkout subscriptions, prorated plan swaps, cancel/resume, invoices, customer portal and webhooks with a `SubscriptionChanged` domain event.
- Bill the user or a workspace (`billing.billable`). Cashier customer columns are created on the table of the configured model, never on a hardcoded `users` table.
- Free trials, global or per plan, granted on the first subscription only.
- Optional promotion codes and tax ID collection at Checkout.
- `larasaas.subscribed` middleware, with optional price pinning.

### Plans & entitlements

- Plans are stored in the database and managed with `LaraSaaS::createPlan()`, `updatePlan()` and `archivePlan()` from your own dashboard, or through the optional `/app/admin/plans` UI.
- Developer-defined typed feature catalog (`plan_features`: boolean / integer / number) with validated per-plan values.
- Server-side `LaraSaaS::entitlement()` and `allows()` checks for user and workspace billing.
- Immutable plan keys and Stripe price IDs. Archived plans keep working for existing subscribers.
- Public pricing on web, API and embed; billing routes always require authentication.

### Multi-tenant workspaces & teams

- Optional multi-tenancy (`multi_tenant.enabled` / `LARASAAS_MULTI_TENANT`): workspaces with unique slugs, per-user workspace lists and current-workspace resolution.
- Team management with Owner / Admin / Member roles, configurable role implications, and e-mail invitations with expiring tokens (accept, revoke, strict e-mail matching).
- Server-side safety rules: the last Owner can't be removed, Admins can't manage Owners or other Admins, and ownership passes to a surviving Owner.
- Host-swappable workspace, membership and invitation models.
- Facade API, JSON API and Inertia pages (`/app/workspaces`, team management).

### Announcements

- Admin-managed banners with colored types, scheduling windows, audience targeting and per-user dismissals.
- React `<AnnouncementStack />` with configurable polling (`poll_seconds`, `0` to disable).

### Feature flags (Laravel Pennant)

- Config-resolved strategies: enabled, percentage, workspaces, e-mails, or a resolver.
- Workspace-scoped activation, `larasaas.feature:*` middleware and an admin panel.

### Admin access

- Admin panels are protected by the `larasaas-admin` Gate: allow-list e-mails with `LARASAAS_ADMIN_EMAILS`, use a `larasaas_admin` user attribute, or redefine the Gate.

### Developer experience

- `php artisan larasaas:install` picks the integration mode and multi-tenancy, writes `.env`, and publishes config, migrations, translations and (when needed) React components.
- `php artisan larasaas:doctor` detects drift between config and database (for example, a changed user model after migrating).
- Fully configurable routing: domain, prefixes, route names and middleware stacks per surface, with ready-made auth recipes for sessions, Sanctum, Passport and custom guards.
- React components (`PricingTable`, `AnnouncementStack`, `FeatureGate`) with zero npm dependencies, light/dark aware.
- EN / FR / AR translations, following the host app's locale. Add languages with `php artisan vendor:publish --tag=larasaas-lang`.
- Right-to-left support with configurable `rtl_locales`, including region variants (`ar_SA`).
- Supports Laravel 10, 11 and 12 with Cashier 15 and 16.