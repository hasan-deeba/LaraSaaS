# LaraSaaS contributor / AI-agent guide

## Package boundaries

- This is `hasan-deeba/larasaas`, a Composer library supporting PHP 8.2+ and Laravel 10/11/12 (Cashier 15/16). `demo/` is an export-ignored Next.js showcase, not the production billing/admin backend.
- Never add host authentication or overwrite host User models. The host owns auth, the Billable trait, workspace resolution, Stripe credentials and usage accounting.
- Version 2 uses **database-backed plans** (`src/Models/Plan.php`). Do not restore `pricing.plans` or seed fake products/Stripe IDs in migrations.
- Developers own `config/larasaas.php` → `plan_features`: stable snake_case keys and boolean/integer/number definitions. Admins assign validated values in `Admin/Plans`. `features` is the separate Pennant rollout catalog; it is not paid entitlement enforcement.
- Plan keys/Stripe IDs are stable subscription identifiers. Archive, never erase, catalog rows. Archived plans cannot be purchased but still resolve existing subscriptions and entitlements. Features change for current subscribers when edited.
- `LaraSaaS::allows()`/`entitlement()` are server checks on Cashier's current billable subscription, not quota usage counters. Host apps must use atomic accounting for concurrent usage.

## Routing and security

- Pricing GETs are public on Inertia/API/embed surfaces. Use separate public middleware stacks; retain auth on billing, invoices, announcements, feature flags and admin endpoints. API optional auth uses `route.pricing_guard`.
- Signed embed identity must be in a valid, unexpired signed QUERY, never a POST body. Invalid identity is 403, not anonymous fallback. Embed writes skip CSRF and therefore always require signed user URLs. Guest embed links only support pricing.
- Admin routes require `larasaas-admin`; validate only catalog-defined feature keys and typed values. Stripe IDs must not overlap across plans. Do not trust frontend values for access control.
- Route prefixes and names are configurable. New frontend links should come from server-generated URLs or shared `web_base`, not hard-coded `/app`.

## Migrations and upgrades

- `database/schema/2026_10_14_000000_create_larasaas_schema.php` is the single fresh-install baseline.
- `database/migrations/2025_01_01_000001_create_larasaas_tables.php` is immutable legacy history; the 2026 plans migration is an additive upgrade. Never run baseline and legacy schema together.
- `larasaas:install` selects a baseline for fresh apps or the additive migration for legacy apps. It never overwrites existing migration files. Migrations are not auto-loaded from vendor. Cashier retains its own migrations.
- Do not silently drop or recreate user data. Document any future schema/entitlement-key upgrade explicitly.

## Verification

- `composer install` then `vendor/bin/pest`; lint with `find src config routes database tests -name '*.php' -print0 | xargs -0 -n1 php -l`.
- GitHub Actions runs the Laravel 10/11/12 matrix. All must pass before a release.
- `npm ci` then `npm run typecheck:package` validates the shipped React source; demo checks are `npm run typecheck` and `npm run lint`.
- Add regression tests for authorization, typed features, signed identity, entitlement lifecycle and migration upgrades. Do not call live Stripe in tests.
- Update README, CHANGELOG, this guide and `.github/copilot-instructions.md` for architectural changes. Tag breaking changes as a major version. Git tags, not composer.json's version field, determine Composer releases.
