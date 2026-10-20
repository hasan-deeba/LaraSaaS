# LaraSaaS contributor / AI-agent guide

## Package boundaries

- This is `hasan-deeba/larasaas`, a Composer library supporting PHP 8.2+ and Laravel 10/11/12 (Cashier 15/16). `demo/` is an export-ignored Next.js showcase, not the production billing/admin backend.
- Never add host authentication or overwrite host User models. The host owns auth, the Billable trait, workspace resolution, Stripe credentials and usage accounting.
- Version 3 uses **database-backed plans** (`src/Models/Plan.php`). Do not restore `pricing.plans` or seed fake products/Stripe IDs in migrations.
- Developers own `config/larasaas.php` → `plan_features`: stable snake_case keys and boolean/integer/number definitions. Admins assign validated values through `createPlan`/`updatePlan` in the host dashboard (or the optional `Admin/Plans` page). `features` is the separate Pennant rollout catalog; it is not paid entitlement enforcement.
- Plan keys/Stripe IDs are stable subscription identifiers. Archive, never erase, catalog rows. Archived plans cannot be purchased but still resolve existing subscriptions and entitlements. Features change for current subscribers when edited.
- `LaraSaaS::allows()`/`entitlement()` are server checks on Cashier's current billable subscription, not quota usage counters. Host apps must use atomic accounting for concurrent usage.

## Routing and security

- Pricing GETs are public on Inertia/API/embed surfaces. Use separate public middleware stacks; retain auth on billing, invoices, announcements, feature flags and admin endpoints. API optional auth uses `route.pricing_guard`.
- Signed embed identity must be in a valid, unexpired signed QUERY, never a POST body. Invalid identity is 403, not anonymous fallback. Embed writes skip CSRF and therefore always require signed user URLs. Guest embed links only support pricing.
- Admin routes require `larasaas-admin`; validate only catalog-defined feature keys and typed values. Stripe IDs must not overlap across plans. Do not trust frontend values for access control.
- Route prefixes and names are configurable. New frontend links should come from server-generated URLs or shared `web_base`, not hard-coded `/app`.

## Migrations and upgrades

- `database/migrations/2026_10_14_000000_create_larasaas_schema.php` is the single-tenant baseline (plans, announcements, dismissals, `larasaas_settings` stamps, config-driven customer columns). `database/migrations/2026_10_14_000001_create_larasaas_workspaces_schema.php` is the optional multi-tenant workspace/team schema, published only for multi-tenant installs. Define all LaraSaaS tables/columns directly in these files; do not restore legacy/additive migrations.
- **Migrations never hardcode host tables.** The customer columns land on the table of the configured billable model (`larasaas.user_model`, or the workspace model for workspace billing) and are idempotent (`hasColumn` guards). `larasaas:install` retires Cashier's stock `create_customer_columns` migration (hardcoded `users`, not idempotent) after confirmation. `larasaas_settings` stamps which model/table the schema was built for; `SetupDoctor` + `larasaas:doctor` alert when config drifts (e.g. `larasaas.user_model` changed after migrating). Never regress to `Schema::table('users', …)`.
- `larasaas:install` publishes the migrations without overwriting host files, including renamed timestamps, and only publishes the workspace schema when the project is multi-tenant (`--multi-tenant` / `--no-multi-tenant` / interactive question → `LARASAAS_MULTI_TENANT`). Migrations are not auto-loaded from vendor.
- No automatic upgrade from old split migrations is shipped. Never drop/recreate real host data. A future deployed-schema upgrade needs an explicit migration strategy.
- Plan writes belong in `Support/PlanManager`, exposed by the manager/facade (`createPlan`, `updatePlan`, `archivePlan`). Reuse its validation in the optional admin UI; host controllers own authorization. Embeds are for customer pricing/billing, never admin forms.

## Multi-tenant workspaces & teams

- `multi_tenant.enabled` is THE single-tenant/multi-tenant switch. When off, no workspace routes, tables or features exist. When on, `larasaas_workspaces` / `larasaas_workspace_members` / `larasaas_workspace_invitations` power workspaces, granular roles (Owner, Admin, Member with configurable `implies`) and e-mail invitations.
- Workspace/team domain rules live in `Support/WorkspaceManager` (role checks, last-owner protection, invitation lifecycle) and are enforced server-side; controllers stay thin and hosts own authentication. `Support/WorkspacePresenter` is the single serialization shape for web + API.
- Model classes are host-swappable via `multi_tenant.workspace_model` / `membership_model` / `invitation_model` (custom models should extend the shipped ones). Route params are resolved manually through the configured classes — never hardcoded type-hints.
- Workspace billing resolves the billable via `billing.workspace_resolver`, the user's `currentWorkspace()`, or — for multi-tenant installs — the most recently joined workspace. The workspace model must use Cashier's `Billable`.
- **Cashier's customer model is synced by the package.** Cashier resolves webhook customers with its own static `Cashier::$customerModel` (default `App\Models\User`, never `larasaas.user_model`), so `LaraSaaS::syncCashierCustomerModel()` points it at `LaraSaaS::billableType()` in the provider's `register()` and again in `boot()`; a host that calls `Cashier::useCustomerModel()` in `AppServiceProvider::boot()` keeps the final say (host providers boot last). `StripeWebhookController::getUserByStripeId()` guards the lookup and logs one actionable error instead of `class_parents(): Class App\Models\User does not exist`. `SetupDoctor` reports mismatches and a missing `Billable` trait. Never reintroduce a hardcoded Cashier customer model, and never let the package require hosts to call `Cashier::useCustomerModel()` for the documented setups. Tests must not declare a class named `App\Models\User` — its absence is the regression scenario.

## Verification

- `composer install` then `vendor/bin/pest`; lint with `find src config routes database tests -name '*.php' -print0 | xargs -0 -n1 php -l`.
- GitHub Actions runs the Laravel 10/11/12 matrix. All must pass before a release.
- `npm ci` then `npm run typecheck:package` validates the shipped React source; demo checks are `npm run typecheck` and `npm run lint`.
- Add regression tests for authorization, typed features, signed identity, entitlement lifecycle and single-schema installation/rollback and manager writes without package routes. Do not call live Stripe in tests.
- Update README, CHANGELOG, this guide and `.github/copilot-instructions.md` for architectural changes. Tag breaking changes as a major version. Git tags, not composer.json's version field, determine Composer releases.
