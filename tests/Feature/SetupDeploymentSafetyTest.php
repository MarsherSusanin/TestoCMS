<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Services\AdminProvisionerService;
use App\Modules\Setup\Services\DeploymentProfileService;
use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Setup\Services\InstallationIdentityService;
use App\Modules\Setup\Services\SetupRedoService;
use App\Modules\Updates\Services\UpdateOperationGate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SetupDeploymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $oldStorage;

    private string $oldEnvironment;

    private ?string $oldCache;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/testocms-setup-safety-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory.'/framework');
        $this->oldStorage = $this->app->storagePath();
        $this->oldEnvironment = $this->app->environmentPath();
        $this->oldCache = is_file($this->app->getCachedConfigPath()) ? file_get_contents($this->app->getCachedConfigPath()) : null;
        $this->app->useStoragePath($this->directory);
        $this->app->useEnvironmentPath($this->directory);
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $content = 'APP_KEY="'.$key.'"'."\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nLARAVEL_PUBLIC_PATH=".public_path()."\nCMS_CONTENT_API_KEY=fixture-key\nMAIL_MAILER=smtp\nCUSTOM_INTEGRATION=preserved\n";
        file_put_contents($this->directory.'/.env', $content);
        app(RolesAndPermissionsSeeder::class)->run();
        $this->admin = app(AdminProvisionerService::class)->provision(['name' => 'Original', 'login' => 'original', 'email' => 'original@example.test', 'password' => 'Original123!']);
        app(EnvWriterService::class)->markInstalled();
    }

    protected function tearDown(): void
    {
        $cache = $this->app->getCachedConfigPath();
        $this->oldCache === null ? File::delete($cache) : file_put_contents($cache, $this->oldCache);
        $this->app->useStoragePath($this->oldStorage);
        $this->app->useEnvironmentPath($this->oldEnvironment);
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function payload(): array
    {
        return ['app_name' => 'Updated', 'admin_name' => 'Updated', 'admin_login' => 'updated', 'admin_email' => 'updated@example.test', 'admin_password' => 'Changed123!'];
    }

    private function service(bool $fail = false): SetupRedoService
    {
        return new class(app(InstallationIdentityService::class), app(EnvWriterService::class), app(UpdateOperationGate::class), $fail) extends SetupRedoService
        {
            public function __construct($identity, $writer, $gate, private bool $fail)
            {
                parent::__construct($identity, $writer, $gate);
            }

            protected function refreshConfiguration(array $environment): void
            {
                if ($this->fail) {
                    throw new RuntimeException('Injected config failure');
                }
            }
        };
    }

    public function test_redo_updates_same_admin_and_preserves_identity_integrations_and_content(): void
    {
        $marker = file_get_contents(storage_path('installed'));
        $migrations = DB::table('migrations')->count();
        $this->admin->createToken('previous');
        $this->service()->redo($this->payload());
        $admin = $this->admin->fresh();
        $this->assertSame('updated@example.test', $admin->email);
        $this->assertTrue(Hash::check('Changed123!', $admin->password));
        $this->assertSame(1, User::count());
        $this->assertSame($migrations, DB::table('migrations')->count());
        $this->assertSame($marker, file_get_contents(storage_path('installed')));
        $this->assertStringContainsString('CUSTOM_INTEGRATION=preserved', file_get_contents($this->directory.'/.env'));
        $this->assertStringContainsString('MAIL_MAILER=smtp', file_get_contents($this->directory.'/.env'));
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertFalse($this->app->isDownForMaintenance());
        $this->assertFileDoesNotExist($this->service()->journalPath());
        app(InstallationIdentityService::class)->assertReady();
    }

    public function test_late_failure_restores_env_admin_tokens_cache_and_marker(): void
    {
        $this->admin->createToken('previous');
        $before = $this->admin->fresh()->getRawOriginal();
        $environment = file_get_contents($this->directory.'/.env');
        $marker = file_get_contents(storage_path('installed'));
        $cache = '<?php return '.var_export(config()->all(), true).';';
        file_put_contents($this->app->getCachedConfigPath(), $cache);
        try {
            $this->service(true)->redo($this->payload());
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('were restored', $exception->getMessage());
        }
        $this->assertSame($before, $this->admin->fresh()->getRawOriginal());
        $this->assertSame(1, $this->admin->tokens()->count());
        $this->assertSame($environment, file_get_contents($this->directory.'/.env'));
        $this->assertSame($marker, file_get_contents(storage_path('installed')));
        $this->assertSame($cache, file_get_contents($this->app->getCachedConfigPath()));
        $this->assertFalse($this->app->isDownForMaintenance());
    }

    public function test_marker_for_another_database_is_not_ready_and_does_not_reset_admin(): void
    {
        DB::table('cms_installation')->where('id', 1)->update(['instance_id' => '00000000-0000-0000-0000-000000000000']);
        $before = $this->admin->password;
        $this->artisan('cms:setup --from-env --no-interaction')->assertFailed();
        $this->assertSame($before, $this->admin->fresh()->password);
        $this->assertSame(1, User::count());
    }

    public function test_legacy_adoption_is_explicit_and_preserves_env_admin(): void
    {
        DB::table('cms_installation')->delete();
        file_put_contents(storage_path('installed'), 'legacy installation');
        $environment = file_get_contents($this->directory.'/.env');
        $hash = $this->admin->password;
        $this->artisan('cms:setup --from-env --no-interaction')->assertFailed();
        $this->artisan('cms:setup --adopt-existing --no-interaction')->assertFailed();
        $this->artisan('cms:setup --adopt-existing --force --no-interaction')->assertSuccessful();
        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->assertSame($environment, file_get_contents($this->directory.'/.env'));
        app(InstallationIdentityService::class)->assertReady();
    }

    public function test_from_env_redo_requires_force_and_never_writes_environment(): void
    {
        file_put_contents($this->directory.'/.env', "CMS_ADMIN_NAME=Updated\nCMS_ADMIN_LOGIN=updated\nCMS_ADMIN_EMAIL=updated@example.test\nCMS_ADMIN_PASSWORD=Changed123!\n", FILE_APPEND);
        $before = file_get_contents($this->directory.'/.env');
        $hash = $this->admin->password;
        $this->artisan('cms:setup --redo --from-env --no-interaction')->assertFailed();
        $this->app->instance(SetupRedoService::class, $this->service());
        $this->artisan('cms:setup --redo --from-env --force --no-interaction')->assertSuccessful();
        $this->assertSame($before, file_get_contents($this->directory.'/.env'));
        $this->assertSame('updated@example.test', $this->admin->fresh()->email);
        $this->assertSame($hash, $this->admin->fresh()->password);
    }

    public function test_redo_rejects_identity_change_before_writing_journal(): void
    {
        $before = file_get_contents($this->directory.'/.env');
        try {
            $this->service()->redo(array_merge($this->payload(), ['db_database' => 'different']));
            $this->fail('Expected refusal');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('database identity', $exception->getMessage());
        }
        $this->assertSame($before, file_get_contents($this->directory.'/.env'));
        $this->assertFileDoesNotExist($this->service()->journalPath());
    }

    public function test_recovery_restores_committed_admin_from_private_interrupted_journal(): void
    {
        $before = $this->admin->getRawOriginal();
        $service = new class(app(InstallationIdentityService::class), app(EnvWriterService::class), app(UpdateOperationGate::class)) extends SetupRedoService
        {
            public ?string $interruptedJournal = null;

            protected function refreshConfiguration(array $environment): void
            {
                $this->interruptedJournal = file_get_contents($this->journalPath());
            }
        };
        $service->redo($this->payload());
        file_put_contents($service->journalPath(), $service->interruptedJournal);
        chmod($service->journalPath(), 0600);
        Artisan::call('down');
        $operation = json_decode($service->interruptedJournal, true)['operation'];
        $this->artisan('cms:setup:recover '.$operation)->assertSuccessful();
        $this->assertSame($before, $this->admin->fresh()->getRawOriginal());
        $this->assertFalse($this->app->isDownForMaintenance());
        $this->assertFileDoesNotExist($service->journalPath());
        $this->artisan('cms:setup:recover '.$operation)->assertSuccessful();
    }

    public function test_local_profile_and_explicit_profile_preserve_existing_custom_public_root(): void
    {
        $this->app['env'] = 'local';
        config(['setup.deployment_profile' => '']);
        $this->assertSame('local', app(DeploymentProfileService::class)->default());
        $content = app(EnvWriterService::class)->buildEnvContent(['deployment_profile' => 'docker_vps'], "LARAVEL_PUBLIC_PATH=/custom/public\n");
        $this->assertStringContainsString('LARAVEL_PUBLIC_PATH=/custom/public', $content);
        $local = app(EnvWriterService::class)->buildEnvContent(['deployment_profile' => 'local'], '');
        $this->assertStringContainsString('APP_ENV="local"', $local);
        $this->assertStringContainsString('LARAVEL_PUBLIC_PATH="html_public"', $local);
    }

    public function test_cached_configuration_cannot_hide_changed_source_identity(): void
    {
        $before = file_get_contents($this->directory.'/.env');
        $hash = $this->admin->password;
        foreach ([['DB_DATABASE=:memory:', 'DB_DATABASE=different'], ['LARAVEL_PUBLIC_PATH='.public_path(), 'LARAVEL_PUBLIC_PATH=/different/root'], ['APP_KEY="'.config('app.key').'"', 'APP_KEY="different-key"']] as [$original, $changed]) {
            file_put_contents($this->directory.'/.env', str_replace($original, $changed, $before));
            $this->artisan('cms:setup --from-env --no-interaction')->assertFailed();
            $this->assertSame($hash, $this->admin->fresh()->password);
            $this->assertFileDoesNotExist($this->service()->journalPath());
        }
        file_put_contents($this->directory.'/.env', $before);
    }

    public function test_journal_failure_happens_before_database_environment_or_maintenance_changes(): void
    {
        $service = new class(app(InstallationIdentityService::class), app(EnvWriterService::class), app(UpdateOperationGate::class)) extends SetupRedoService
        {
            protected function saveJournal(array $journal): void
            {
                throw new RuntimeException('Injected private journal write failure');
            }
        };
        $before = file_get_contents($this->directory.'/.env');
        $admin = $this->admin->getRawOriginal();
        try {
            $service->redo($this->payload());
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('journal write failure', $exception->getMessage());
        }
        $this->assertSame($before, file_get_contents($this->directory.'/.env'));
        $this->assertSame($admin, $this->admin->fresh()->getRawOriginal());
        $this->assertFileDoesNotExist($service->journalPath());
        $this->assertFalse($this->app->isDownForMaintenance());
    }

    public function test_environment_write_failure_rolls_back_transaction_and_keeps_marker(): void
    {
        $writer = new class(app(DeploymentProfileService::class)) extends EnvWriterService
        {
            private int $writes = 0;

            public function writeEnvFile(string $content): void
            {
                if (++$this->writes === 1) {
                    throw new RuntimeException('Injected environment atomic write failure');
                }
                parent::writeEnvFile($content);
            }
        };
        $service = new SetupRedoService(app(InstallationIdentityService::class), $writer, app(UpdateOperationGate::class));
        $this->admin->createToken('previous');
        $before = file_get_contents($this->directory.'/.env');
        $marker = file_get_contents(storage_path('installed'));
        $hash = $this->admin->password;
        try {
            $service->redo($this->payload());
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('were restored', $exception->getMessage());
        }
        $this->assertSame($before, file_get_contents($this->directory.'/.env'));
        $this->assertSame($marker, file_get_contents(storage_path('installed')));
        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->assertSame(1, $this->admin->tokens()->count());
        $this->assertFalse($this->app->isDownForMaintenance());
    }

    public function test_admin_transaction_failure_does_not_change_environment(): void
    {
        $dispatcher = User::getEventDispatcher();
        User::setEventDispatcher(clone $dispatcher);
        User::saving(fn () => throw new RuntimeException('Injected administrator persistence failure'));
        $before = file_get_contents($this->directory.'/.env');
        $hash = $this->admin->password;
        try {
            try {
                $this->service()->redo($this->payload());
                $this->fail('Expected failure');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('were restored', $exception->getMessage());
            }
            $this->assertSame($before, file_get_contents($this->directory.'/.env'));
            $this->assertSame($hash, $this->admin->fresh()->password);
            $this->assertFalse($this->app->isDownForMaintenance());
        } finally {
            User::setEventDispatcher($dispatcher);
        }
    }

    public function test_failed_restoration_retains_maintenance_and_journal_until_explicit_recovery(): void
    {
        $writer = new class(app(DeploymentProfileService::class)) extends EnvWriterService
        {
            public function writeEnvFile(string $content): void
            {
                throw new RuntimeException('Injected persistent environment write failure');
            }
        };
        $service = new SetupRedoService(app(InstallationIdentityService::class), $writer, app(UpdateOperationGate::class));
        $hash = $this->admin->password;
        try {
            $service->redo($this->payload());
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restoration is incomplete', $exception->getMessage());
        }
        $this->assertTrue($this->app->isDownForMaintenance());
        $this->assertFileExists($service->journalPath());
        $operation = json_decode(file_get_contents($service->journalPath()), true)['operation'];
        $this->artisan('cms:setup:recover wrong-operation')->assertFailed();
        $this->assertTrue($this->app->isDownForMaintenance());
        $this->artisan('cms:setup:recover '.$operation)->assertSuccessful();
        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->assertFalse($this->app->isDownForMaintenance());
        $this->assertFileDoesNotExist($service->journalPath());
    }

    public function test_journal_transition_failure_restores_before_and_after_database_commit(): void
    {
        foreach ([2, 3] as $failOnWrite) {
            $writer = app(EnvWriterService::class);
            $service = new class(app(InstallationIdentityService::class), $writer, app(UpdateOperationGate::class), $failOnWrite) extends SetupRedoService
            {
                private int $writes = 0;

                public function __construct($identity, $writer, $gate, private int $failOnWrite)
                {
                    parent::__construct($identity, $writer, $gate);
                }

                protected function saveJournal(array $journal): void
                {
                    if (++$this->writes === $this->failOnWrite) {
                        throw new RuntimeException('Injected journal transition failure');
                    }
                    parent::saveJournal($journal);
                }

                protected function refreshConfiguration(array $environment): void {}
            };
            $before = file_get_contents($this->directory.'/.env');
            $admin = $this->admin->fresh()->getRawOriginal();
            try {
                $service->redo($this->payload());
                $this->fail('Expected failure');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('were restored', $exception->getMessage());
            }
            $this->assertSame($admin, $this->admin->fresh()->getRawOriginal());
            $this->assertSame($before, file_get_contents($this->directory.'/.env'));
            $this->assertFalse($this->app->isDownForMaintenance());
            $this->assertFileDoesNotExist($service->journalPath());
        }
    }
}
