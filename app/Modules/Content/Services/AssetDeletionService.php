<?php

namespace App\Modules\Content\Services;

use App\Models\Asset;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Caching\Services\PublicContentVersionService;
use App\Modules\Content\Exceptions\AssetInUseException;
use App\Modules\Content\Exceptions\AssetStorageException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AssetDeletionService
{
    public function __construct(
        private readonly ContentMutationGuard $guard,
        private readonly AssetUsageService $usage,
        private readonly AssetDeletionJournal $journal,
        private readonly PublicContentVersionService $version,
        private readonly PageCacheService $cache,
    ) {}

    public function delete(Asset $asset): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new AssetStorageException('Media deletion requires a top-level transaction.');
        }
        $entry = null;
        try {
            DB::transaction(function () use ($asset, &$entry): void {
                $this->guard->lockMedia();
                $locked = Asset::query()->lockForUpdate()->findOrFail($asset->id);
                $this->validateStorage((string) $locked->disk, (string) $locked->storage_path);
                $usages = $this->usage->usages($locked);
                if ($usages !== []) {
                    throw new AssetInUseException($usages);
                }
                $owners = Asset::query()->where('disk', $locked->disk)->where('storage_path', $locked->storage_path)->whereKeyNot($locked->id)->lockForUpdate()->exists();
                if (! $owners) {
                    $this->validateStorage((string) $locked->disk, (string) $locked->storage_path);
                    try {
                        $entry = $this->prepare($locked);
                    } catch (Throwable $exception) {
                        throw new AssetStorageException('Unable to inspect asset storage.', previous: $exception);
                    }
                    $this->journal->write($entry);
                    $this->stage($entry);
                }
                $locked->delete();
                $this->version->bump();
            });
        } catch (Throwable $exception) {
            if ($entry !== null) {
                try {
                    $this->recoverEntry($entry);
                } catch (Throwable $recovery) {
                    Log::error('Media rollback requires recovery.', ['operation' => $entry['id'], 'exception' => $recovery::class]);
                }
            }
            if ($exception instanceof AssetInUseException) {
                throw $exception;
            }
            if ($entry !== null || $exception instanceof AssetStorageException) {
                throw new AssetStorageException('Media storage cleanup failed. The record was retained; recovery operation '.($entry['id'] ?? 'not-staged').'. Retry or run cms:assets:recover; restoration may need operator attention.', previous: $exception);
            }
            throw $exception;
        }
        if ($entry !== null) {
            try {
                $this->recoverEntry($entry);
            } catch (Throwable $exception) {
                // The public removal committed, but storage cleanup is incomplete.
                // Report that distinction and retain the journal for recovery.
                Log::warning('Media quarantine purge deferred.', ['operation' => $entry['id'], 'exception' => $exception::class]);
                $this->cache->flushAll(false);
                throw new AssetStorageException('The asset record and public file were removed, but private storage cleanup failed. Run cms:assets:recover for operation '.$entry['id'].'.', previous: $exception);
            }
        }
        $this->cache->flushAll(false);
    }

    private function validateStorage(string $disk, string $path): void
    {
        if ($disk === 'local' && ! str_starts_with($path, 'assets/')) {
            throw new AssetStorageException('Private local disk deletion is limited to assets/*; internal CMS files cannot be deleted as media.');
        }
        if (! is_array(config('filesystems.disks.'.$disk)) || $path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0") || in_array('..', explode('/', $path), true)) {
            throw new AssetStorageException('Asset storage location is invalid.');
        }
        if (config('filesystems.disks.'.$disk.'.driver') === 'local') {
            $storage = Storage::disk($disk);
            $root = realpath($storage->path(''));
            $source = $storage->path($path);
            $parent = dirname($source);
            while (! file_exists($parent) && dirname($parent) !== $parent) {
                $parent = dirname($parent);
            }
            $resolvedParent = realpath($parent);
            if ($root === false || $resolvedParent === false || is_link($source)
                || ($resolvedParent !== $root && ! str_starts_with($resolvedParent, $root.DIRECTORY_SEPARATOR))) {
                throw new AssetStorageException('Asset path escapes its disk or is a symlink.');
            }
            if (file_exists($source)) {
                $resolved = realpath($source);
                if ($resolved === false || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || ! is_file($source)) {
                    throw new AssetStorageException('Asset path is not a regular file inside its disk.');
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function prepare(Asset $asset): array
    {
        $id = bin2hex(random_bytes(16));
        $local = config('filesystems.disks.'.$asset->disk.'.driver') === 'local';
        $disk = Storage::disk($asset->disk);
        $urls = array_values(array_filter([$asset->public_url, $disk->url($asset->storage_path)], 'is_string'));

        return ['id' => $id, 'asset_id' => $asset->id, 'disk' => $asset->disk, 'path' => $asset->storage_path,
            'local' => $local, 'state' => 'prepared', 'urls' => $urls, 'had_source' => $disk->exists($asset->storage_path),
            'visibility' => $disk->exists($asset->storage_path) ? $disk->getVisibility($asset->storage_path) : 'public',
            'quarantine' => $local ? $this->journal->root().'/'.$id.'.bin' : '.cms-private/asset-deletions/'.$id,
            'mode' => $local && $disk->exists($asset->storage_path) ? (fileperms($disk->path($asset->storage_path)) & 0777) : null,
            'created_at' => now()->toIso8601String()];
    }

    /** @param array<string, mixed> $entry */
    private function stage(array $entry): void
    {
        $disk = Storage::disk($entry['disk']);
        if (! $disk->exists($entry['path'])) {
            return;
        }
        if ($entry['local']) {
            $source = $disk->path($entry['path']);
            $sourceStat = @stat(dirname($source));
            $targetStat = @stat(dirname($entry['quarantine']));
            if ($sourceStat === false || $targetStat === false || $sourceStat['dev'] !== $targetStat['dev'] || ! @rename($source, $entry['quarantine'])) {
                throw new AssetStorageException('Unable to quarantine local media on the same filesystem.');
            }
            if (! @chmod($entry['quarantine'], 0600)) {
                throw new AssetStorageException('Unable to make quarantined media private.');
            }
        } else {
            if (! $disk->copy($entry['path'], $entry['quarantine']) || ! $disk->setVisibility($entry['quarantine'], 'private') || ! $disk->delete($entry['path'])) {
                throw new AssetStorageException('Unable to quarantine remote media.');
            }
        }
    }

    /** @return array{restored:int,purged:int,failed:int} */
    public function recover(): array
    {
        $result = ['restored' => 0, 'purged' => 0, 'failed' => 0];
        foreach ($this->journal->entries() as $entry) {
            if ($entry['state'] === 'deleted') {
                continue;
            }
            try {
                $result[$this->recoverEntry($entry)]++;
            } catch (Throwable $exception) {
                $result['failed']++;
                Log::error('Media recovery failed.', ['operation' => $entry['id'], 'exception' => $exception::class]);
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $entry */
    public function recoverEntry(array $entry): string
    {
        return DB::transaction(function () use ($entry): string {
            $this->guard->lockMedia();
            $this->validateStorage($entry['disk'], $entry['path']);
            $disk = Storage::disk($entry['disk']);
            $ownerExists = Asset::query()->where('disk', $entry['disk'])->where('storage_path', $entry['path'])->exists();
            if ($entry['local']) {
                $backup = $entry['quarantine'];
                // Journal values must not escape the private quarantine directory.
                if ($backup !== $this->journal->root().'/'.$entry['id'].'.bin') {
                    throw new AssetStorageException('Invalid quarantine path.');
                }
                if ($ownerExists && is_file($backup)) {
                    if ($disk->exists($entry['path'])) {
                        throw new AssetStorageException('Recovery source already exists; refusing to overwrite it.');
                    }
                    if (! @rename($backup, $disk->path($entry['path']))) {
                        throw new AssetStorageException('Unable to restore local media.');
                    }
                    @chmod($disk->path($entry['path']), $entry['mode'] ?? 0644);
                } elseif (! $ownerExists && is_file($backup) && ! @unlink($backup)) {
                    throw new AssetStorageException('Unable to purge quarantined media.');
                }
            } else {
                if ($entry['quarantine'] !== '.cms-private/asset-deletions/'.$entry['id']) {
                    throw new AssetStorageException('Invalid remote quarantine path.');
                }
                if ($ownerExists && $disk->exists($entry['quarantine']) && ! $disk->exists($entry['path'])) {
                    if (! $disk->copy($entry['quarantine'], $entry['path']) || ! $disk->setVisibility($entry['path'], $entry['visibility'] ?? 'public')) {
                        throw new AssetStorageException('Unable to restore remote media.');
                    }
                }
                if ($disk->exists($entry['quarantine']) && ! $disk->delete($entry['quarantine'])) {
                    throw new AssetStorageException('Unable to purge remote quarantine.');
                }
            }
            if ($ownerExists) {
                if (($entry['had_source'] ?? false) && ! $disk->exists($entry['path'])) {
                    throw new AssetStorageException('Recovery could not restore the original bytes. Keep the journal for operator recovery.');
                }
                $this->journal->forget($entry['id']);

                return 'restored';
            }
            $entry['state'] = 'deleted';
            $entry['quarantine'] = null;
            $this->journal->write($entry);

            return 'purged';
        });
    }
}
