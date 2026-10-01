<?php

namespace App\Modules\Setup\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PublicRootValidationService
{
    public function validate(string $root): void
    {
        if (! is_file(rtrim($root, '/').'/index.php')) {
            throw new RuntimeException('Configured public root has no index.php. Prepare the web root and configure the web server before setup: '.$root);
        }
        foreach ([(string) config('modules.modules_root', base_path('modules')), rtrim($root, '/').'/modules'] as $directory) {
            File::ensureDirectoryExists($directory);
            if (! is_writable($directory)) {
                throw new RuntimeException('Module directory is not writable by the PHP runtime: '.$directory);
            }
        }
    }
}
