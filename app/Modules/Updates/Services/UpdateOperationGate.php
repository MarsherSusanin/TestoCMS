<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

/** Shared storage must be the same volume for web, workers and scheduler. */
class UpdateOperationGate
{
    private mixed $handle = null;

    private int $depth = 0;

    private bool $exclusive = false;

    public function acquire(bool $exclusive = false, bool $wait = false): bool
    {
        if ($this->handle !== null) {
            if ($exclusive && ! $this->exclusive) {
                throw new RuntimeException('Cannot upgrade a writer lock to an update lock.');
            }
            $this->depth++;

            return true;
        }

        $path = storage_path('app/private/core-updates.lock');
        File::ensureDirectoryExists(dirname($path), 0700);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cannot open the CMS update coordination lock.');
        }
        // A root CLI invocation must not create a file unusable by the FPM group.
        @chgrp($path, filegroup(dirname($path)));
        @chmod($path, 0660);
        $operation = ($exclusive ? LOCK_EX : LOCK_SH) | ($wait ? 0 : LOCK_NB);
        if (! flock($handle, $operation)) {
            fclose($handle);

            return false;
        }
        $this->handle = $handle;
        $this->depth = 1;
        $this->exclusive = $exclusive;

        return true;
    }

    public function release(): void
    {
        if ($this->handle === null || --$this->depth > 0) {
            return;
        }
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
        $this->exclusive = false;
        $this->depth = 0;
    }

    public function runExclusive(callable $callback): mixed
    {
        // Bounded wait: never let an unresponsive writer hang an update forever.
        $deadline = microtime(true) + (int) config('updates.writer_drain_timeout', 30);
        while (! $this->acquire(true)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out waiting for CMS writers; no snapshot was taken.');
            }
            usleep(100000);
        }
        try {
            return $callback();
        } finally {
            $this->release();
        }
    }
}
