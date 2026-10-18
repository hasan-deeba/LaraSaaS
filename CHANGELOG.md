# Changelog

All notable changes to `hasan-deeba/larasaas` will be documented in this file.

## Unreleased

### Added

- `rtl_locales` config and `LaraSaaS::textDirection()`: right-to-left detection is configurable and matches region variants (`ar_SA` → `ar`). `ur` is now RTL by default.
- `announcements.poll_seconds = 0` loads announcements once without polling.
- README guide for publishing and adding translations (`--tag=larasaas-lang`).

### Removed

- Unused config keys `announcements.history_days` and `locales` (never read by the package).
- `locale` config: it changed `<html lang>` but not the translated text. LaraSaaS now always follows the host's `app()->getLocale()`.

### Fixed

- Checkout no longer crashes with `BadMethodCallException` (`hasEverSubscribed()` does not exist in Cashier) for plans without their own trial days.
- Free trials (global or per-plan) are now granted on the first subscription only; returning customers no longer receive a plan-level trial again.

## 4.0.0 — 2026-10-15

### Breaking changes

- The Cashier customer columns (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`) are now created by the LaraSaaS schema migration **on the table of the configured model** (`larasaas.user_model`, or the workspace model for workspace billing) — never on a hardcoded `users` table. The installer retires Cashier's stock `create_customer_columns` migration (it hardcodes `users` and is not idempotent) after confirmation. Existing installations keep their columns; new installations no longer depend on the Cashier copy.
- The package ships a second, optional migration (`2026_10_14_000001_create_larasaas_workspaces_schema.php`) for the multi-tenant workspace/team schema. It is published only for multi-tenant installs (`larasaas:install --multi-tenant` or the interactive question). The baseline filename/schema is unchanged for existing installs.

### Added

- **🏢 Multi-tenant Workspaces** — seamless workspace management allowing users to operate within parallel organizational structures. The `multi_tenant.enabled` / `LARASAAS_MULTI_TENANT` config switch makes the project multi-tenant (or keeps it single-tenant with zero workspace overhead): workspaces with unique slugs, per-user workspace lists and current-workspace resolution.
- **👥 Team Management** — robust invitation system with granular workspace-level roles (Owner, Admin, Member): e-mail invitations with expiring tokens, accept/revoke flows, role changes with implications (`admin` implies `member`, configurable), and server-side safety rules (last-owner protection, Admins cannot touch Owners/Admins, owner-pointer reassignment). Manager/facade API (`createWorkspace`, `inviteMember`, `acceptInvitation`, `changeMemberRole`, `removeMember`, `hasWorkspaceRole`) plus JSON API and shipped Inertia pages (`/app/workspaces`, team management).
- Workspace billing integration: with `billing.billable = workspace` and no custom resolver, the billable workspace is resolved from the team tables (most recently joined). Customer columns are created on the workspace model's table.
- `php artisan larasaas:doctor` — setup drift detection with loud alerts: when the model in `larasaas.user_model` changes after migrating, it reports which table holds the billing columns and what to do (also surfaced at the end of `larasaas:install` and as a one-time boot log warning). Migrations stamp the model/table they built for in `larasaas_settings`.
- `larasaas:install` asks whether the project is multi-tenant (`--multi-tenant` / `--no-multi-tenant`), writes `LARASAAS_MULTI_TENANT` to `.env`, and publishes the workspace schema only when needed.
- Regression coverage for config-driven billable tables (custom user tables, workspace tables), model-change alerts, install idempotence/rollback, multi-tenant installs, the full invitation/role lifecycle, single-tenant route absence and workspace billing resolution.

## 3.0.0 — 2026-10-14

### Breaking changes

- Remove the unused split/legacy migration paths. The package now contains exactly one migration in `database/migrations`, defining all LaraSaaS tables and columns directly. Cashier keeps ownership of its separate billing migrations. There is no automatic upgrade from the old split schema; see README before installing over an existing database.
- The consolidated 2.0 baseline retains its filename and schema, so projects that already published it need no schema changes. Installation never overwrites it, even with `--force` or a host-renamed timestamp.

### Added

- Public `LaraSaaS::createPlan()`, `updatePlan()` and `archivePlan()` functions for developer-owned dashboards in every integration mode, without an admin embed or an HTTP request. Host applications authorize the caller.
- Shared plan validation for the manager and optional Inertia admin controller, typed feature defaults, partial updates and preserved archived subscription mappings.
- Regression coverage for direct catalog writes, identifier immutability, guest pricing/private billing, no admin embeds, and the single migration's installation/rollback/reinstallation.

### Documentation

- Lead with `LaraSaaS::embedUrl()` for public pricing: no user or signature needed. Billing/subscription operations remain authenticated; embed writes remain signed.
- Document native dashboard form/controller integration, the one-file schema and database-only plan storage. No sample plans or Stripe IDs are inserted by migrations.

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
