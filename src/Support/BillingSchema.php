<?php

namespace LaraSaaS\Support;

use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Knows where Laravel Cashier's customer columns (stripe_id, pm_type,
 * pm_last_four, trial_ends_at) are supposed to live: on the table of the
 * model configured in `larasaas.user_model` — or of `larasaas.billing.workspace_model`
 * / `larasaas.workspaces.model` when `billing.billable` is "workspace" —
 * never on a hardcoded `users` table.
 *
 * Also powers the developer alerts: when the configured model changes after
 * the columns were migrated, they are left behind on the old table. The
 * problem list below detects exactly that and tells the developer what to do.
 */
class BillingSchema
{
    /** @return array<int, string> */
    public function requiredColumns(): array
    {
        return ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'];
    }

    /** The configured billable model class (user or workspace). */
    public function billableModel(): string
    {
        if (config('larasaas.billing.billable', 'user') === 'workspace') {
            $model = config('larasaas.billing.workspace_model')
                ?: config('larasaas.workspaces.model');

            if (! is_string($model) || $model === '') {
                throw new RuntimeException(
                    'LaraSaaS: `larasaas.billing.billable` is "workspace" but no workspace model is configured. '
                    . 'Set `larasaas.billing.workspace_model` (or `larasaas.workspaces.model`) to the model that owns subscriptions.'
                );
            }

            return $model;
        }

        return (string) config('larasaas.user_model', 'App\\Models\\User');
    }

    /** The table the customer columns belong to — resolved from the configured model. */
    public function billableTable(): string
    {
        $model = $this->billableModel();

        if (! class_exists($model)) {
            throw new RuntimeException(
                "LaraSaaS: configured billable model [{$model}] does not exist. "
                . 'Check `larasaas.user_model` / `larasaas.billing.*` in config/larasaas.php.'
            );
        }

        if (! method_exists($model, 'getTable')) {
            throw new RuntimeException(
                "LaraSaaS: configured billable model [{$model}] is not an Eloquent model (no getTable())."
            );
        }

        return (new $model)->getTable();
    }

    /** @return array<int, string> Columns missing from the configured billable table. */
    public function missingColumns(): array
    {
        try {
            $table = $this->billableTable();
        } catch (RuntimeException) {
            return $this->requiredColumns();
        }

        if (! Schema::hasTable($table)) {
            return $this->requiredColumns();
        }

        return array_values(array_filter(
            $this->requiredColumns(),
            fn (string $column) => ! Schema::hasColumn($table, $column),
        ));
    }

    /**
     * Every table in the database that currently carries the Cashier customer
     * columns — used to detect columns left behind after a config change.
     *
     * @return array<int, string>
     */
    public function tablesWithCustomerColumns(): array
    {
        $tables = [];

        foreach ($this->databaseTables() as $name) {
            try {
                if (! Schema::hasTable($name)) {
                    continue;
                }

                if (Schema::hasColumn($name, 'stripe_id') && Schema::hasColumn($name, 'trial_ends_at')) {
                    $tables[] = $name;
                }
            } catch (\Throwable) {
                // One unreadable table must not kill the drift scan.
            }
        }

        return $tables;
    }

    /** @return array<int, string> Table names, best-effort across Laravel versions/drivers. */
    protected function databaseTables(): array
    {
        $names = [];

        try {
            foreach (Schema::getTables() as $table) {
                $name = is_array($table)
                    ? ($table['name'] ?? null)
                    : (is_object($table) ? ($table->name ?? null) : $table);

                if (is_string($name) && $name !== '') {
                    $names[] = str_contains($name, '.') ? substr($name, (int) strrpos($name, '.') + 1) : $name;
                }
            }
        } catch (\Throwable) {
            // Fall through to the looser listings below.
        }

        try {
            foreach ((array) Schema::getTables() as $table) {
                if (is_object($table)) {
                    $first = reset((array) $table);
                    if (is_string($first) && $first !== '') {
                        $names[] = $first;
                    }
                }
            }
        } catch (\Throwable) {
            // Best-effort only.
        }

        return array_values(array_unique($names));
    }

    /**
     * Developer-facing alerts. Empty array = healthy.
     *
     * @return array<int, string>
     */
    public function problems(): array
    {
        $problems = [];

        try {
            $model = $this->billableModel();
            $table = $this->billableTable();
        } catch (RuntimeException $e) {
            return [$e->getMessage()];
        }

        if (! Schema::hasTable($table)) {
            $problems[] = "⚠ Table [{$table}] of the configured billable model [{$model}] does not exist, so the Cashier "
                . 'customer columns could not be migrated there. Create the table (or fix `larasaas.user_model` / '
                . '`larasaas.billing.*` in config/larasaas.php), then add the columns with a new migration '
                . '(or roll back to before the LaraSaaS schema and re-run `php artisan migrate`). '
                . 'If you changed the configured model, the columns may live on the previous model\'s table.';

            return $problems;
        }

        $missing = $this->missingColumns();

        if ($missing !== []) {
            $others = array_values(array_diff($this->tablesWithCustomerColumns(), [$table]));

            if ($others !== []) {
                $problems[] = '⚠ CONFIG CHANGED? The Cashier customer columns (' . implode(', ', $this->requiredColumns()) . ') live on ['
                    . implode('], [', $others) . '], but `larasaas.billing.billable`/`larasaas.user_model` now resolve [' . $model . '] → table [' . $table . '] '
                    . '(missing here: ' . implode(', ', $missing) . '). '
                    . 'If you changed the model in config after migrating, the columns were left on the previous model\'s table: '
                    . 'move them (see README "Changing the configured model") or revert the config.';
            } else {
                $problems[] = "Table [{$table}] is missing the Cashier customer columns: " . implode(', ', $missing)
                    . '. They are published by `php artisan larasaas:install` (config-aware Cashier migration) — '
                    . 'if you changed `larasaas.user_model` / `larasaas.billing.*` after running migrations, add them to the new table (see README).';
            }
        }

        foreach ($this->stockMigrationConflicts($table) as $conflict) {
            $problems[] = $conflict;
        }

        return $problems;
    }

    /**
     * Published Cashier `create_customer_columns` migrations that still target
     * a hardcoded `users` table while the configured model resolves elsewhere.
     *
     * @return array<int, string>
     */
    protected function stockMigrationConflicts(string $table): array
    {
        $conflicts = [];
        $paths = glob(database_path('migrations/*create_customer_columns*.php')) ?: [];

        foreach ($paths as $path) {
            $contents = (string) file_get_contents($path);

            if (str_contains($contents, 'billableTable') || str_contains($contents, 'larasaas.user_model')) {
                continue; // config-aware (larasaas:install generated)
            }

            if (preg_match("/Schema::table\\(\\s*'([^']+)'/", $contents, $matches) === 1 && $matches[1] !== $table) {
                $conflicts[] = '⚠ Migration [' . basename($path) . '] still targets the hardcoded [' . $matches[1] . '] table, '
                    . "but the configured billable model resolves [{$table}]. Adapt or delete that migration "
                    . "(larasaas:install generates a config-aware replacement) so the Cashier columns land on [{$table}].";
            }
        }

        return $conflicts;
    }

    public function healthy(): bool
    {
        return $this->problems() === [];
    }
}
