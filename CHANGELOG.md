# Changelog

All notable changes to `hasan-deeba/larasaas` will be documented in this file.

## 1.0.0 — 2026-10-29

First stable release.

### Added

- Stripe subscription billing with Checkout, plan changes, cancellations, resumes, invoices, customer portal, and webhooks.
- Database-backed plans with developer-defined, typed paid-plan entitlements. Check access and limits server-side with `LaraSaaS::entitlement()` and `LaraSaaS::allows()`.
- Integration modes for Inertia, a headless JSON API, standalone embeds, or a combination of surfaces.
- Optional multi-tenant workspaces, team roles, and expiring invitations.
- Admin-managed announcements with scheduling, audience targeting, dismissals, and a React `<AnnouncementStack />`.
- Built-in Inertia pages for pricing, billing, the dashboard, and plan and announcement administration.
- Signed, expiring embed URLs for standalone pricing and billing pages.
- `larasaas:install` and `larasaas:doctor` Artisan commands.
- English, French, and Arabic translations, with dark-mode and right-to-left support.

### Compatibility

- PHP 8.2+
- Laravel 10–13, with compatible Laravel Cashier versions.