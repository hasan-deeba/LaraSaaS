<?php

namespace LaraSaaS\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'larasaas:install
        {--migrate : Run the package + cashier migrations right away}
        {--force  : Overwrite previously published files}';

    protected $description = 'Install LaraSaaS: publish config, migrations, React components and translations';

    public function handle(): int
    {
        $this->components->info('Installing LaraSaaS…');

        $force = $this->option('force') ? ['--force' => true] : [];

        foreach (['larasaas-config', 'larasaas-migrations', 'larasaas-react', 'larasaas-lang'] as $tag) {
            $this->callSilently('vendor:publish', ['--tag' => $tag, ...$force]);
            $this->components->twoColumnDetail("Published [{$tag}]", '<fg=green>DONE</>');
        }

        // Cashier needs its migrations in the host app.
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            $this->callSilently('vendor:publish', ['--tag' => 'cashier-migrations', ...$force]);
            $this->components->twoColumnDetail('Published [cashier-migrations]', '<fg=green>DONE</>');
        }

        if ($this->option('migrate') || $this->components->confirm('Run the migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('LaraSaaS is ready. Next steps:');
        $this->line('  1. Add your Stripe keys to .env: <comment>STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET</comment>');
        $this->line('  2. Set plan price ids in <comment>config/larasaas.php</comment> (or .env STRIPE_PRICE_*)');
        $this->line('  3. Point Stripe webhooks at <comment>/larasaas/webhooks/stripe</comment> and exclude it from CSRF');
        $this->line('  4. Register the published pages in your app.tsx resolver (see README)');
        $this->line('  5. Visit <comment>/app/pricing</comment>');

        return self::SUCCESS;
    }
}
