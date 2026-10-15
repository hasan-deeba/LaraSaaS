<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LaraSaaS\Support\BillingSchema;

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
            $table->unsignedBigInteger('user_id')->index(); // references the host user model
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });

        /*
        |----------------------------------------------------------------------
        | Multi-tenant workspaces & team management
        |----------------------------------------------------------------------
        | user_id / invited_by reference the host user model (config
        | larasaas.user_model) on purpose — no foreign key, same convention as
        | the dismissals table above.
        */

        Schema::create('larasaas_workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->string('slug', 140)->unique();
            $table->unsignedBigInteger('owner_id')->index(); // references the host user model
            $table->timestamps();
        });

        Schema::create('larasaas_workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('larasaas_workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index(); // references the host user model
            $table->string('role', 20)->default('member'); // owner | admin | member
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('larasaas_workspace_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('larasaas_workspaces')->cascadeOnDelete();
            $table->string('email', 190)->index();
            $table->string('role', 20)->default('member'); // admin | member
            $table->string('token', 80)->unique();
            $table->unsignedBigInteger('invited_by')->nullable()->index(); // references the host user model
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'email']);
        });

        /*
        |----------------------------------------------------------------------
        | Laravel Cashier customer columns — on the CONFIGURED table
        |----------------------------------------------------------------------
        | ⚠ NOT hardcoded to `users`: the columns land on the table of the
        | model configured in config/larasaas.php (`larasaas.user_model`, or
        | `larasaas.billing.workspace_model` / `larasaas.workspaces.model` when
        | billing a workspace). Added after the LaraSaaS tables exist so the
        | configured workspace table is always available, and idempotent so a
        | host that already ran Cashier's own migration keeps working.
        |
        | 🚨 ALERT — if you CHANGE the model in the config AFTER migrating, the
        | columns stay on the previous table. Run `php artisan larasaas:doctor`
        | after every config change and follow its alerts (README: "Changing
        | the configured model"). `down()` intentionally leaves the columns in
        | place on host tables — it never destroys billing data.
        */

        $schema = new BillingSchema;
        $table = $schema->billableTable();

        if (! Schema::hasTable($table)) {
            echo PHP_EOL . "  ⚠ LaraSaaS: table [{$table}] of the configured billable model [{$schema->billableModel()}] does not exist yet;" . PHP_EOL
                . "    the Cashier customer columns (stripe_id, pm_type, pm_last_four, trial_ends_at) were NOT added." . PHP_EOL
                . '    Create the table (or fix `larasaas.user_model` / `larasaas.billing.*` in config/larasaas.php), then run' . PHP_EOL
                . "    `php artisan larasaas:doctor` and follow its alerts." . PHP_EOL;
        } else {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $schema) {
                foreach ($schema->requiredColumns() as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        match ($column) {
                            'stripe_id' => $blueprint->string('stripe_id')->nullable()->index(),
                            'pm_type' => $blueprint->string('pm_type')->nullable(),
                            'pm_last_four' => $blueprint->string('pm_last_four', 4)->nullable(),
                            'trial_ends_at' => $blueprint->timestamp('trial_ends_at')->nullable(),
                            default => null,
                        };
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('larasaas_workspace_invitations');
        Schema::dropIfExists('larasaas_workspace_members');
        Schema::dropIfExists('larasaas_workspaces');
        Schema::dropIfExists('larasaas_plans');
        Schema::dropIfExists('larasaas_announcement_dismissals');
        Schema::dropIfExists('larasaas_announcements');
    }
};
