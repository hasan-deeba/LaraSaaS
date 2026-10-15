<?php

namespace LaraSaaS\Console;

use Illuminate\Console\Command;
use LaraSaaS\Support\SetupDoctor;

/**
 * `php artisan larasaas:doctor` — checks that config/larasaas.php and the
 * database schema still agree, and shouts when they don't (for example when
 * the model in `larasaas.user_model` was changed after migrating).
 */
class DoctorCommand extends Command
{
    protected $signature = 'larasaas:doctor';

    protected $description = 'Check the LaraSaaS setup: configured models, billable columns and multi-tenant schema, with loud alerts on config drift';

    public function handle(SetupDoctor $doctor): int
    {
        $this->components->info('LaraSaaS setup doctor');

        $hasProblems = false;

        foreach ($doctor->report() as $check) {
            $status = match ($check['level']) {
                'error'   => '<fg=red;options=bold>ERROR</>',
                'warning' => '<fg=yellow;options=bold>WARN </>',
                default   => '<fg=green>OK   </>',
            };

            if (! $check['ok']) {
                $hasProblems = true;
            }

            $this->line("  [{$status}] {$check['message']}");
        }

        $this->newLine();

        if ($hasProblems) {
            $this->components->warn('Some checks need your attention — fix them to keep billing and workspaces reliable.');
        } else {
            $this->components->info('Everything looks healthy.');
        }

        return self::SUCCESS;
    }
}
