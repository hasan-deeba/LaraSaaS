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
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerGate();
        $this->registerPennantFeatures();
        $this->registerMiddleware();
        $this->registerCommands();
    }

    protected function registerRoutes(): void
    {
        // Web UI — Inertia pages, checkout redirects, billing portal.
        Route::group([
            'domain'     => config('larasaas.route.domain'),
            'prefix'     => config('larasaas.route.prefix', 'app'),
            'middleware' => config('larasaas.route.middleware', ['web', 'auth']),
            'as'         => config('larasaas.route.name_prefix', 'larasaas.'),
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });

        // JSON API consumed by the React components.
        Route::group([
            'domain'     => config('larasaas.route.domain'),
            'prefix'     => config('larasaas.route.api_prefix', 'api/larasaas'),
            'middleware' => config('larasaas.route.api_middleware', ['web', 'auth']),
            'as'         => config('larasaas.route.name_prefix', 'larasaas.') . 'api.',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });

        // Stripe webhook — public, signature verified by Cashier middleware.
        Route::group(['middleware' => ['api']], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhook.php');
        });
    }

    protected function registerResources(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
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
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'larasaas-migrations');

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
            ]);
        }
    }
}
