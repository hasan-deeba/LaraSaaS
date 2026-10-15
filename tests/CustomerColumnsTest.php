<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use LaraSaaS\Support\BillingSchema;

/**
 * The Cashier customer columns must migrate to the table of the CONFIGURED
 * user/workspace model — never a hardcoded `users` table — and the developer
 * must be alerted when that model changes after migrating.
 */
class CustomerColumnsTest extends TestCase
{
    private string $databaseDirectory;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->databaseDirectory = sys_get_temp_dir() . '/larasaas-columns-' . bin2hex(random_bytes(8));
        $app->useDatabasePath($this->databaseDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->databaseDirectory);
        parent::tearDown();
    }

    private function createTable(string $table): void
    {
        Schema::create($table, function (Blueprint $blueprint) {
            $blueprint->id();
        });
    }

    private function migrateSchema(): void
    {
        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();
    }

    public function test_columns_are_migrated_to_the_configured_user_model_table_not_users(): void
    {
        config(['larasaas.user_model' => \App\Models\Member::class]);
        $this->createTable('users');
        $this->createTable('members');

        $this->migrateSchema();

        foreach ((new BillingSchema)->requiredColumns() as $column) {
            $this->assertTrue(Schema::hasColumn('members', $column), "members.{$column} missing");
            $this->assertFalse(Schema::hasColumn('users', $column), "users.{$column} should not exist");
        }
    }

    public function test_columns_land_on_users_by_default(): void
    {
        $this->createTable('users');

        $this->migrateSchema();

        $this->assertTrue(Schema::hasColumn('users', 'stripe_id'));
        $this->assertTrue(Schema::hasColumn('users', 'pm_type'));
        $this->assertTrue(Schema::hasColumn('users', 'pm_last_four'));
        $this->assertTrue(Schema::hasColumn('users', 'trial_ends_at'));
    }

    public function test_workspace_billing_targets_the_workspace_model_table(): void
    {
        config(['larasaas.billing.billable' => 'workspace']);
        $this->createTable('users');

        $this->migrateSchema(); // creates larasaas_workspaces BEFORE adding the columns

        $this->assertTrue(Schema::hasColumn('larasaas_workspaces', 'stripe_id'));
        $this->assertFalse(Schema::hasColumn('users', 'stripe_id'));
    }

    public function test_workspace_billing_targets_a_custom_workspace_model_table(): void
    {
        config([
            'larasaas.billing.billable' => 'workspace',
            'larasaas.billing.workspace_model' => \App\Models\Team::class,
        ]);
        $this->createTable('users');
        $this->createTable('teams');

        $this->migrateSchema();

        $this->assertTrue(Schema::hasColumn('teams', 'stripe_id'));
        $this->assertFalse(Schema::hasColumn('users', 'stripe_id'));
        $this->assertFalse(Schema::hasColumn('larasaas_workspaces', 'stripe_id'));
    }

    public function test_migrations_are_idempotent_and_rollback_keeps_billing_columns(): void
    {
        $this->createTable('users');
        $migration = require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php';

        $migration->up();
        $migration->down();
        $this->assertTrue(Schema::hasColumn('users', 'stripe_id'), 'down() must never destroy billing data');
        $migration->up(); // guards make re-runs safe

        $this->assertTrue(Schema::hasColumn('users', 'stripe_id'));
        $this->assertTrue(Schema::hasTable('larasaas_workspaces'));
    }

    public function test_missing_billable_table_is_skipped_with_a_visible_warning(): void
    {
        config(['larasaas.user_model' => \App\Models\Member::class]); // members table never created
        $this->createTable('users');

        $output = (function () {
            ob_start();
            $this->migrateSchema();

            return (string) ob_get_clean();
        })();

        $this->assertStringContainsString('were NOT added', $output);
        $this->assertFalse(Schema::hasColumn('users', 'stripe_id'));
    }

    public function test_doctor_is_silent_when_the_schema_is_healthy(): void
    {
        $this->createTable('users');
        $this->migrateSchema();

        $this->assertSame([], app(BillingSchema::class)->problems());
        $this->artisan('larasaas:doctor')->assertSuccessful();
    }

    public function test_doctor_alerts_when_the_configured_model_changed_after_migrating(): void
    {
        $this->createTable('users');
        $this->createTable('members');
        $this->migrateSchema(); // columns land on users

        config(['larasaas.user_model' => \App\Models\Member::class]); // ⚠ changed AFTER migrating

        $problems = app(BillingSchema::class)->problems();
        $joined = implode(' || ', $problems); // visible in assertion output
        $this->assertNotEmpty($problems, 'problems() was empty');
        $this->assertStringContainsString('CONFIG CHANGED?', $joined, "problems: {$joined}");
        $this->assertStringContainsString('[users]', $joined, "problems: {$joined}");
        $this->assertStringContainsString('[members]', $joined, "problems: {$joined}");
        $this->assertStringContainsString('user_model', $joined, "problems: {$joined}");

        $this->artisan('larasaas:doctor')->assertFailed();
    }

    public function test_doctor_reports_missing_columns_before_any_migration(): void
    {
        $this->createTable('users');

        $problems = app(BillingSchema::class)->problems();
        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('missing the Cashier customer columns', $problems[0]);
    }

    public function test_doctor_flags_stock_cashier_migrations_targeting_the_wrong_table(): void
    {
        config(['larasaas.user_model' => \App\Models\Member::class]);
        $this->createTable('members');
        $this->migrateSchema(); // healthy so far

        if (! is_dir(database_path('migrations'))) {
            mkdir(database_path('migrations'), 0755, true);
        }
        file_put_contents(
            database_path('migrations/2019_05_03_000001_create_customer_columns.php'),
            "<?php\n// stock Laravel Cashier migration\nSchema::table('users', function (\$table) { \$table->string('stripe_id'); });\n",
        );

        $problems = app(BillingSchema::class)->problems();
        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('hardcoded [users]', $problems[0]);
    }

    public function test_install_skips_stock_customer_columns_and_reports_the_target_table(): void
    {
        $this->createTable('users');

        $this->artisan('larasaas:install', ['--mode' => 'api'])
            ->expectsConfirmation('Run the migrations now?', false)->assertSuccessful();

        $this->assertCount(0, glob(database_path('migrations/*create_customer_columns*.php')));
        $this->assertCount(1, glob(database_path('migrations/*_create_subscriptions_table*.php')));
    }
}
