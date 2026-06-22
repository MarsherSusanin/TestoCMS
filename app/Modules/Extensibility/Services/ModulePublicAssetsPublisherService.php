<?php

namespace App\Modules\Extensibility\Services;

use App\Models\CmsModule;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ModulePublicAssetsPublisherService
{
    /**
     * Static asset extensions that may be published into the public web root.
     * Anything not on this list (notably .php/.phtml/.phar and dotfiles) is
     * skipped so an installed module can never drop an executable webshell.
     *
     * @var list<string>
     */
    private const ALLOWED_ASSET_EXTENSIONS = [
        'css', 'js', 'mjs', 'cjs', 'map', 'json', 'webmanifest',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp4', 'webm', 'ogg', 'ogv', 'mp3', 'wav', 'm4a',
        'pdf', 'txt', 'csv', 'xml', 'vtt',
    ];

    public function publishFromInstallPath(string $installPath, string $moduleKey): void
    {
        $source = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public';
        if (! is_dir($source)) {
            return;
        }

        $this->hardenModulesRoot();

        $target = public_path('modules'.DIRECTORY_SEPARATOR.$this->moduleDirName($moduleKey));
        $this->deletePath($target);
        $this->copyDirectorySafe($source, $target);
    }

    public function removePublishedAssets(string $moduleKey): void
    {
        $this->deletePath(public_path('modules'.DIRECTORY_SEPARATOR.$this->moduleDirName($moduleKey)));
    }

    public function republishInstalledModules(): void
    {
        /** @var CmsModule $module */
        foreach (CmsModule::query()->get(['module_key', 'install_path']) as $module) {
            $installPath = trim((string) $module->install_path);
            if ($installPath === '' || (! is_dir($installPath) && ! is_link($installPath))) {
                continue;
            }

            $this->publishFromInstallPath($installPath, (string) $module->module_key);
        }
    }

    private function moduleDirName(string $moduleKey): string
    {
        return str_replace('/', '--', strtolower($moduleKey));
    }

    private function deletePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (is_dir($path)) {
            File::deleteDirectory($path);
        }
    }

    private function copyDirectorySafe(string $source, string $target): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $target = rtrim($target, DIRECTORY_SEPARATOR);

        if (! is_dir($source)) {
            throw new RuntimeException('Source module public directory not found: '.$source);
        }

        File::ensureDirectoryExists($target);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $pathname = (string) $item->getPathname();
            $relative = ltrim(str_replace($source, '', $pathname), DIRECTORY_SEPARATOR);
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            if (is_link($pathname)) {
                throw new RuntimeException('Module public assets contain symbolic links. This is not allowed.');
            }

            if ($item->isDir()) {
                File::ensureDirectoryExists($destination);

                continue;
            }

            if (! $this->isPublishableAsset($pathname)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            if (! copy($pathname, $destination)) {
                throw new RuntimeException('Failed to copy module public asset: '.$relative);
            }
        }
    }

    /**
     * Only allow known-static asset extensions into the web root. Executable
     * scripts (.php, .phar, …), dotfiles and extensionless files are refused
     * so module publishing can never produce a directly-executable webshell.
     */
    private function isPublishableAsset(string $pathname): bool
    {
        $basename = basename($pathname);
        if ($basename === '' || str_starts_with($basename, '.')) {
            return false;
        }

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if ($extension === '') {
            return false;
        }

        return in_array($extension, self::ALLOWED_ASSET_EXTENSIONS, true);
    }

    /**
     * Drop a hardened .htaccess at the modules public root so that, even on a
     * misconfigured Apache shared host, no script handler runs for published
     * module files. Defense-in-depth behind the extension allowlist above.
     */
    private function hardenModulesRoot(): void
    {
        $root = public_path('modules');
        File::ensureDirectoryExists($root);

        $htaccess = $root.DIRECTORY_SEPARATOR.'.htaccess';
        if (is_file($htaccess)) {
            return;
        }

        File::put($htaccess, <<<'HTACCESS'
            # Managed by TestoCMS — deny execution of any script handler under public/modules.
            RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .pht .phar .cgi .pl .py .sh
            RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .pht .phar
            <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|php7|php8|phps|pht|phar|cgi|pl|py|sh)$">
                <IfModule mod_authz_core.c>
                    Require all denied
                </IfModule>
                <IfModule !mod_authz_core.c>
                    Deny from all
                </IfModule>
            </FilesMatch>
            HTACCESS);
    }
}
