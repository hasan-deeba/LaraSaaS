<?php

namespace LaraSaaS\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'larasaas:install
        {--mode= : Integration mode: inertia, api, embed, all or custom}
        {--multi-tenant : Enable multi-tenant workspaces & team management}
        {--no-multi-tenant : Keep the project single-tenant}
        {--migrate : Run the package + cashier migrations right away}
        {--force  : Overwrite previously published files}';

    protected $description = 'Install LaraSaaS: pick an integration mode, publish config, migrations, React components and translations';

    /** @var array<string, string> */
    protected array $modes = [
        'inertia' => 'Laravel + React in the SAME project (Inertia) — publish the React pages and use them directly',
        'api'     => 'Headless — React on another server or Flutter/native apps consume the JSON API and render their own UI',
        'embed'   => 'Multi-server — embed the standalone pricing/billing pages with an <iframe> or mobile WebView',
        'all'     => 'Every surface at once (inertia + api + embed) — useful while migrating',
        'custom'  => 'Register only the Stripe webhook — build your own routes on the LaraSaaS manager',
    ];

    public function handle(): int
    {
        $this->components->info('Installing LaraSaaS…');

        $mode = (string) ($this->option('mode') ?: '');

        if ($mode !== '' && ! array_key_exists($mode, $this->modes)) {
            $this->components->error("Unknown mode [{$mode}] — expected one of: " . implode(', ', array_keys($this->modes)));

            return self::FAILURE;
        }

        if ($mode === '') {
            $choice = $this->components->choice('How will your frontend consume LaraSaaS?', array_map(
                fn (string $key, string $label) => "{$key} — {$label}",
                array_keys($this->modes),
                $this->modes,
            ));

            $mode = explode(' — ', $choice)[0];
        }

        $this->newLine();
        $this->components->twoColumnDetail('Integration mode', "<fg=cyan>{$mode}</>");

        $multiTenant = $this->resolveMultiTenant();
        $this->components->twoColumnDetail(
            'Multi-tenant workspaces',
            $multiTenant ? '<fg=cyan>enabled (workspaces + teams)</>' : '<fg=cyan>disabled (single-tenant)</>',
        );

        $force = $this->option('force') ? ['--force' => true] : [];

        $tags = ['larasaas-config', 'larasaas-lang'];

        // The React/Inertia surface is only needed when the host serves it.
        if (in_array($mode, ['inertia', 'all'], true)) {
            $tags[] = 'larasaas-react';
        }

        foreach ($tags as $tag) {
            $this->callSilently('vendor:publish', ['--tag' => $tag, ...$force]);
            $this->components->twoColumnDetail("Published [{$tag}]", '<fg=green>DONE</>');
        }

        $this->publishMigrations($multiTenant);

        // Cashier needs its migrations in the host app.
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            $this->callSilently('vendor:publish', ['--tag' => 'cashier-migrations']);
            $this->components->twoColumnDetail('Published [cashier-migrations]', '<fg=green>DONE</>');

            // Cashier resolves webhook customers with its own static model, so
            // show the developer exactly which class Stripe events will land on.
            $this->components->twoColumnDetail(
                'Cashier customer model',
                '<fg=cyan>' . app(\LaraSaaS\LaraSaaS::class)->cashierCustomerModel() . '</> (from larasaas config)',
            );
        }

        $this->guardCashierCustomerColumns();

        $this->writeEnv('LARASAAS_INTEGRATION_MODE', $mode);
        $this->writeEnv('LARASAAS_MULTI_TENANT', $multiTenant ? 'true' : 'false');

        if ($this->option('migrate') || $this->components->confirm('Run the migrations now?', true)) {
            if ($this->call('migrate') !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $this->reportSetupAlerts();

        $this->newLine();
        $this->components->info('LaraSaaS is ready. Next steps:');
        $this->line('  1. Add your Stripe keys to .env: <comment>STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET</comment>');
        $this->line('  2. Define <comment>plan_features</comment> in config, then call <comment>LaraSaaS::createPlan($data)</comment> from your protected dashboard');
        $this->line('  3. Point Stripe webhooks at <comment>/larasaas/webhooks/stripe</comment> and exclude it from CSRF');

        $this->line('  4. Add <comment>Laravel\\Cashier\\Billable</comment> to your billable model (user, or workspace for workspace billing; see README Cashier setup)');
        $this->line('     Model not at <comment>App\\Models\\User</comment>? Set <comment>LARASAAS_USER_MODEL=App\\Models\\…</comment> (unquoted!) — LaraSaaS points Cashier at it, so Stripe webhooks find your customers');

        if ($multiTenant) {
            $this->line('  5. Create workspaces with <comment>LaraSaaS::createWorkspace()</comment> and manage teams via <comment>/app/workspaces</comment> (or the JSON API)');
            $this->line('  6. Run <comment>php artisan larasaas:doctor</comment> whenever you change <comment>larasaas.user_model</comment> or the multi-tenant config');
        } else {
            $this->line('  5. Run <comment>php artisan larasaas:doctor</comment> whenever you change <comment>larasaas.user_model</comment> or other model config');
        }

        foreach ($this->nextSteps($mode) as $i => $step) {
            $this->line('  ' . ($i + ($multiTenant ? 7 : 6)) . ". {$step}");
        }

        return self::SUCCESS;
    }

    /** The install-time multi-tenant choice — option, then interactive confirm. */
    protected function resolveMultiTenant(): bool
    {
        if ($this->option('multi-tenant')) {
            return true;
        }

        if ($this->option('no-multi-tenant')) {
            return false;
        }

        return $this->components->confirm(
            'Enable multi-tenant workspaces & team management (Owner/Admin/Member roles, invitations)?',
            (bool) config('larasaas.multi_tenant.enabled'),
        );
    }

    protected function publishMigrations(bool $multiTenant): void
    {
        $directory = database_path('migrations');

        // filename => glob detecting a previously published copy (renamed timestamp included)
        $files = [
            '2026_10_14_000000_create_larasaas_schema.php' => '*_create_larasaas_schema.php',
        ];

        if ($multiTenant) {
            $files['2026_10_14_000001_create_larasaas_workspaces_schema.php'] = '*_create_larasaas_workspaces_schema.php';
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $published = 0;

        foreach ($files as $filename => $pattern) {
            if (glob($directory . '/' . $pattern) !== []) {
                $this->components->info("LaraSaaS migration [{$filename}] already published (left unchanged).");

                continue;
            }

            copy(__DIR__ . '/../../database/migrations/' . $filename, $directory . '/' . $filename);
            $published++;
        }

        if ($published > 0) {
            $this->components->twoColumnDetail("Published {$published} LaraSaaS schema migration(s)", '<fg=green>DONE</>');
        }
    }

    /**
     * Cashier's stock customer-columns migration hardcodes the `users` table
     * and is NOT idempotent — freshly published it is also timestamped "now",
     * so it runs AFTER the LaraSaaS baseline and would crash on duplicate
     * columns. LaraSaaS's schema migration owns these columns on the
     * configured billable table instead, so retire the Cashier copy (asking
     * first) instead of running it blindly.
     */
    protected function guardCashierCustomerColumns(): void
    {
        $files = glob(database_path('migrations/*create_customer_columns.php'));

        if ($files === []) {
            return;
        }

        $table = 'users';

        try {
            $class = config('larasaas.billing.billable', 'user') === 'workspace'
                ? (config('larasaas.billing.workspace_model') ?: config('larasaas.multi_tenant.workspace_model'))
                : config('larasaas.user_model', 'App\\Models\\User');

            if (is_string($class) && $class !== '' && class_exists($class)) {
                $table = (new $class)->getTable();
            }
        } catch (\Throwable $e) {
            // Unresolvable config — the doctor will report it after install.
        }

        $this->components->warn(
            "Cashier's customer-columns migration hardcodes the `users` table and is not idempotent, "
            . "but LaraSaaS creates those columns on [{$table}] via its own schema migration (config-driven, safe to re-run)."
        );

        foreach ($files as $file) {
            if ($this->components->confirm('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', true)) {
                unlink($file);
                $this->components->twoColumnDetail('Removed ' . basename($file), '<fg=green>DONE</>');
            } else {
                $this->components->warn("Keep it only if it targets [{$table}] and runs before the LaraSaaS baseline — or delete it and rely on the LaraSaaS migration.");
            }
        }
    }

    /** Surface config/schema drift (e.g. larasaas.user_model changed) right away. */
    protected function reportSetupAlerts(): void
    {
        $issues = app(\LaraSaaS\Support\SetupDoctor::class)->issues();

        if ($issues === []) {
            return;
        }

        $this->newLine();
        $this->components->warn('LaraSaaS setup alerts (php artisan larasaas:doctor for details):');

        foreach ($issues as $issue) {
            $this->line("  ⚠️  {$issue}");
        }
    }

    protected function writeEnv(string $key, string $value): void
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            $this->components->warn("Could not write {$key} to {$path} — set it manually.");

            return;
        }

        $content = file_get_contents($path);
        $line    = $key . '=' . $value;

        if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $content)) {
            $content = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $content);
        } else {
            $content = rtrim($content) . PHP_EOL . PHP_EOL . $line . PHP_EOL;
        }

        file_put_contents($path, $content);
        $this->components->twoColumnDetail("Set {$key}", '<fg=green>DONE</>');
    }

    /** @return array<int, string> */
    protected function nextSteps(string $mode): array
    {
        return match ($mode) {
            'inertia' => [
                'Register the published pages in your app.tsx resolver (see README)',
                'Import <comment>../js/LaraSaaS/larasaas.css</comment> in your app.css',
                'Visit <comment>/app/pricing</comment>',
            ],
            'api' => [
                'Consume <comment>/api/larasaas/pricing</comment>, <comment>/api/larasaas/subscription</comment>, <comment>/api/larasaas/billing/*</comment> from your SPA or mobile app',
                'Point <comment>route.api_middleware</comment> at your API auth (e.g. auth:sanctum) and set <comment>integration.api.cors_origins</comment> for browser SPAs',
                'Render your own pricing tables from the plan matrix the API returns',
            ],
            'embed' => [
                'Embed public pricing with <comment>LaraSaaS::embedUrl()</comment> — no user required',
                'Generate private billing links with <comment>LaraSaaS::embedUrl($user, \'billing\')</comment> for authenticated users only',
                'Restrict who may frame the pages via <comment>integration.embed.frame_ancestors</comment>',
                'Stripe Checkout & the customer portal open in the top-level window automatically',
            ],
            'all' => [
                'Pick the surface you need per feature: /app/* (Inertia), /api/larasaas/* (JSON), /embed/* (iframe)',
                'Visit <comment>/app/pricing</comment> — or embed <comment>/embed/pricing</comment> today and migrate later',
            ],
            default => [
                'Only the Stripe webhook is registered — build your own routes/pages on the <comment>LaraSaaS</comment> manager or facade',
                'See "Choose your integration" in the README for the manager API',
            ],
        };
    }
}
