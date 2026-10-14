<?php

namespace LaraSaaS\Tests;

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
        $this->databaseDirectory = sys_get_temp_dir() . '/larasaas-install-' . bin2hex(random_bytes(8));
        $app->useDatabasePath($this->databaseDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->databaseDirectory);
        parent::tearDown();
    }

    private function install(): void
    {
        $this->artisan('larasaas:install', ['--mode' => 'api'])
            ->expectsConfirmation('Run the migrations now?', false)->assertSuccessful();
    }

    public function test_fresh_install_publishes_one_idempotent_schema_with_working_rollback(): void
    {
        $this->install();
        $files = glob(database_path('migrations/*larasaas*.php'));
        $this->assertCount(1, $files);
        $original = file_get_contents($files[0]);
        $this->install();
        $this->assertCount(1, glob(database_path('migrations/*larasaas*.php')));
        $this->assertSame($original, file_get_contents($files[0]));
        $migration = require $files[0];
        $migration->up();
        foreach (['larasaas_plans', 'larasaas_announcements', 'larasaas_announcement_dismissals'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(0, \Illuminate\Support\Facades\DB::table($table)->count());
        }
        $migration->down();
        $this->assertFalse(Schema::hasTable('larasaas_plans'));
        $this->assertFalse(Schema::hasTable('larasaas_announcements'));
        $this->assertFalse(Schema::hasTable('larasaas_announcement_dismissals'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('larasaas_plans', 'features'));
    }

    public function test_install_and_migrate_runs_one_package_schema_and_cashier(): void
    {
        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        $this->artisan('larasaas:install', ['--mode' => 'api', '--migrate' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('larasaas_plans'));
        $this->assertTrue(Schema::hasTable('subscriptions'));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%larasaas%')->count());
    }

    public function test_force_preserves_host_edits_even_with_a_renamed_timestamp(): void
    {
        $this->install();
        $file = glob(database_path('migrations/*larasaas*.php'))[0];
        $customized = file_get_contents($file) . "\n// Host customization\n";
        $renamed = database_path('migrations/2026_10_15_000000_create_larasaas_schema.php');
        rename($file, $renamed);
        file_put_contents($renamed, $customized);
        $this->artisan('larasaas:install', ['--mode' => 'embed', '--force' => true])
            ->expectsConfirmation('Run the migrations now?', false)->assertSuccessful();
        $this->assertCount(1, glob(database_path('migrations/*larasaas*.php')));
        $this->assertSame($customized, file_get_contents($renamed));
    }

    public function test_repository_ships_exactly_one_migration(): void
    {
        $this->assertCount(1, glob(__DIR__ . '/../database/migrations/*.php'));
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../database/schema');
    }
}
