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
        }
        $migration->down();
        $this->assertFalse(Schema::hasTable('larasaas_plans'));
        $this->assertFalse(Schema::hasTable('larasaas_announcements'));
    }

    public function test_install_and_migrate_does_not_run_legacy_schema_alongside_baseline(): void
    {
        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        $this->artisan('larasaas:install', ['--mode' => 'api', '--migrate' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('larasaas_plans'));
        $this->assertTrue(Schema::hasTable('subscriptions'));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%larasaas%')->count());
    }

    public function test_upgrade_only_adds_plans_and_preserves_legacy_tables(): void
    {
        (require __DIR__ . '/../database/migrations/2025_01_01_000001_create_larasaas_tables.php')->up();
        $this->install();
        $files = glob(database_path('migrations/*larasaas*.php'));
        $this->assertCount(1, $files);
        $this->assertStringContainsString('create_larasaas_plans_table', $files[0]);
        $migration = require $files[0];
        $migration->up();
        $this->assertTrue(Schema::hasTable('larasaas_plans'));
        $migration->down();
        $this->assertTrue(Schema::hasTable('larasaas_announcements'));
    }
}
