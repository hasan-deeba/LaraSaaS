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
    */

    'user_model' => env('LARASAAS_USER_MODEL', 'App\\Models\\User'),

    // E-mails allowed to open the admin panels. When empty, access defers to
    // the `larasaas-admin` Gate you may define in your own application.
    'admin_emails' => array_filter(array_map('trim', explode(',', (string) env('LARASAAS_ADMIN_EMAILS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | Every URL, middleware stack, domain and route name is yours to change.
    |
    */

    'route' => [
        'domain'           => env('LARASAAS_DOMAIN'),                  // e.g. billing.yourapp.test
        'prefix'           => env('LARASAAS_PREFIX', 'app'),           // web UI  → /app/pricing
        'api_prefix'       => env('LARASAAS_API_PREFIX', 'api/larasaas'),
        'webhook_path'     => env('LARASAAS_WEBHOOK_PATH', 'larasaas/webhooks/stripe'),
        'name_prefix'      => 'larasaas.',
        'middleware'       => ['web', 'auth'],
        'api_middleware'   => ['web', 'auth'],
        'admin_middleware' => ['web', 'auth', 'can:larasaas-admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing — Laravel Cashier + Stripe
    |--------------------------------------------------------------------------
    |
    | `billable` decides where subscriptions live: "user" attaches them to
    | the User model; "workspace" resolves a team/workspace through the
    | resolver below, so each workspace gets its own subscription & portal.
    |
    */

    'billing' => [
        'billable'             => env('LARASAAS_BILLABLE', 'user'), // user | workspace
        'workspace_model'      => env('LARASAAS_WORKSPACE_MODEL'),  // e.g. App\Models\Workspace
        'workspace_resolver'   => null,                             // fn ($user) => $user->currentWorkspace
        'subscription'         => 'default',
        'trial_days'           => 14,
        'allow_promotion_codes'=> true,
        'collect_tax_ids'      => false,
        'prices_source'        => 'config',                         // config | stripe (live lookup)
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Plans — 100% config driven
    |--------------------------------------------------------------------------
    |
    | Every card in the React pricing table is generated from this array.
    | Add, remove, rename, highlight — the UI follows. Prices may live here
    | (`price`) or be pulled live from Stripe (`prices_source => stripe`).
    |
    */

    'pricing' => [
        'default_currency'     => 'usd',
        'default_interval'     => 'monthly',
        'intervals'            => ['monthly', 'yearly'],
        'currencies'           => ['usd' => '$', 'eur' => '€', 'sar' => 'ر.س'],
        'yearly_savings_badge' => '2 months free',
        'contact_sales'        => ['enabled' => true, 'label' => 'Talk to sales', 'url' => 'mailto:sales@example.com'],

        'plans' => [
            'starter' => [
                'name'        => 'Starter',
                'description' => 'For side-projects and weekend hacks.',
                'highlight'   => false,
                'badge'       => null,
                'trial_days'  => 7,
                'monthly'     => ['price' => 9,  'stripe_id' => env('STRIPE_PRICE_STARTER_MONTHLY')],
                'yearly'      => ['price' => 90, 'stripe_id' => env('STRIPE_PRICE_STARTER_YEARLY')],
                'features'    => ['1 workspace', '3 projects', 'Community support', 'Basic analytics'],
                'limits'      => ['seats' => 1, 'projects' => 3],
            ],

            'growth' => [
                'name'        => 'Growth',
                'description' => 'For teams shipping fast and measuring everything.',
                'highlight'   => true,
                'badge'       => 'Most popular',
                'trial_days'  => 14,
                'monthly'     => ['price' => 29,  'stripe_id' => env('STRIPE_PRICE_GROWTH_MONTHLY')],
                'yearly'      => ['price' => 290, 'stripe_id' => env('STRIPE_PRICE_GROWTH_YEARLY')],
                'features'    => ['5 workspaces', 'Unlimited projects', 'Priority support', 'Advanced analytics', 'Custom domains'],
                'limits'      => ['seats' => 10, 'projects' => -1],
            ],

            'scale' => [
                'name'        => 'Scale',
                'description' => 'For companies with compliance & scale needs.',
                'highlight'   => false,
                'badge'       => null,
                'trial_days'  => 30,
                'monthly'     => ['price' => 79,  'stripe_id' => env('STRIPE_PRICE_SCALE_MONTHLY')],
                'yearly'      => ['price' => 790, 'stripe_id' => env('STRIPE_PRICE_SCALE_YEARLY')],
                'features'    => ['Unlimited workspaces', 'SSO / SAML', 'Audit log', '99.9% SLA', 'Dedicated success manager'],
                'limits'      => ['seats' => -1, 'projects' => -1],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Announcements
    |--------------------------------------------------------------------------
    |
    | Global broadcasts with colored typings. Each type maps to a color and
    | icon the React <AnnouncementStack /> consumes. Scheduling, audiences
    | and dismissal persistence are all handled by the package.
    |
    */

    'announcements' => [
        'poll_seconds' => 60,
        'history_days' => 365,
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

    'locales' => ['en', 'fr', 'ar'],
    'locale'  => null, // null → app()->getLocale() of the host project

    // Directory (inside resources/js) where `vendor:publish --tag=larasaas-react`
    // places the pages/components, and how Inertia page names are resolved.
    'inertia' => [
        'pages_dir' => 'LaraSaaS',
    ],
];
