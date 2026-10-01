<?php

namespace App\Modules\Setup\Services;

use App\Models\User;
use App\Modules\Auth\Services\UserAccessRevocationService;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Services\SlugResolverService;
use App\Modules\Updates\Services\UpdateOperationGate;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/** Redo changes one existing admin/settings, never schema, installation identity or content. */
class SetupRedoService
{
    public function __construct(private readonly InstallationIdentityService $identity, private readonly EnvWriterService $writer, private readonly UpdateOperationGate $gate) {}

    public function journalPath(): string
    {
        return storage_path('app/private/setup-redo.json');
    }

    public function redo(array $data, bool $writeEnv = true, ?callable $onPrepared = null): void
    {
        $this->gate->runExclusive(function () use ($data, $writeEnv, $onPrepared): void {
            if (is_file($this->journalPath())) {
                throw new RuntimeException('Interrupted setup operation: run cms:setup:recover before another redo.');
            }
            $this->identity->assertReady();
            Validator::make($data, [
                'admin_name' => 'required|string|max:255', 'admin_login' => 'required|string|max:255',
                'admin_email' => 'required|email|max:255', 'admin_password' => $writeEnv ? 'required|string|min:8' : 'nullable|string',
                'app_url' => 'sometimes|url', 'default_locale' => 'sometimes|in:ru,en',
                'supported_locales' => 'sometimes|array|min:1', 'supported_locales.*' => 'in:ru,en',
            ])->validate();
            $admin = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'superadmin'))->orderBy('id')->firstOrFail();
            if (User::query()->where('id', '!=', $admin->id)->where(fn ($query) => $query->where('email', $data['admin_email'])->orWhere('login', $data['admin_login']))->exists()) {
                throw new RuntimeException('Admin login or email belongs to another existing user.');
            }
            $beforeEnv = is_file(app()->environmentFilePath()) ? (string) file_get_contents(app()->environmentFilePath()) : null;
            if ($beforeEnv === null) {
                throw new RuntimeException('Redo requires the existing environment file.');
            }
            $candidate = $writeEnv ? $this->writer->buildEnvContent($data, $beforeEnv) : $beforeEnv;
            $this->assertUnchangedIdentity($data, Dotenv::parse($candidate));
            $cachePath = app()->getCachedConfigPath();
            if (! str_starts_with($cachePath, base_path('bootstrap/cache/'))) {
                throw new RuntimeException('Redo requires the standard private bootstrap/cache config path.');
            }
            $journal = [
                'version' => 1, 'operation' => (string) Str::uuid(), 'phase' => 'prepared', 'identity' => $this->identity->current(),
                'instance_id' => json_decode((string) file_get_contents(storage_path('installed')), true)['instance_id'],
                'write_env' => $writeEnv, 'env' => base64_encode($beforeEnv),
                'marker' => base64_encode((string) file_get_contents(storage_path('installed'))),
                'config' => config()->all(), 'cache' => is_file($cachePath) ? base64_encode((string) file_get_contents($cachePath)) : null,
                'cache_path' => $cachePath,
                'admin' => $admin->getRawOriginal(), 'model_type' => $admin->getMorphClass(),
                'tokens' => Schema::hasTable('personal_access_tokens') ? DB::table('personal_access_tokens')->where('tokenable_type', $admin->getMorphClass())->where('tokenable_id', $admin->id)->get()->map(fn ($row) => (array) $row)->all() : [],
                'sessions' => Schema::hasTable('sessions') ? DB::table('sessions')->where('user_id', $admin->id)->get()->map(fn ($row) => (array) $row)->all() : [],
                'maintenance' => is_file(storage_path('framework/down')) ? base64_encode((string) file_get_contents(storage_path('framework/down'))) : null,
            ];
            $this->saveJournal($journal);
            if ($onPrepared !== null) {
                $onPrepared($journal['operation']);
            }
            try {
                if (Artisan::call('down') !== 0) {
                    throw new RuntimeException('Cannot enter setup maintenance.');
                }
                DB::transaction(function () use ($data, $writeEnv, $candidate, $admin, &$journal): void {
                    $credentialsChanged = $writeEnv || $admin->email !== $data['admin_email'] || $admin->login !== $data['admin_login'];
                    $attributes = ['name' => $data['admin_name'], 'login' => $data['admin_login'], 'email' => $data['admin_email']];
                    if ($writeEnv) {
                        $attributes['password'] = $data['admin_password'];
                    }
                    $admin->fill($attributes)->save();
                    if ($credentialsChanged) {
                        app(UserAccessRevocationService::class)->revoke($admin);
                    }
                    if ($writeEnv) {
                        $this->writer->writeEnvFile($candidate);
                    }
                    $journal['phase'] = 'environment_applied';
                    $this->saveJournal($journal);
                    $this->refreshConfiguration(Dotenv::parse($candidate));
                    app(PageCacheService::class)->flushAll();
                    app(SlugResolverService::class)->flushAll();
                });
                $journal['phase'] = 'completed';
                $this->saveJournal($journal);
                $this->restoreMaintenance($journal);
                unlink($this->journalPath());
            } catch (Throwable $exception) {
                try {
                    $this->restore($journal);
                    unlink($this->journalPath());
                } catch (Throwable) {
                    throw new RuntimeException('Redo failed and restoration is incomplete. Maintenance is retained; run cms:setup:recover '.$journal['operation'].'. Private details are in the recovery journal.', 0, $exception);
                }
                throw new RuntimeException('Redo failed; the original environment, administrator and config cache were restored.', 0, $exception);
            }
        });
    }

    private function assertUnchangedIdentity(array $data, array $environment): void
    {
        $current = $this->identity->current();
        foreach (['db_connection' => 'driver', 'db_host' => 'host', 'db_port' => 'port', 'db_database' => 'database', 'db_schema' => 'search_path'] as $input => $field) {
            if (array_key_exists($input, $data) && (string) $data[$input] !== ($current['database'][$field] ?? '')) {
                throw new RuntimeException('Redo cannot change database identity.');
            }
        }
        $this->identity->assertEnvironmentIdentity($environment);
    }

    protected function refreshConfiguration(array $environment): void
    {
        if (empty($environment['APP_KEY'])) {
            $environment['APP_KEY'] = (string) config('app.key');
        }
        if (empty($environment['CMS_CONTENT_API_KEY'])) {
            $environment['CMS_CONTENT_API_KEY'] = (string) config('cms.content_api.key');
        }
        $process = new Process([PHP_BINARY, base_path('artisan'), 'config:cache', '--no-interaction'], base_path(), $environment);
        $process->setTimeout(120);
        $process->mustRun();
    }

    public function recover(string $operation): void
    {
        $this->gate->runExclusive(function () use ($operation): void {
            if (! is_file($this->journalPath())) {
                return;
            }
            $journal = json_decode((string) file_get_contents($this->journalPath()), true, 512, JSON_THROW_ON_ERROR);
            if (($journal['version'] ?? null) !== 1 || ($journal['operation'] ?? '') !== $operation || ! in_array($journal['phase'] ?? '', ['prepared', 'environment_applied', 'completed'], true)) {
                throw new RuntimeException('Invalid setup recovery journal; maintenance remains enabled.');
            }
            if ($journal['phase'] === 'completed') {
                $this->identity->assertReady(false);
                $marker = json_decode((string) file_get_contents(storage_path('installed')), true);
                if (($marker['instance_id'] ?? null) !== $journal['instance_id'] || $this->identity->current() !== $journal['identity']) {
                    throw new RuntimeException('Recovery journal belongs to another installation.');
                }
                $this->restoreMaintenance($journal);
            } else {
                $this->restore($journal);
            }
            unlink($this->journalPath());
        });
    }

    private function restore(array $journal): void
    {
        $database = config('database');
        config($journal['config']);
        if ($database !== $journal['config']['database']) {
            DB::purge();
        }
        $this->identity->assertReady(false);
        $marker = json_decode((string) file_get_contents(storage_path('installed')), true);
        if ($this->identity->current() !== $journal['identity'] || ($marker['instance_id'] ?? null) !== $journal['instance_id']) {
            throw new RuntimeException('Recovery journal belongs to another installation.');
        }
        DB::transaction(function () use ($journal): void {
            $id = $journal['admin']['id'];
            if (DB::table('users')->where('id', $id)->update($journal['admin']) === 0 && ! DB::table('users')->where('id', $id)->exists()) {
                throw new RuntimeException('Original administrator is missing; recovery refused.');
            }
            foreach (['personal_access_tokens' => 'tokens', 'sessions' => 'sessions'] as $table => $snapshot) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $query = DB::table($table);
                $table === 'sessions' ? $query->where('user_id', $id) : $query->where('tokenable_type', $journal['model_type'])->where('tokenable_id', $id);
                $query->delete();
                if ($journal[$snapshot] !== []) {
                    DB::table($table)->insert($journal[$snapshot]);
                }
            }
            if ($journal['write_env']) {
                $this->writer->writeEnvFile(base64_decode($journal['env'], true));
            }
            $path = (string) $journal['cache_path'];
            if (! str_starts_with($path, base_path('bootstrap/cache/')) || str_contains($path, '..')) {
                throw new RuntimeException('Invalid private config cache recovery path.');
            }
            if ($journal['cache'] === null) {
                File::delete($path);
            } else {
                $this->atomicWrite($path, base64_decode($journal['cache'], true));
            }
            $this->atomicWrite(storage_path('installed'), base64_decode($journal['marker'], true));
        });
        $this->restoreMaintenance($journal);
    }

    private function restoreMaintenance(array $journal): void
    {
        if ($journal['maintenance'] === null) {
            if (Artisan::call('up') !== 0) {
                throw new RuntimeException('Cannot leave setup maintenance.');
            }
        } else {
            $this->atomicWrite(storage_path('framework/down'), base64_decode($journal['maintenance'], true));
        }
    }

    protected function saveJournal(array $journal): void
    {
        File::ensureDirectoryExists(dirname($this->journalPath()), 0700);
        $this->atomicWrite($this->journalPath(), json_encode($journal, JSON_THROW_ON_ERROR));
    }

    private function atomicWrite(string $path, string $bytes): void
    {
        $temporary = tempnam(dirname($path), '.setup-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot stage setup recovery data.');
        }
        try {
            chmod($temporary, 0600);
            $handle = fopen($temporary, 'wb');
            if ($handle === false || fwrite($handle, $bytes) !== strlen($bytes) || ! fflush($handle) || (function_exists('fsync') && ! fsync($handle))) {
                throw new RuntimeException('Cannot persist setup recovery data.');
            }
            fclose($handle);
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Cannot replace setup recovery data.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
