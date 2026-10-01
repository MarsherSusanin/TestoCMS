<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Exceptions\AssetStorageException;

/** Private durable journals and lightweight tombstones; never served as media. */
class AssetDeletionJournal
{
    public function root(): string
    {
        return storage_path('app/private/asset-deletions');
    }

    /** @param array<string, mixed> $entry */
    public function write(array $entry): void
    {
        $root = $this->root();
        if (! is_dir($root) && ! @mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new AssetStorageException('Unable to create private media recovery journal.');
        }
        if (is_link($root)) {
            throw new AssetStorageException('The private media journal directory cannot be a symlink.');
        }
        @chmod($root, 0700);
        $path = $this->path($entry['id']);
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) {
            throw new AssetStorageException('Unable to write media recovery journal.');
        }
        try {
            @chmod($temporary, 0600);
            $json = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle) || (function_exists('fsync') && ! fsync($handle))) {
                throw new AssetStorageException('Unable to persist media recovery journal.');
            }
        } finally {
            fclose($handle);
        }
        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new AssetStorageException('Unable to finalize media recovery journal.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function entries(): array
    {
        $entries = [];
        foreach (glob($this->root().'/*.json') ?: [] as $path) {
            $json = @file_get_contents($path);
            try {
                $entry = json_decode($json === false ? '' : $json, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new AssetStorageException('A media recovery journal is unreadable. Run cms:assets:recover after repairing it.', previous: $exception);
            }
            if (! is_array($entry) || ! isset($entry['id'], $entry['disk'], $entry['path'], $entry['state'])) {
                throw new AssetStorageException('A media recovery journal is invalid.');
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    public function forget(string $id): void
    {
        $path = $this->path($id);
        if (is_file($path) && ! @unlink($path)) {
            throw new AssetStorageException('Unable to remove restored media journal.');
        }
    }

    public function path(string $id): string
    {
        if (! preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new AssetStorageException('Invalid media recovery operation.');
        }

        return $this->root().'/'.$id.'.json';
    }
}
