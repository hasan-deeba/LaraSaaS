<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant workspaces & team management schema.
 *
 * Published and run only when `larasaas.multi_tenant.enabled` is true —
 * single-tenant installs never create these tables. Flip the config flag
 * on an existing installation, publish this migration
 * (`vendor:publish --tag=larasaas-workspaces`) and run `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('larasaas.multi_tenant.enabled')) {
            return; // single-tenant install
        }

        Schema::create('larasaas_workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->string('slug', 160)->unique();
            $table->unsignedBigInteger('owner_id')->index(); // references the configured user model (larasaas.user_model)
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('larasaas_workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('larasaas_workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index(); // references the configured user model (larasaas.user_model)
            $table->string('role', 32)->default('member'); // owner | admin | member (see multi_tenant.roles)
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('larasaas_workspace_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('larasaas_workspaces')->cascadeOnDelete();
            $table->string('email', 190);
            $table->string('role', 32)->default('member'); // owner | admin | member
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('invited_by')->nullable()->index(); // references the configured user model
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        $this->addWorkspaceCustomerColumns();
        $this->stampSetup();
    }

    public function down(): void
    {
        if (! config('larasaas.multi_tenant.enabled')) {
            return;
        }

        $this->dropWorkspaceCustomerColumns();
        Schema::dropIfExists('larasaas_workspace_invitations');
        Schema::dropIfExists('larasaas_workspace_members');
        Schema::dropIfExists('larasaas_workspaces');
    }

    /**
     * When subscriptions belong to workspaces, Cashier's customer columns
     * live on the workspace model's table (config-driven, never `users`).
     * Idempotent, so re-running alongside other migrations is safe.
     */
    protected function addWorkspaceCustomerColumns(): void
    {
        if (config('larasaas.billing.billable', 'user') !== 'workspace') {
            return;
        }

        $class = config('larasaas.billing.workspace_model') ?: config('larasaas.multi_tenant.workspace_model');

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            throw new RuntimeException(
                'LaraSaaS: workspace billing requires a workspace model. Set larasaas.multi_tenant.workspace_model '
                . '(or larasaas.billing.workspace_model) in config/larasaas.php before migrating.'
            );
        }

        $table = (new $class)->getTable();

        if (! Schema::hasTable($table)) {
            throw new RuntimeException(
                "LaraSaaS: the workspace billing table [{$table}] does not exist. "
                . 'Create it before this migration (custom workspace models keep their own tables).'
            );
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

    protected function dropWorkspaceCustomerColumns(): void
    {
        if (config('larasaas.billing.billable', 'user') !== 'workspace') {
            return;
        }

        $class = config('larasaas.billing.workspace_model') ?: config('larasaas.multi_tenant.workspace_model');

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return;
        }

        $table = (new $class)->getTable();

        if (! Schema::hasTable($table)) {
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

    /** Update the drift-detection stamps written by the baseline migration. */
    protected function stampSetup(): void
    {
        $now = now();

        $class = config('larasaas.multi_tenant.workspace_model');
        $table = is_string($class) && $class !== '' && class_exists($class) ? (new $class)->getTable() : null;

        $rows = [
            'multi_tenant'   => true,
            'workspace_model' => is_string($class) ? $class : null,
            'workspace_table' => $table,
        ];

        foreach ($rows as $key => $value) {
            $encoded = json_encode($value);
            $existing = DB::table('larasaas_settings')->where('key', $key)->first();

            if ($existing) {
                DB::table('larasaas_settings')->where('key', $key)->update(['value' => $encoded, 'updated_at' => $now]);
            } else {
                DB::table('larasaas_settings')->insert(['key' => $key, 'value' => $encoded, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
};
