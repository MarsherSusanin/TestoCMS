<?php

namespace App\Modules\Setup\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PublicMediaService
{
    public function prepare(): void
    {
        if (config('filesystems.disks.public.driver', 'local') !== 'local') {
            return;
        }
        $target = (string) config('filesystems.disks.public.root', storage_path('app/public'));
        File::ensureDirectoryExists($target);
        if (! is_writable($target)) {
            throw new RuntimeException('Public upload storage is not writable.');
        }
        $link = public_path('storage');
        if (! is_link($link) && ! file_exists($link) && function_exists('symlink')) {
            // Optional acceleration; physical directories from old installs stay
            // untouched and the HTTP route always reads the authoritative disk.
            @symlink($target, $link);
        }
    }

    public function resolve(string $path): ?string
    {
        if (config('filesystems.disks.public.driver', 'local') !== 'local') {
            return null;
        }
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return null;
            }
        }
        if (preg_match('/\.(?:php\d*|phtml|pht|phar|phps|cgi|pl|py|sh|htaccess|env)$/i', $path)) {
            return null;
        }
        $root = realpath((string) config('filesystems.disks.public.root', storage_path('app/public')));
        $file = $root === false ? false : realpath($root.DIRECTORY_SEPARATOR.$path);
        if ($root === false || $file === false || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)
            || ! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $relative = substr($file, strlen($root) + 1);
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                return null;
            }
        }
        if (preg_match('/\.(?:php\d*|phtml|pht|phar|phps|cgi|pl|py|sh|htaccess|env)$/i', $relative)) {
            return null;
        }

        return $file;
    }
}
