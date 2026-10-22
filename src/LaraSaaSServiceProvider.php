<?php

namespace LaraSaaS;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaraSaaS\Console\InstallCommand;
use LaraSaaS\Http\Middleware\EnsureSubscribed;
use LaraSaaS\Http\Middleware\RequireFeature;
use LaraSaaS\Support\ConfigDrivenFeature;
use Laravel\Pennant\Feature;

class LaraSaaSServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/larasaas.php', 'larasaas');

        $this->app->singleton(LaraSaaS::class, fn () => new LaraSaaS);
        $this->app->alias(LaraSaaS::class, 'larasaas');

        // Cashier's static customer model defaults to a hardcoded App\Models\User
        // and is what every Stripe webhook lookup goes through. Point it at the
        // configured billable BEFORE the host's providers boot, so a host with
        // `Cashier::useCustomerModel(...)` in AppServiceProvider::boot() keeps
        // the final say.
        $this->app->make(LaraSaaS::class)->syncCashierCustomerModel();
    }

    public function boot(): void
    {
        $this->registerCashierCustomerModel();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerGate();
        $this->registerPennantFeatures();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->alertOnConfigDrift();
    }

    /**
     * Second sync pass: hosts that set `larasaas.user_model` (or switch
     * `billing.billable` to workspace) from their own provider register/boot
     * configure it AFTER our register() ran. Anything the host sets later —
     * `Cashier::useCustomerModel()` in AppServiceProvider::boot() — still wins,
     * because every host provider boots after every package provider.
     */
    protected function registerCashierCustomerModel(): void
    {
        $this->app->make(LaraSaaS::class)->syncCashierCustomerModel();
    }

    protected function registerRoutes(): void
    {
        /** @var LaraSaaS $larasaas */
        $larasaas = $this->app->make(LaraSaaS::class);

        // Web UI — Inertia pages, checkout redirects, billing portal.
        // (integration.mode: inertia | all)
        if ($larasaas->servesInertia()) {
            Route::group([
                'domain' => config('larasaas.route.domain'),
                'prefix' => config('larasaas.route.prefix', 'app'),
                'middleware' => array_merge(config('larasaas.route.public_middleware', ['web']), [
                    \LaraSaaS\Http\Middleware\ShareLaraSaaSProps::class,
                ]),
                'as' => config('larasaas.route.name_prefix', 'larasaas.'),
            ], function () {
                Route::get('/pricing', [\LaraSaaS\Http\Controllers\BillingController::class, 'pricing'])->name('pricing');
            });
            Route::group([
                'domain'     => config('larasaas.route.domain'),
                'prefix'     => config('larasaas.route.prefix', 'app'),
                'middleware' => config('larasaas.route.middleware', ['web', 'auth']),
                'as'         => config('larasaas.route.name_prefix', 'larasaas.'),
            ], function () use ($larasaas) {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

                // Workspaces & teams — only for multi-tenant installs.
                if ($larasaas->multiTenant()) {
                    $this->loadRoutesFrom(__DIR__ . '/../routes/workspaces-web.php');
                }
            });
        }

        // JSON API — consumed by the shipped React components and by any
        // headless client (separate SPA, Flutter, native).
        // (integration.mode: inertia | api | embed | all)
        if ($larasaas->servesApi()) {
            $middleware = config('larasaas.route.api_middleware', ['web', 'auth']);
            $publicMiddleware = array_merge(config('larasaas.route.public_api_middleware', ['web']), [
                \LaraSaaS\Http\Middleware\OptionalPricingUser::class,
            ]);

            if (config('larasaas.integration.api.cors_origins')) {
                array_unshift($middleware, \LaraSaaS\Http\Middleware\ApiCors::class);
                array_unshift($publicMiddleware, \LaraSaaS\Http\Middleware\ApiCors::class);
            }

            Route::group([
                'domain'     => config('larasaas.route.domain'),
                'prefix'     => config('larasaas.route.api_prefix', 'api/larasaas'),
                'middleware' => $middleware,
                'as'         => config('larasaas.route.name_prefix', 'larasaas.') . 'api.',
            ], function () use ($larasaas) {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

                // Workspaces & teams — only for multi-tenant installs.
                if ($larasaas->multiTenant()) {
                    $this->loadRoutesFrom(__DIR__ . '/../routes/workspaces-api.php');
                }
            });
            Route::group([
                'domain' => config('larasaas.route.domain'),
                'prefix' => config('larasaas.route.api_prefix', 'api/larasaas'),
                'middleware' => $publicMiddleware,
                'as' => config('larasaas.route.name_prefix', 'larasaas.') . 'api.',
            ], function () {
                Route::get('/pricing', [\LaraSaaS\Http\Controllers\Api\BillingApiController::class, 'pricing'])->name('pricing');
                Route::options('/{path}', fn () => response('', 204))->where('path', '.*');
            });
        }

        // Embed UI — standalone pages for <iframe> / mobile WebView.
        // (integration.mode: embed | all)
        if ($larasaas->servesEmbed()) {
            $embedMiddleware = config('larasaas.route.embed_middleware', [
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ]);

            $embedMiddleware = array_merge($embedMiddleware, [
                \LaraSaaS\Http\Middleware\EmbedFrameHeaders::class,
            ]);

            Route::group([
                'domain'     => config('larasaas.route.domain'),
                'prefix'     => config('larasaas.route.embed_prefix', 'embed'),
                'middleware' => $embedMiddleware,
                'as'         => config('larasaas.route.name_prefix', 'larasaas.') . 'embed.',
            ], function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/embed.php');
            });
        }

        // Stripe webhook — public, signature verified by Cashier middleware.
        // Always registered, whatever the integration mode.
        Route::group(['middleware' => ['api']], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhook.php');
        });
    }

    protected function registerResources(): void
    {
        // Migrations are published by larasaas:install; the host owns execution.
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'larasaas');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'larasaas');
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/larasaas.php' => config_path('larasaas.php'),
        ], 'larasaas-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php'
                => database_path('migrations/2026_10_14_000000_create_larasaas_schema.php'),
        ], 'larasaas-migrations');

        // Optional multi-tenant workspace & team schema — also published by
        // `larasaas:install` when the project is multi-tenant.
        $this->publishes([
            __DIR__ . '/../database/migrations/2026_10_14_000001_create_larasaas_workspaces_schema.php'
                => database_path('migrations/2026_10_14_000001_create_larasaas_workspaces_schema.php'),
        ], 'larasaas-workspaces');

        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/' . trim(config('larasaas.inertia.pages_dir', 'LaraSaaS'), '/')),
        ], 'larasaas-react');

        $this->publishes([
            __DIR__ . '/../resources/lang' => lang_path('vendor/larasaas'),
        ], 'larasaas-lang');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/larasaas'),
        ], 'larasaas-views');
    }

    protected function registerGate(): void
    {
        Gate::define('larasaas-admin', function ($user) {
            return app(LaraSaaS::class)->isAdmin($user);
        });
    }

    protected function registerPennantFeatures(): void
    {
        if (! class_exists(Feature::class)) {
            return;
        }

        foreach (app(LaraSaaS::class)->featureDefinitions() as $name => $definition) {
            Feature::define($name, fn ($scope) => (new ConfigDrivenFeature($name, $definition))->resolve($scope));
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('larasaas.subscribed', EnsureSubscribed::class);
        $router->aliasMiddleware('larasaas.feature', RequireFeature::class);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                \LaraSaaS\Console\DoctorCommand::class,
            ]);
        }
    }

    /**
     * One log warning per boot when config drifted from the database schema
     * (e.g. `larasaas.user_model` was changed after migrating) — so the
     * developer notices even without running `larasaas:doctor`.
     */
    protected function alertOnConfigDrift(): void
    {
        static $alerted = false;

        if ($alerted || $this->app->runningUnitTests() || $this->app->runningInConsole()) {
            return;
        }

        $alerted = true;

        foreach (app(\LaraSaaS\Support\SetupDoctor::class)->issues() as $issue) {
            \Illuminate\Support\Facades\Log::warning('LaraSaaS setup alert: ' . $issue);
        }
    }
}
