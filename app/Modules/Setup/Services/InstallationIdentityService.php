<?php

namespace App\Modules\Setup\Services;

use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class InstallationIdentityService
{
    public function current(): array
    {
        $connection = DB::connection()->getConfig();
        $database = array_intersect_key($connection, array_flip(['driver', 'host', 'port', 'database', 'unix_socket', 'search_path', 'prefix']));
        foreach ($database as &$value) {
            $value = is_array($value) ? implode(',', $value) : (string) $value;
        }
        ksort($database);

        return ['database' => $database, 'public_root' => $this->canonicalPath(public_path()), 'key_sha256' => hash('sha256', (string) config('app.key'))];
    }

    public function canonicalPath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return realpath($path) ?: '/'.implode('/', $parts);
    }

    public function assertSchema(): void
    {
        foreach (['migrations', 'users', 'roles', 'model_has_roles', 'cms_installation'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Installed marker does not match a ready CMS database. Missing '.$table.'. Restore the matching database/storage; no automatic reset was performed.');
            }
        }
        if (! User::query()->whereHas('roles', fn ($query) => $query->where('name', 'superadmin'))->exists()) {
            throw new RuntimeException('The configured database has no CMS superadmin. Restore the matching database; no account was reset.');
        }
    }

    public function assertReady(bool $verifyEnvironment = true): void
    {
        $this->assertSchema();
        $marker = json_decode((string) file_get_contents(storage_path('installed')), true);
        $record = DB::table('cms_installation')->where('id', 1)->first();
        if (! is_array($marker) || empty($marker['instance_id']) || $record === null) {
            throw new RuntimeException('Legacy/unbound installation: run cms:setup --adopt-existing --force after verifying this database and public root. No account or environment was changed.');
        }
        if ($verifyEnvironment && is_file(app()->environmentFilePath())) {
            $this->assertEnvironmentIdentity(Dotenv::parse((string) file_get_contents(app()->environmentFilePath())));
        }
        $identity = json_decode($record->identity, true, 512, JSON_THROW_ON_ERROR);
        if ($marker['instance_id'] !== $record->instance_id || ($marker['identity'] ?? null) !== $identity || $identity !== $this->current()) {
            throw new RuntimeException('Installation identity mismatch (database, web root or APP_KEY). Restore the matching configuration; migration/reset is not supported by setup.');
        }
    }

    public function assertEnvironmentIdentity(array $environment): void
    {
        $current = $this->current();
        foreach (['DB_CONNECTION' => 'driver', 'DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_SCHEMA' => 'search_path', 'DB_SOCKET' => 'unix_socket', 'DB_PREFIX' => 'prefix'] as $key => $field) {
            if (array_key_exists($field, $current['database']) && array_key_exists($key, $environment) && (string) $environment[$key] !== $current['database'][$field]) {
                throw new RuntimeException('Environment changes database identity; setup refused.');
            }
        }
        $path = (string) ($environment['LARAVEL_PUBLIC_PATH'] ?? 'html_public');
        $path = str_starts_with($path, '/') ? $path : base_path($path);
        $key = (string) ($environment['APP_KEY'] ?? '');
        if ($key === '' && ($environment['CMS_DEPLOYMENT_PROFILE'] ?? '') === 'local' && is_file(storage_path('app/private/docker-bootstrap-keys.json'))) {
            $keys = json_decode((string) file_get_contents(storage_path('app/private/docker-bootstrap-keys.json')), true, 512, JSON_THROW_ON_ERROR);
            $key = (string) ($keys['APP_KEY'] ?? '');
        }
        if ($this->canonicalPath($path) !== $current['public_root'] || hash('sha256', $key) !== $current['key_sha256']) {
            throw new RuntimeException('Setup cannot change APP_KEY or public root. Restore the existing identity.');
        }
        if (! empty($environment['DB_URL'])) {
            $url = parse_url($environment['DB_URL']);
            $driver = ['postgres' => 'pgsql', 'postgresql' => 'pgsql', 'mysql2' => 'mysql'][$url['scheme'] ?? ''] ?? ($url['scheme'] ?? '');
            if (! is_array($url) || $driver !== $current['database']['driver'] || ($url['host'] ?? '') !== ($current['database']['host'] ?? '') || ltrim($url['path'] ?? '', '/') !== ($current['database']['database'] ?? '') || (string) ($url['port'] ?? ($driver === 'pgsql' ? 5432 : 3306)) !== ($current['database']['port'] ?? '')) {
                throw new RuntimeException('DB_URL does not match the bound installation.');
            }
        }
    }

    public function bind(): array
    {
        $identity = $this->current();
        $record = DB::table('cms_installation')->where('id', 1)->first();
        if ($record !== null && json_decode($record->identity, true, 512, JSON_THROW_ON_ERROR) !== $identity) {
            throw new RuntimeException('Database belongs to a different installation identity.');
        }
        $id = $record === null ? (string) Str::uuid() : $record->instance_id;
        DB::table('cms_installation')->updateOrInsert(['id' => 1], ['instance_id' => $id, 'identity' => json_encode($identity, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => $record === null ? now() : $record->created_at]);

        return ['instance_id' => $id, 'identity' => $identity];
    }
}
