# Changelog

All notable changes to `larasaas/larasaas` will be documented in this file.

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
