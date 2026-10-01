<?php

namespace App\Console\Commands;

use App\Models\CoreBackup;
use App\Modules\Updates\Services\CoreUpdateEnvironment;
use App\Modules\Updates\Services\FilesystemUpdateDriver;
use Illuminate\Console\Command;

class RecoverCoreUpdateCommand extends Command
{
    protected $signature = 'cms:updates:recover {backup_key : Backup key from the private recovery journal}';

    protected $description = 'Resume restoration from a filesystem backup, including interrupted PostgreSQL rename';

    public function handle(CoreUpdateEnvironment $environment, FilesystemUpdateDriver $driver): int
    {
        $key = (string) $this->argument('backup_key');
        if (! preg_match('/^bkp_[0-9]{14}_[a-z0-9]{6}$/', $key)) {
            $this->error('Invalid backup key.');

            return self::FAILURE;
        }
        $directory = $environment->storageRoot().'/backups/'.$key;
        try {
            $manifest = json_decode((string) file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $attributes = $manifest['backup'] ?? null;
            if (! is_array($attributes) || ($attributes['backup_key'] ?? '') !== $key || ($attributes['backup_path'] ?? '') !== $directory) {
                throw new \RuntimeException('Recovery requires a version 2 backup manifest.');
            }
            $result = $driver->rollback(new CoreBackup($attributes));
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return $result['status'] === 'success' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
