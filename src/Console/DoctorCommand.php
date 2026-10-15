<?php

namespace LaraSaaS\Console;

use Illuminate\Console\Command;
use LaraSaaS\Support\BillingSchema;

/**
 * Health-check for the billing schema configuration: verifies that the Cashier
 * customer columns live on the table of the model configured in
 * `larasaas.user_model` / `larasaas.billing.*`, and loudly alerts when that
 * model changed after the columns were migrated.
 */
class DoctorCommand extends Command
{
    protected $signature = 'larasaas:doctor';

    protected $description = 'Check the LaraSaaS billing schema: alerts when the configured user/workspace model changed after migrating';

    public function handle(BillingSchema $schema): int
    {
        $this->components->info('LaraSaaS doctor');

        try {
            $this->components->twoColumnDetail('Billable model', '<fg=cyan>' . $schema->billableModel() . '</>');
            $this->components->twoColumnDetail('Customer columns table', '<fg=cyan>' . $schema->billableTable() . '</>');
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Required columns', implode(', ', $schema->requiredColumns()));

        $problems = $schema->problems();

        if ($problems === []) {
            $this->components->info('Billing schema OK — the Cashier columns match the configured model.');

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($problems as $problem) {
            $this->components->warn($problem);
        }

        $this->newLine();
        $this->components->error('Found ' . count($problems) . ' problem(s). See the README sections "Cashier setup" and "Changing the configured model".');

        return self::FAILURE;
    }
}
