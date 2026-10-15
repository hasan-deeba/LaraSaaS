<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasaas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->boolean('highlight')->default(false);
            $table->string('badge', 80)->nullable();
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->json('monthly')->nullable();
            $table->json('yearly')->nullable();
            $table->json('features');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('larasaas_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 140);
            $table->text('body');
            $table->string('type', 32)->default('info');
            $table->string('color', 9)->nullable();   // hex override for the type color
            $table->string('icon', 60)->nullable();   // icon override for the type icon
            $table->string('action_label', 60)->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('dismissible')->default(true);
            $table->boolean('active')->default(true);
            $table->json('audience')->nullable();     // { "emails": [...], "workspaces": [...] }
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'starts_at', 'ends_at']);
        });

        Schema::create('larasaas_announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('larasaas_announcements')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index(); // references the configured user model (larasaas.user_model)
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });

        // Records how the schema was built so `php artisan larasaas:doctor` can
        // alert the developer when config drifts from the database (e.g. the
        // model in `larasaas.user_model` was changed after migrating).
        Schema::create('larasaas_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        $this->addCustomerColumns();
        $this->stampSetup();
    }

    public function down(): void
    {
        $this->dropCustomerColumns();
        Schema::dropIfExists('larasaas_settings');
        Schema::dropIfExists('larasaas_plans');
        Schema::dropIfExists('larasaas_announcement_dismissals');
        Schema::dropIfExists('larasaas_announcements');
    }

    /**
     * Cashier's customer columns — created on the table of the model the HOST
     * configured (`larasaas.user_model`, or the workspace model when billing
     * billable workspaces), never on a hardcoded `users` table. Idempotent, so
     * it coexists with Cashier's own published migrations.
     */
    protected function addCustomerColumns(): void
    {
        $table = $this->billableTable();

        if ($table === null) {
            return; // workspace billing on a table created later — the workspaces migration adds the columns
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $name = $blueprint->getTable();

            if (! Schema::hasColumn($name, 'stripe_id')) {
                $blueprint->string('stripe_id')->nullable()->index();
            }
            if (! Schema::hasColumn($name, 'pm_type')) {
                $blueprint->string('pm_type')->nullable();
            }
            if (! Schema::hasColumn($name, 'pm_last_four')) {
                $blueprint->string('pm_last_four', 4)->nullable();
            }
            if (! Schema::hasColumn($name, 'trial_ends_at')) {
                $blueprint->timestamp('trial_ends_at')->nullable();
            }
        });
    }

    protected function dropCustomerColumns(): void
    {
        try {
            $table = $this->billableTable(strict: false);
        } catch (RuntimeException $e) {
            return; // config changed or model is gone — nothing sensible to roll back
        }

        if ($table === null || ! Schema::hasTable($table)) {
            return;
        }

        $columns = array_filter(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'], fn ($column) => Schema::hasColumn($table, $column));

        if ($columns === []) {
            return;
        }

        // SQLite refuses to drop a column that still has an index on it.
        if (Schema::hasColumn($table, 'stripe_id')) {
            try {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex(['stripe_id']));
            } catch (Throwable $e) {
                // The index may not exist (columns could predate this migration).
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->dropColumn($columns);
        });
    }

    /**
     * Resolve the billable model's table from config.
     *
     * Returns the table name, or null when the columns must be added later
     * (workspace billing whose table is created by the workspaces migration).
     */
    protected function billableTable(bool $strict = true): ?string
    {
        $workspaceBilling = config('larasaas.billing.billable', 'user') === 'workspace';

        $class = $workspaceBilling
            ? (config('larasaas.billing.workspace_model') ?: config('larasaas.multi_tenant.workspace_model'))
            : config('larasaas.user_model', 'App\\Models\\User');

        if (! is_string($class) || $class === '') {
            throw new RuntimeException(
                'LaraSaaS: no billable model configured. Set larasaas.user_model in config/larasaas.php '
                . '(or larasaas.billing.workspace_model for workspace billing) before migrating.'
            );
        }

        if (! class_exists($class)) {
            throw new RuntimeException(
                "LaraSaaS: the configured billable model [{$class}] does not exist. "
                . 'Set larasaas.user_model (or larasaas.billing.workspace_model) in config/larasaas.php to your model class '
                . '— the customer columns are created on THAT model\'s table, not on a hardcoded `users` table.'
            );
        }

        $table = (new $class)->getTable();

        if (Schema::hasTable($table)) {
            return $table;
        }

        // Workspace billing on a multi-tenant install: the workspaces migration
        // creates the table and adds the customer columns to it.
        if ($workspaceBilling && config('larasaas.multi_tenant.enabled')) {
            return null;
        }

        if (! $strict) {
            return null;
        }

        throw new RuntimeException(
            "LaraSaaS: the billable model's table [{$table}] does not exist yet. "
            . 'Run your own table migrations first (they must run before the LaraSaaS schema migration), then migrate again.'
        );
    }

    /** Stamp which model/table the schema was built for — the drift detector reads this. */
    protected function stampSetup(): void
    {
        $now = now();

        $workspaceBilling = config('larasaas.billing.billable', 'user') === 'workspace';
        $userModel = (string) config('larasaas.user_model', 'App\\Models\\User');
        $userTable = class_exists($userModel) ? (new $userModel)->getTable() : null;

        try {
            $billableModel = $workspaceBilling
                ? (config('larasaas.billing.workspace_model') ?: config('larasaas.multi_tenant.workspace_model'))
                : $userModel;
            $billableTable = $this->billableTable(strict: false);
        } catch (RuntimeException $e) {
            $billableModel = $workspaceBilling
                ? (config('larasaas.billing.workspace_model') ?: config('larasaas.multi_tenant.workspace_model'))
                : $userModel;
            $billableTable = null;
        }

        $rows = [
            'user_model'    => $userModel,
            'user_table'    => $userTable,
            'billable_model' => $billableModel,
            'billable_table' => $billableTable,
            'multi_tenant'  => (bool) config('larasaas.multi_tenant.enabled'),
        ];

        foreach ($rows as $key => $value) {
            DB::table('larasaas_settings')->insert([
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
