<?php

namespace LaraSaaS\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects drift between `config/larasaas.php` and the database schema, so the
 * developer gets a loud alert when — for example — the model in
 * `larasaas.user_model` is changed after the migrations have already run.
 *
 * Surfaced by `php artisan larasaas:doctor`, by `larasaas:install` and by a
 * one-time log warning on boot.
 */
class SetupDoctor
{
    /**
     * Every check with its outcome.
     *
     * @return array<int, array{check: string, ok: bool, level: string, message: string}>
     */
    public function report(): array
    {
        return array_merge(
            $this->modelChecks(),
            $this->driftChecks(),
            $this->columnChecks(),
            $this->multiTenantChecks(),
        );
    }

    /** Human-readable problems only (empty array = healthy). */
    public function issues(): array
    {
        return array_values(array_map(
            fn (array $check) => $check['message'],
            array_filter($this->report(), fn (array $check) => ! $check['ok']),
        ));
    }

    /** @return array<int, array{check: string, ok: bool, level: string, message: string}> */
    protected function modelChecks(): array
    {
        $checks = [];
        $userModel = (string) config('larasaas.user_model', 'App\\Models\\User');

        if (! class_exists($userModel)) {
            $checks[] = [
                'check' => 'user_model',
                'ok' => false,
                'level' => 'error',
                'message' => "The configured user model [{$userModel}] does not exist. Set larasaas.user_model in config/larasaas.php to your model class.",
            ];

            return $checks;
        }

        $checks[] = [
            'check' => 'user_model',
            'ok' => true,
            'level' => 'info',
            'message' => "User model [{$userModel}] resolves to table [" . (new $userModel)->getTable() . '].',
        ];

        return $checks;
    }

    /** Compare the configuration against what the migrations recorded. */
    protected function driftChecks(): array
    {
        if (! Schema::hasTable('larasaas_settings')) {
            return [[
                'check' => 'migrations',
                'ok' => false,
                'level' => 'warning',
                'message' => 'The LaraSaaS schema is not migrated yet (no larasaas_settings table). Run `php artisan migrate`.',
            ]];
        }

        $stamps = $this->stamps();

        if ($stamps === []) {
            return [[
                'check' => 'migrations',
                'ok' => false,
                'level' => 'warning',
                'message' => 'The LaraSaaS schema was migrated without setup stamps (older baseline). Re-run `php artisan larasaas:doctor` after your next migration to enable drift detection.',
            ]];
        }

        $checks = [];
        $userModel = (string) config('larasaas.user_model', 'App\\Models\\User');
        $userTable = class_exists($userModel) ? (new $userModel)->getTable() : null;

        if (($stamps['user_model'] ?? null) !== $userModel) {
            $checks[] = [
                'check' => 'user_model_drift',
                'ok' => false,
                'level' => 'warning',
                'message' => sprintf(
                    '⚠️ larasaas.user_model changed after the database was migrated: [%s] → [%s]. '
                    . 'The Cashier customer columns (stripe_id, pm_type, pm_last_four, trial_ends_at) were created on table [%s] for the OLD model. '
                    . 'Move them to the new model\'s table [%s] (or point the config back), then confirm with `php artisan larasaas:doctor`.',
                    $stamps['user_model'] ?? 'none',
                    $userModel,
                    $stamps['user_table'] ?? 'unknown',
                    $userTable ?? 'unknown',
                ),
            ];
        } elseif (($stamps['user_table'] ?? null) !== $userTable && $userTable !== null) {
            $checks[] = [
                'check' => 'user_table_drift',
                'ok' => false,
                'level' => 'warning',
                'message' => sprintf(
                    '⚠️ The configured user model now resolves to table [%s], but the schema was migrated for table [%s]. '
                    . 'The LaraSaaS/Cashier columns live on the old table — move them or update the model config.',
                    $userTable,
                    $stamps['user_table'] ?? 'unknown',
                ),
            ];
        } else {
            $checks[] = [
                'check' => 'user_model_drift',
                'ok' => true,
                'level' => 'info',
                'message' => 'Configured user model matches the model the schema was migrated for.',
            ];
        }

        return $checks;
    }

    /** Are the Cashier customer columns present on the configured billable table? */
    protected function columnChecks(): array
    {
        $billableTable = $this->billableTable();

        if ($billableTable === null) {
            return [];
        }

        if (! Schema::hasTable($billableTable)) {
            return [[
                'check' => 'customer_columns',
                'ok' => false,
                'level' => 'error',
                'message' => "The billable table [{$billableTable}] does not exist. Run `php artisan migrate` (and make sure your own table migrations run first).",
            ]];
        }

        $missing = array_filter(
            ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'],
            fn (string $column) => ! Schema::hasColumn($billableTable, $column),
        );

        if ($missing !== []) {
            return [[
                'check' => 'customer_columns',
                'ok' => false,
                'level' => 'error',
                'message' => sprintf(
                    'The billable table [%s] is missing the Cashier customer columns: %s. Re-run the LaraSaaS migrations or add them manually.',
                    $billableTable,
                    implode(', ', $missing),
                ),
            ]];
        }

        return [[
            'check' => 'customer_columns',
            'ok' => true,
            'level' => 'info',
            'message' => "Cashier customer columns are present on billable table [{$billableTable}].",
        ]];
    }

    /** @return array<int, array{check: string, ok: bool, level: string, message: string}> */
    protected function multiTenantChecks(): array
    {
        $enabled = (bool) config('larasaas.multi_tenant.enabled');
        $hasSchema = Schema::hasTable('larasaas_workspaces');

        if ($enabled && ! $hasSchema) {
            return [[
                'check' => 'multi_tenant',
                'ok' => false,
                'level' => 'warning',
                'message' => 'Multi-tenant workspaces are enabled (larasaas.multi_tenant.enabled) but the workspace schema is missing. '
                    . 'Publish it with `php artisan vendor:publish --tag=larasaas-workspaces` and run `php artisan migrate`.',
            ]];
        }

        if (! $enabled && $hasSchema) {
            return [[
                'check' => 'multi_tenant',
                'ok' => false,
                'level' => 'warning',
                'message' => 'Workspace tables exist but multi-tenant workspaces are disabled in config. '
                    . 'Set larasaas.multi_tenant.enabled (or LARASAAS_MULTI_TENANT=true) to activate workspace & team features.',
            ]];
        }

        return [[
            'check' => 'multi_tenant',
            'ok' => true,
            'level' => 'info',
            'message' => $enabled
                ? 'Multi-tenant workspaces are enabled and the workspace schema is present.'
                : 'Single-tenant install (multi-tenant workspaces disabled).',
        ]];
    }

    /** The configured billable model's table — null when it cannot be resolved yet. */
    protected function billableTable(): ?string
    {
        $workspaceBilling = config('larasaas.billing.billable', 'user') === 'workspace';

        $class = $workspaceBilling
            ? (config('larasaas.billing.workspace_model') ?: config('larasaas.multi_tenant.workspace_model'))
            : config('larasaas.user_model', 'App\\Models\\User');

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return null;
        }

        return (new $class)->getTable();
    }

    /** @return array<string, mixed> */
    protected function stamps(): array
    {
        return DB::table('larasaas_settings')->pluck('value', 'key')
            ->map(fn ($value) => json_decode((string) $value, true))
            ->all();
    }
}
