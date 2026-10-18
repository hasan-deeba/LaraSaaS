<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class InstallTest extends TestCase
{
    private string $databaseDirectory;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('larasaas.user_model', InstallUser::class);
        $this->databaseDirectory = sys_get_temp_dir() . '/larasaas-install-' . bin2hex(random_bytes(8));
        $app->useDatabasePath($this->databaseDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->databaseDirectory);
        parent::tearDown();
    }

    private function install(array $options = []): void
    {
        $this->artisan('larasaas:install', ['--mode' => 'api', '--no-multi-tenant' => true, ...$options])
            ->expectsConfirmation('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', 'yes')
            ->expectsConfirmation('Run the migrations now?', 'no')->assertSuccessful()->run();
    }

    public function test_fresh_install_publishes_one_idempotent_schema_with_working_rollback(): void
    {
        $this->install();
        $files = glob(database_path('migrations/*_create_larasaas_schema.php'));
        $this->assertCount(1, $files);
        $this->assertCount(0, glob(database_path('migrations/*_create_larasaas_workspaces_schema.php')));
        $original = file_get_contents($files[0]);
        $this->install();
        $this->assertCount(1, glob(database_path('migrations/*_create_larasaas_schema.php')));
        $this->assertSame($original, file_get_contents($files[0]));

        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });

        $migration = require $files[0];
        $migration->up();
        foreach (['larasaas_plans', 'larasaas_announcements', 'larasaas_announcement_dismissals'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(0, \Illuminate\Support\Facades\DB::table($table)->count());
        }
        $this->assertTrue(Schema::hasTable('larasaas_settings'));
        // Customer columns land on the configured model's table…
        foreach (['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column));
        }
        // …and the migration stamps which model/table it built the schema for.
        $this->assertSame(json_encode(InstallUser::class), $this->setting('user_model'));
        $this->assertSame(json_encode('users'), $this->setting('user_table'));

        $migration->down();
        $this->assertFalse(Schema::hasTable('larasaas_plans'));
        $this->assertFalse(Schema::hasTable('larasaas_announcements'));
        $this->assertFalse(Schema::hasTable('larasaas_announcement_dismissals'));
        $this->assertFalse(Schema::hasTable('larasaas_settings'));
        $this->assertFalse(Schema::hasColumn('users', 'stripe_id'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('larasaas_plans', 'features'));
    }

    /** The core item-1 guarantee: never a hardcoded `users` table. */
    public function test_customer_columns_land_on_the_configured_model_table_not_on_users(): void
    {
        config(['larasaas.user_model' => MembersUser::class]);

        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        Schema::create('members', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });

        $migration = require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php';
        $migration->up();

        foreach (['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('members', $column), "missing [{$column}] on members");
            $this->assertFalse(Schema::hasColumn('users', $column), "unexpected [{$column}] on users");
        }

        $this->assertSame(json_encode(MembersUser::class), $this->setting('user_model'));
        $this->assertSame(json_encode('members'), $this->setting('user_table'));
    }

    /** Workspace billing puts the customer columns on the workspace model's table. */
    public function test_workspace_billing_puts_customer_columns_on_the_workspace_table(): void
    {
        config([
            'larasaas.multi_tenant.enabled' => true,
            'larasaas.billing.billable' => 'workspace',
        ]);

        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });

        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();
        (require __DIR__ . '/../database/migrations/2026_10_14_000001_create_larasaas_workspaces_schema.php')->up();

        foreach (['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('larasaas_workspaces', $column));
            $this->assertFalse(Schema::hasColumn('users', $column));
        }
    }

    /** The requested alert: the doctor shouts when the configured model changes. */
    public function test_doctor_alerts_when_the_user_model_in_config_changed_after_migrating(): void
    {
        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        Schema::create('members', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });

        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();

        // Healthy right after migrating.
        $this->assertSame([], app(\LaraSaaS\Support\SetupDoctor::class)->issues());

        // The developer now points the config at another model…
        config(['larasaas.user_model' => MembersUser::class]);

        $issues = app(\LaraSaaS\Support\SetupDoctor::class)->issues();
        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('larasaas.user_model changed after the database was migrated', implode("\n", $issues));
        $this->assertStringContainsString(InstallUser::class, implode("\n", $issues));
        $this->assertStringContainsString(MembersUser::class, implode("\n", $issues));
        $this->assertStringContainsString('[users]', implode("\n", $issues));

        $this->artisan('larasaas:doctor')
            ->expectsOutputToContain('larasaas.user_model changed after the database was migrated')
            ->assertSuccessful()->run();
    }

    public function test_multi_tenant_install_publishes_the_workspace_schema_and_writes_env(): void
    {
        $env = $this->app->environmentFilePath();
        if (! is_file($env)) {
            File::put($env, "APP_KEY=base64:" . base64_encode(str_repeat('a', 32)) . "\n");
        }

        $this->artisan('larasaas:install', ['--mode' => 'api', '--multi-tenant' => true])
            ->expectsConfirmation('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', 'yes')
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful()->run();

        $this->assertCount(1, glob(database_path('migrations/*_create_larasaas_schema.php')));
        $this->assertCount(1, glob(database_path('migrations/*_create_larasaas_workspaces_schema.php')));
        $this->assertStringContainsString('LARASAAS_MULTI_TENANT=true', file_get_contents($env));

        // Re-running stays idempotent.
        $this->artisan('larasaas:install', ['--mode' => 'api', '--multi-tenant' => true])
            ->expectsConfirmation('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', 'yes')
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful()->run();
        $this->assertCount(1, glob(database_path('migrations/*_create_larasaas_workspaces_schema.php')));
    }

    public function test_install_and_migrate_runs_one_package_schema_and_cashier(): void
    {
        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        $this->artisan('larasaas:install', ['--mode' => 'api', '--no-multi-tenant' => true, '--migrate' => true])
            ->expectsConfirmation('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', 'yes')
            ->assertSuccessful()->run();
        $this->assertTrue(Schema::hasTable('larasaas_plans'));
        $this->assertTrue(Schema::hasTable('subscriptions'));
        $this->assertTrue(Schema::hasColumn('users', 'stripe_id'));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%larasaas%')->count());
    }

    /** Cashier's stock migration hardcodes `users` — it must not run against a custom billable table. */
    public function test_cashier_customer_columns_migration_is_offered_for_removal_on_custom_tables(): void
    {
        config(['larasaas.user_model' => MembersUser::class]);

        $this->artisan('larasaas:install', ['--mode' => 'api', '--no-multi-tenant' => true])
            ->expectsConfirmation('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', 'yes')
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful()->run();

        $this->assertCount(0, glob(database_path('migrations/*create_customer_columns.php')));
    }

    /**
     * Even when the billable table IS `users`, Cashier's copy is retired: it is
     * not idempotent and would run after (and collide with) the LaraSaaS
     * baseline, which owns the columns on the configured table.
     */
    public function test_cashier_customer_columns_migration_is_removed_even_when_the_billable_table_is_users(): void
    {
        $this->artisan('larasaas:install', ['--mode' => 'api', '--no-multi-tenant' => true])
            ->expectsConfirmation('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', 'yes')
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful()->run();

        $this->assertCount(0, glob(database_path('migrations/*create_customer_columns.php')));
    }

    public function test_force_preserves_host_edits_even_with_a_renamed_timestamp(): void
    {
        $this->install();
        $file = glob(database_path('migrations/*larasaas*.php'))[0];
        $customized = file_get_contents($file) . "\n// Host customization\n";
        $renamed = database_path('migrations/2026_10_15_000000_create_larasaas_schema.php');
        rename($file, $renamed);
        file_put_contents($renamed, $customized);
        $this->artisan('larasaas:install', ['--mode' => 'embed', '--no-multi-tenant' => true, '--force' => true])
            ->expectsConfirmation('Remove the published Cashier customer-columns migration (its columns are managed by the LaraSaaS migration)?', 'yes')
            ->expectsConfirmation('Run the migrations now?', 'no')->assertSuccessful()->run();
        $this->assertCount(1, glob(database_path('migrations/*larasaas*.php')));
        $this->assertSame($customized, file_get_contents($renamed));
    }

    public function test_repository_ships_the_baseline_and_the_optional_workspaces_migration(): void
    {
        $files = glob(__DIR__ . '/../database/migrations/*.php');
        $this->assertCount(2, $files);
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../database/schema');
    }

    private function setting(string $key): ?string
    {
        return \Illuminate\Support\Facades\DB::table('larasaas_settings')->where('key', $key)->value('value');
    }
}

class InstallUser extends User
{
    protected $table = 'users';
    protected $guarded = [];
}

class MembersUser extends User
{
    protected $table = 'members';
    protected $guarded = [];
}
