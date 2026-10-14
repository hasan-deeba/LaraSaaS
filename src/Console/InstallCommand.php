<?php

namespace LaraSaaS\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'larasaas:install
        {--mode= : Integration mode: inertia, api, embed, all or custom}
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

        $this->publishMigrations();

        // Cashier needs its migrations in the host app.
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            $this->callSilently('vendor:publish', ['--tag' => 'cashier-migrations']);
            $this->components->twoColumnDetail('Published [cashier-migrations]', '<fg=green>DONE</>');
        }

        $this->writeEnvMode($mode);

        if ($this->option('migrate') || $this->components->confirm('Run the migrations now?', true)) {
            if ($this->call('migrate') !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->components->info('LaraSaaS is ready. Next steps:');
        $this->line('  1. Add your Stripe keys to .env: <comment>STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET</comment>');
        $this->line('  2. Define <comment>plan_features</comment> in config, then call <comment>LaraSaaS::createPlan($data)</comment> from your protected dashboard');
        $this->line('  3. Point Stripe webhooks at <comment>/larasaas/webhooks/stripe</comment> and exclude it from CSRF');

        $this->line('  4. Add <comment>Laravel\\Cashier\\Billable</comment> to your billable model (user, or workspace for workspace billing; see README Cashier setup)');

        foreach ($this->nextSteps($mode) as $i => $step) {
            $this->line('  ' . ($i + 5) . ". {$step}");
        }

        return self::SUCCESS;
    }

    protected function publishMigrations(): void
    {
        $directory = database_path('migrations');
        $filename = '2026_10_14_000000_create_larasaas_schema.php';
        if (glob($directory . '/*_create_larasaas_schema.php')) {
            $this->components->info('LaraSaaS schema migration already published (left unchanged).');

            return;
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        copy(__DIR__ . '/../../database/migrations/' . $filename, $directory . '/' . $filename);
        $this->components->twoColumnDetail('Published one LaraSaaS schema migration', '<fg=green>DONE</>');
    }

    protected function writeEnvMode(string $mode): void
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            $this->components->warn("Could not write LARASAAS_INTEGRATION_MODE to {$path} — set it manually.");

            return;
        }

        $content = file_get_contents($path);
        $line    = 'LARASAAS_INTEGRATION_MODE=' . $mode;

        if (preg_match('/^LARASAAS_INTEGRATION_MODE=.*$/m', $content)) {
            $content = preg_replace('/^LARASAAS_INTEGRATION_MODE=.*$/m', $line, $content);
        } else {
            $content = rtrim($content) . PHP_EOL . PHP_EOL . $line . PHP_EOL;
        }

        file_put_contents($path, $content);
        $this->components->twoColumnDetail('Set LARASAAS_INTEGRATION_MODE', '<fg=green>DONE</>');
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
