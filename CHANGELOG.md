# Changelog

All notable changes to `hasan-deeba/larasaas` will be documented in this file.

## 3.1.0 — 2026-10-15

### Added

- **🏢 Multi-tenant Workspaces** — first-party `larasaas_workspaces` tables and models (swap-able via `workspaces.model` / `member_model` / `invitation_model`) so users can operate within parallel organizational structures. Opt in on your user model with `LaraSaaS\Concerns\HasWorkspaces` (`workspaces()`, `currentWorkspace()`, `switchWorkspace()`, `roleIn()`…).
- **👥 Team Management** — robust invitation lifecycle (invite → e-mail-matched accept by 64-char token → expiry → resend/revoke, re-invite after removal) with granular workspace-level roles (Owner, Admin, Member). `WorkspaceRole` maps each role to a permission catalog (`members.invite`, `workspace.delete`, `ownership.transfer`…), overridable per role via `workspaces.role_permissions`. Server-enforced invariants: the owner can never be demoted or removed, `owner` is transfer-only, and transfers keep `owner_id` and the members table consistent.
- `LaraSaaS\Support\WorkspaceManager` (validated writes, same pattern as `PlanManager`) plus facade helpers `createWorkspace()`, `inviteToWorkspace()`, `acceptWorkspaceInvitation()`, `workspacesFor()`.
- JSON API for workspaces, members, invitations and ownership transfer under `route.api_prefix` (id **or** slug binding), with `larasaas.workspace`, `larasaas.workspace.can:` and `larasaas.workspace.role:` middleware aliases for host routes.
- `php artisan larasaas:doctor` and `LaraSaaS::billingSchemaProblems()` — developer alerts for the billing schema.

### Changed

- **Cashier customer columns now follow the configured model.** The baseline migration adds `stripe_id`, `pm_type`, `pm_last_four` and `trial_ends_at` to the table of `larasaas.user_model` — or of `billing.workspace_model` (falling back to `workspaces.model`) when `billing.billable` is `workspace` — never to a hardcoded `users` table. The additions are idempotent (`hasColumn` guards) and `down()` never drops them from host tables. The stock Cashier `create_customer_columns` migration (which hardcodes `users`) is now skipped by `larasaas:install`; a host-owned copy published earlier is left untouched but flagged by the doctor when it targets the wrong table.
- The baseline migration (same filename) additionally creates `larasaas_workspaces`, `larasaas_workspace_members` and `larasaas_workspace_invitations`. If you already ran the 3.0.0 baseline, apply the new `Schema::create` blocks with your own migration (the 3.x baseline was announced as unused; fresh installs are unaffected).
- **Alerts when the configured model changes after migrating:** the columns stay on the previous table in that case — `larasaas:install` warns right after migrating, `larasaas:doctor` reports `⚠ CONFIG CHANGED?` with remediation steps, and the config file carries an explicit alert comment next to `user_model` / `billing.*`. See README "Changing the configured model".

### Documentation

- New README section "Multi-tenant Workspaces & Team Management" (schema, role/permission matrix, manager API, JSON endpoints, workspace billing) and "Changing the configured model".

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
