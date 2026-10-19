<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Host Application Integration
    |--------------------------------------------------------------------------
    |
    | LaraSaaS ships NO authentication of its own. Login, registration and
    | password resets keep living in YOUR project — the kit simply resolves
    | the user (and optionally the workspace) models you point it to.
    |
    | The migrations never touch a hardcoded `users` table: the billable
    | customer columns (stripe_id, pm_type, pm_last_four, trial_ends_at) are
    | created on whatever table this model (or the workspace model) uses.
    | If you change this value AFTER migrating, run `php artisan larasaas:doctor`
    | — it alerts you about the drift between config and database.
    |
    | LaraSaaS also points Laravel Cashier at this model for you
    | (Cashier::useCustomerModel), so Stripe webhooks resolve customers with
    | YOUR class instead of Cashier's hardcoded `App\Models\User` default.
    | Without it, a namespaced user model such as
    | App\Models\AccessManagement\User makes every `customer.subscription.*`
    | and `invoice.*` webhook fail with
    | "class_parents(): Class App\Models\User does not exist".
    |
    | Set it from .env with LARASAAS_USER_MODEL — unquoted or in 'single
    | quotes'. NEVER in "double quotes": dotenv reads \M as an escape sequence
    | and then refuses to load the entire .env file.
    |
    */

    'user_model' => env('LARASAAS_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Access (optional)
    |--------------------------------------------------------------------------
    |
    | Who may open the shipped admin panels (/app/admin/plans, announcements,
    | features). They are guarded by the `larasaas-admin` Gate, which asks:
    |
    |   1. LARASAAS_ADMIN_EMAILS set? → only these e-mails (exact match).
    |      e.g. LARASAAS_ADMIN_EMAILS="you@app.com,ops@app.com"
    |   2. Otherwise → users whose `larasaas_admin` attribute is truthy.
    |   3. Otherwise → nobody (403). Safe by default.
    |
    | Prefer your own roles? Redefine the Gate in AppServiceProvider::boot():
    |   Gate::define('larasaas-admin', fn ($user) => $user->is_admin);
    |
    | Not using the shipped admin UI (api/embed mode, own dashboard)? Ignore it.
    |
    */

    'admin_emails' => array_filter(array_map('trim', explode(',', (string) env('LARASAAS_ADMIN_EMAILS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Routing — URLs & who may open them
    |--------------------------------------------------------------------------
    |
    | Every URL, middleware stack, domain and route name below is yours to
    | change. Which groups are registered depends on `integration.mode`:
    | inertia → web pages + JSON API, api → JSON API, embed → iframe pages +
    | JSON API, all → everything. The Stripe webhook is always registered.
    |
    | Quick auth recipes: pick the row that matches how YOUR users log in
    |
    |   Setup                                 api_middleware            public_api_middleware  pricing_guard
    |   ------------------------------------  ------------------------  ---------------------  -------------
    |   Same project, session login (Breeze)  ['web', 'auth']           ['web']                null
    |   Sanctum, SPA on another origin/server ['api', 'auth:sanctum']   ['api']                'sanctum'
    |   Sanctum tokens (Flutter, iOS, ...)    ['api', 'auth:sanctum']   ['api']                'sanctum'
    |   Passport                              ['api', 'auth:api']       ['api']                'api'
    |   Custom guard (config/auth.php)        ['api', 'auth:customer']  ['api']                'customer'
    |
    | The guard must authenticate instances of `user_model` above, because
    | LaraSaaS looks up subscriptions on that model. Sanctum SPA cookies on
    | another origin also need statefulApi() in bootstrap/app.php,
    | SANCTUM_STATEFUL_DOMAINS and `integration.api.cors_origins`.
    |
    */

    'route' => [

        /*
        | URLs
        |----------------------------------------------------------------------
        */

        // Serve every LaraSaaS page/API from one host only. null = any host.
        //   LARASAAS_DOMAIN=billing.yourapp.com
        // Logging in on yourapp.com? Share the session: SESSION_DOMAIN=.yourapp.com
        // (The Stripe webhook ignores this and answers on every host.)
        'domain' => env('LARASAAS_DOMAIN'),

        // Web pages (integration.mode inertia|all):
        //   /app/pricing, /app/billing, /app/dashboard, /app/admin/*, /app/workspaces
        //   LARASAAS_PREFIX=account   →  /account/pricing, /account/billing, ...
        'prefix' => env('LARASAAS_PREFIX', 'app'),

        // JSON API: /api/larasaas/pricing, /api/larasaas/billing, ...
        // The shipped React components read this automatically.
        //   LARASAAS_API_PREFIX=api/v1/billing  →  /api/v1/billing/pricing
        'api_prefix' => env('LARASAAS_API_PREFIX', 'api/larasaas'),

        // Iframe / WebView pages: /embed/pricing, /embed/billing
        //   LARASAAS_EMBED_PREFIX=widgets  →  /widgets/pricing
        'embed_prefix' => env('LARASAAS_EMBED_PREFIX', 'embed'),

        // The URL you paste into Stripe → Developers → Webhooks:
        //   https://yourapp.com/larasaas/webhooks/stripe
        // Requests are verified with STRIPE_WEBHOOK_SECRET (Cashier).
        'webhook_path' => env('LARASAAS_WEBHOOK_PATH', 'larasaas/webhooks/stripe'),

        // Prepended to every route name so they never clash with yours:
        //   route('larasaas.pricing'), route('larasaas.api.pricing'),
        //   route('larasaas.embed.billing'), route('larasaas.billing.index')
        'name_prefix' => 'larasaas.',

        /*
        | Middleware: one stack per kind of page
        |----------------------------------------------------------------------
        */

        // LOGGED-IN WEB PAGES: dashboard, billing, admin, workspaces.
        //   ['web', 'auth', 'verified']   → also require a verified e-mail
        //   ['web', 'auth:customer']      → use another guard from config/auth.php
        'middleware' => ['web', 'auth'],

        // PUBLIC WEB PRICING: /app/pricing. Guests may view it; logged-in
        // users additionally see their current plan. Keep it without `auth`.
        'public_middleware' => ['web'],

        // PROTECTED JSON API: billing, subscribe, cancel, announcements,
        // workspaces. See the recipes table above.
        //   ['web', 'auth']          → same-origin session (default)
        //   ['api', 'auth:sanctum']  → Sanctum cookie or Bearer token
        //   ['api', 'auth:api']      → Passport
        'api_middleware' => ['web', 'auth'],

        // PUBLIC JSON API: GET /api/larasaas/pricing + CORS preflight.
        //   ['web'] → session clients    ['api'] → token clients
        // NEVER add `auth` / `auth:sanctum` here: it would make pricing private.
        // To recognise token users on this route, use `pricing_guard` below.
        'public_api_middleware' => ['web'],

        // Guard used to *optionally* identify the visitor on the PUBLIC JSON
        // pricing route (GET /api/larasaas/pricing), so a token client sees
        // "your current plan" next to the plans. It never requires login:
        // missing/invalid credentials = guest. (Web pricing uses the session;
        // embed pricing uses the signed URL.)
        //   null       → use the session user from the stack above (default)
        //   'sanctum'  → also accept `Authorization: Bearer <token>`
        //   'api'      → Passport
        // The guard must exist in config/auth.php.
        'pricing_guard' => null,

        // ADMIN PANELS: /app/admin/plans, announcements, features.
        // Applied ON TOP of `middleware` above (a stricter extra layer, not a
        // replacement). Who passes `larasaas-admin`: see `admin_emails` above.
        //   ['web', 'auth', 'can:manage-billing']  → reuse your own ability
        //   ['web', 'auth', 'role:admin']          → e.g. spatie/laravel-permission
        'admin_middleware' => ['web', 'auth', 'can:larasaas-admin'],

        // IFRAME / WEBVIEW PAGES: /embed/pricing, /embed/billing.
        // Advanced: leave as is unless you know why you're changing it.
        //  • No `auth`: visitors are identified by the signed, expiring URL from
        //    LaraSaaS::embedUrl($user, 'billing'), with no shared cookies needed.
        //  • No CSRF on purpose: browsers often block the session cookie inside a
        //    cross-site iframe, so every embed POST must carry that signed URL
        //    instead (unsigned POSTs are rejected with 403). Adding
        //    VerifyCsrfToken here will break third-party embeds.
        //  • Session/cookies stay on so a same-site embed can use the normal login.
        //  • The frame headers (`integration.embed.frame_ancestors`) are always
        //    appended by the package and can't be removed by accident.
        'embed_middleware' => [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Mode — how your frontend consumes LaraSaaS
    |--------------------------------------------------------------------------
    |
    | Pick the surface that matches your architecture (or "all" while
    | migrating). The mode decides which routes the package registers —
    | change it here or via the LARASAAS_INTEGRATION_MODE env variable,
    | any time, without touching code:
    |
    |  • inertia — Laravel + React in the SAME project (the default).
    |    Publish the React pages/components and use them directly.
    |
    |  • api     — headless. Your client lives elsewhere: a React SPA on
    |    another server, Flutter / iOS / Android… Only the JSON API (and the
    |    Stripe webhook) is registered. You design your own tables & pricing
    |    UI and drive them from the API.
    |
    |  • embed   — same multi-server setup, but you'd rather not rebuild the
    |    UI: the package serves standalone pricing/billing pages you drop in
    |    with an <iframe> (or a mobile WebView). Signed URLs authenticate the
    |    visitor without sharing sessions across servers.
    |
    |  • all     — every surface at once (inertia + api + embed).
    |
    |  • custom  — register nothing but the Stripe webhook. Build your own
    |    routes/pages on top of the LaraSaaS manager (or the `LaraSaaS`
    |    facade) — the "choose your own way" escape hatch.
    |
    */

    'integration' => [
        'mode' => env('LARASAAS_INTEGRATION_MODE', 'inertia'), // inertia | api | embed | all | custom

        'api' => [
            // Browser SPAs on other origins need CORS. List the exact origins
            // (e.g. "https://app.example.com,https://admin.example.com") or "*".
            // Leave empty when clients don't run in a browser (Flutter, native,
            // server-side) or when the SPA shares your origin.
            'cors_origins' => array_filter(array_map('trim', explode(',', (string) env('LARASAAS_API_CORS_ORIGINS', '')))),
        ],

        'embed' => [
            // Who may frame the embed pages — sent as CSP `frame-ancestors`.
            // "*" (default) or a comma-separated list of parent origins.
            'frame_ancestors' => array_filter(array_map('trim', explode(',', (string) env('LARASAAS_EMBED_FRAME_ANCESTORS', '*')))),

            // Lifetime (minutes) of the signed embed URLs from `LaraSaaS::embedUrl()`.
            'token_ttl' => (int) env('LARASAAS_EMBED_TOKEN_TTL', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenant Workspaces & Team Management
    |--------------------------------------------------------------------------
    |
    | Is THIS project multi-tenant? A workspace is a company / team; users
    | join workspaces with a role, and one user may belong to many.
    |
    | LaraSaaS handles workspaces, memberships, roles and invitations.
    | YOUR app still: adds `workspace_id` to its own tables and filters by it,
    | and sends the invitation e-mail (the package only creates the token).
    |
    */

    'multi_tenant' => [

        // Main switch. false = single-tenant: no workspace tables, routes or
        // queries; subscriptions belong to the user.
        // true = /app/workspaces pages + /api/larasaas/workspaces/* API.
        //   LARASAAS_MULTI_TENANT=true
        // ⚠ Set it BEFORE `php artisan migrate`, or the workspace tables are
        //   skipped. Enabling later: `php artisan larasaas:install --multi-tenant`,
        //   then `migrate` (`larasaas:doctor` reports missing tables).
        'enabled' => (bool) env('LARASAAS_MULTI_TENANT', false),

        /*
        | Models: swap them for your own
        |----------------------------------------------------------------------
        |
        | ⚠ YOUR MODEL MUST EXTEND THE PACKAGE MODEL. Do NOT extend
        |   Illuminate\Database\Eloquent\Model directly.
        |
        |   ✓ class Workspace extends \LaraSaaS\Models\Workspace
        |   ✗ class Workspace extends Model
        |
        | Extending keeps:
        |   • the table name: `larasaas_workspaces`, `larasaas_workspace_members`,
        |     `larasaas_workspace_invitations` (the only tables the migration
        |     creates, so don't override $table)
        |   • the relations: members(), invitations(), owner(), workspace(), user()
        |   • the role helpers: hasRole(), isOwner(), isAdmin()
        | Without it you get a missing `workspaces` table, missing relations, or
        | a TypeError from the package internals.
        |
        | Example: add your own relations and Cashier billing to the workspace
        |
        |   // app/Models/Workspace.php
        |   class Workspace extends \LaraSaaS\Models\Workspace
        |   {
        |       use \Laravel\Cashier\Billable;   // only for billing.billable = workspace
        |
        |       public function projects() { return $this->hasMany(Project::class); }
        |   }
        |
        |   // .env
        |   LARASAAS_WORKSPACE_MODEL=App\Models\Workspace
        |
        */

        // The company / team.             Extend \LaraSaaS\Models\Workspace
        'workspace_model' => env('LARASAAS_WORKSPACE_MODEL', \LaraSaaS\Models\Workspace::class),

        // User + workspace + role.         Extend \LaraSaaS\Models\WorkspaceMember
        'membership_model' => env('LARASAAS_MEMBERSHIP_MODEL', \LaraSaaS\Models\WorkspaceMember::class),

        // Pending invite (e-mail, role, token, expiry). Extend \LaraSaaS\Models\WorkspaceInvitation
        'invitation_model' => env('LARASAAS_INVITATION_MODEL', \LaraSaaS\Models\WorkspaceInvitation::class),

        /*
        | Roles
        |----------------------------------------------------------------------
        |
        |   key      → saved in the database (max 32 characters)
        |   label    → what users see. Rename freely: 'label' => 'Manager'
        |   implies  → roles it ALSO counts as. An owner passes
        |              LaraSaaS::hasWorkspaceRole($ws, $user, 'member').
        |
        | ⚠ Do NOT remove or rename the keys `owner`, `admin`, `member`.
        |   The built-in rules use them: only owners change roles, admins
        |   can't invite owners, the last owner can't be removed.
        |
        | ⚠ `implies` is ONE level only. List EVERY lower role:
        |   ✓ 'owner' => [..., 'implies' => ['admin', 'member']]
        |   ✗ 'owner' => [..., 'implies' => ['admin']]   // owner is NOT a member!
        |
        | ✓ You can add your own roles. They get no built-in permissions; check
        |   them yourself:
        |   'billing' => ['label' => 'Billing', 'implies' => ['member']],
        |   LaraSaaS::hasWorkspaceRole($workspace, $user, 'billing');
        |
        */
        'roles' => [
            'owner'  => ['label' => 'Owner',  'implies' => ['admin', 'member']],
            'admin'  => ['label' => 'Admin',  'implies' => ['member']],
            'member' => ['label' => 'Member', 'implies' => []],
        ],

        /*
        | Invitations
        |----------------------------------------------------------------------
        */

        // Role used when an invitation is CREATED without one. Must be a key of `roles`.
        //   LaraSaaS::inviteMember($ws, $me, 'sara@acme.com');          → member
        //   LaraSaaS::inviteMember($ws, $me, 'sara@acme.com', 'admin'); → admin
        'default_role' => 'member',

        // Days before an invite link stops working. Applies to NEW invitations;
        // existing ones keep the expiry they were created with.
        //   7 → invited on the 1st, link dead on the 8th
        'invitation_expires_days' => 7,

        // Who may accept an invitation (the user must be logged in either way):
        //   true  → only the invited e-mail (case-insensitive). sara@acme.com
        //           invited, ali@acme.com clicks the link → 403.
        //   false → anyone with the link can join, like a "share invite link"
        //           in Slack. Treat the link as a secret.
        'strict_invitation_email' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing — Laravel Cashier + Stripe
    |--------------------------------------------------------------------------
    */

    'billing' => [

        // Who pays: 'user' (each person) or 'workspace' (the whole company).
        // ⚠ Set BEFORE `migrate`: the Stripe columns go on that model's table.
        // The model must `use Laravel\Cashier\Billable`.
        // Cashier is pointed at this model automatically (see `user_model`
        // above) — you do NOT need Cashier::useCustomerModel() anymore. Call it
        // yourself only to bill a model LaraSaaS does not know about, from
        // AppServiceProvider::boot(): host providers boot after package
        // providers, so your call has the final say.
        'billable' => env('LARASAAS_BILLABLE', 'user'),

        // Paying workspace class. Leave null to reuse multi_tenant.workspace_model.
        'workspace_model' => env('LARASAAS_WORKSPACE_MODEL'),

        // Which workspace pays when a user belongs to several. Leave null to use
        // $user->currentWorkspace() if it exists, else the most recently joined.
        //   [\App\Support\CurrentWorkspace::class, 'for']   // static method
        // ⚠ No closures here: they break `php artisan config:cache`.
        'workspace_resolver' => null,

        // Cashier subscription name (subscriptions.type). Change only to match
        // existing Cashier data, e.g. 'main'.
        // ⚠ Never change it after you have subscribers: they'd look unsubscribed.
        'subscription' => 'default',

        // Free trial at Checkout (0 = none). A plan's own "Trial days" wins.
        // First subscription only: re-subscribing never earns a second trial.
        'trial_days' => 14,

        // Show the "Add promotion code" box at Checkout. Codes are created in
        // the Stripe Dashboard (Products → Coupons), e.g. LAUNCH50.
        'allow_promotion_codes' => true,

        // Ask business customers for a VAT/tax ID at Checkout (EU B2B).
        // Collects the number only; tax calculation is Stripe Tax.
        'collect_tax_ids' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing display settings & developer-defined plan features
    |--------------------------------------------------------------------------
    |
    | Your dashboard calls LaraSaaS::createPlan(); no plan data lives here.
    | These settings control how the database-backed catalog is displayed.
    |
    */

    // Developer-owned entitlement catalog, separate from Pennant rollout flags.
    // Admins can set values only for these keys; they cannot invent features.
    'plan_features' => [
        'uploads' => ['label' => 'File uploads', 'type' => 'boolean', 'default' => false],
        'storage_gb' => ['label' => 'Storage', 'type' => 'integer', 'unit' => 'GB', 'default' => 0, 'allow_unlimited' => true],
    ],

    // Host login/registration URL used by public pricing pages.
    'login_url' => '/login',

    'pricing' => [
        'default_currency'     => 'usd',
        'default_interval'     => 'monthly',
        'intervals'            => ['monthly', 'yearly'],
        'currencies'           => ['usd' => '$', 'eur' => '€', 'sar' => 'ر.س'],
        'yearly_savings_badge' => '2 months free',
        'contact_sales'        => ['enabled' => true, 'label' => 'Talk to sales', 'url' => 'mailto:sales@example.com'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Announcements
    |--------------------------------------------------------------------------
    |
    | Banners admins post from /app/admin/announcements, shown by the React
    | <AnnouncementStack />. Scheduling, audiences and dismissals are built in.
    |
    */

    'announcements' => [

        // How often the banner checks for new announcements (seconds).
        // Minimum 10. 0 = load once on page load, no polling.
        'poll_seconds' => 60,

        // Types offered in the admin form. Each gives a default color + icon;
        // admins can still override the color per announcement.
        // Icons: info, check-circle, alert-triangle, alert-octagon, sparkles,
        //        check, x, external (unknown names fall back to info).
        //   'maintenance' => ['color' => '#64748b', 'icon' => 'alert-triangle'],
        'types' => [
            'info'    => ['color' => '#3b82f6', 'icon' => 'info'],
            'success' => ['color' => '#22c55e', 'icon' => 'check-circle'],
            'warning' => ['color' => '#f59e0b', 'icon' => 'alert-triangle'],
            'danger'  => ['color' => '#ef4444', 'icon' => 'alert-octagon'],
            'promo'   => ['color' => '#a855f7', 'icon' => 'sparkles'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags — Laravel Pennant, config resolved
    |--------------------------------------------------------------------------
    |
    | Strategies per flag: `enabled` (bool), `percentage` (0-100, stable
    | bucketing), `workspaces` (ids), `emails`, or a `resolve` closure.
    | Scope is the authenticated user by default; call Feature::for($workspace)
    | for workspace-specific rollouts.
    |
    */

    'features' => [
        'ai-assistant'  => ['enabled' => false],
        'new-dashboard' => ['percentage' => 50],
        'beta-reports'  => ['workspaces' => []],
        'concierge'     => ['emails' => []],
        // 'labs' => ['resolve' => fn ($scope) => $scope?->email === 'founder@yourapp.test'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Look & Feel / i18n
    |--------------------------------------------------------------------------
    |
    | Components are headless-friendly: they read your app's CSS variables,
    | follow your `.dark` class automatically, and ship translated strings.
    |
    */

    'theme' => [
        'default' => 'system', // system | light | dark (hint only — host wins)
        'accent'  => '#ff2d20', // --larasaas-accent, overridable from your CSS
    ],

    // Text follows your app's locale (app()->getLocale()); missing languages
    // use your app.fallback_locale. Add one: php artisan vendor:publish --tag=larasaas-lang
    //
    // Languages written right-to-left. Region variants match: ar_SA → ar.
    'rtl_locales' => ['ar', 'he', 'fa', 'ur'],

    // Directory (inside resources/js) where `vendor:publish --tag=larasaas-react`
    // places the pages/components, and how Inertia page names are resolved.
    'inertia' => [
        'pages_dir' => 'LaraSaaS',
    ],
];
