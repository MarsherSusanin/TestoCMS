<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class UpdateOperationJournal
{
    public function write(string $directory, array $state): void
    {
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/operation.json';
        $previous = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $state = array_merge(is_array($previous) ? $previous : [], $state, ['updated_at' => now()->toIso8601String()]);
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write update recovery journal.');
        }
        chmod($temporary, 0600);
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Cannot commit update recovery journal.');
        }
    }
}
