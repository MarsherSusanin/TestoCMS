<?php

/** Build a full BASELINE tree with ONLY updater/bootstrap changes; no new migrations. */
require dirname(__DIR__).'/vendor/autoload.php';
use Composer\Semver\VersionParser;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

$options = getopt('', ['base-ref:', 'version:', 'out:', 'assets-root:']);
foreach (['base-ref', 'version', 'out'] as $required) {
    if (empty($options[$required])) {
        throw new RuntimeException('Required: --'.$required);
    }
}
// Composer accepts alpha/beta/RC/dev prereleases, not arbitrary suffixes such as -bridge.
(new VersionParser)->normalize($options['version']);
$root = dirname(__DIR__);
$assets = $options['assets-root'] ?? $root.'/html_public/build';
if (! is_file($assets.'/manifest.json')) {
    throw new RuntimeException('Build frontend assets first or pass --assets-root with a Vite build directory.');
}
$filesystem = new Filesystem;
$temporary = sys_get_temp_dir().'/testocms-bridge-'.bin2hex(random_bytes(6));
$tree = $temporary.'/tree';
$filesystem->ensureDirectoryExists($tree);
$run = static function (array $command, ?string $cwd = null): void {
    $process = new Process($command, $cwd);
    $process->setTimeout(300);
    $process->mustRun();
};
try {
    $run(['git', 'archive', '--format=tar', '--output='.$temporary.'/base.tar', $options['base-ref']], $root);
    (new PharData($temporary.'/base.tar'))->extractTo($tree);
    // CorePackageApplier REPLACES entire top-level directories: a partial app is unsafe.
    foreach (['app/Modules/Updates/Services', 'app/Http/Controllers/Admin/CoreUpdateController.php', 'app/Http/Middleware/CoordinateCoreUpdates.php', 'app/Console/Commands/RecoverCoreUpdateCommand.php', 'app/Modules/Ops/Services/ScheduleIntegrityService.php', 'config/updates.php', 'composer.json', 'composer.lock'] as $relative) {
        $source = $root.'/'.$relative;
        $target = $tree.'/'.$relative;
        $filesystem->ensureDirectoryExists(dirname($target));
        if (is_dir($source)) {
            $filesystem->copyDirectory($source, $target);
        } else {
            $filesystem->copy($source, $target);
        }
    }
    $provider = $tree.'/app/Providers/AppServiceProvider.php';
    $content = file_get_contents($provider);
    $needle = '$this->app->singleton(AdminNavigationRegistry::class, AdminNavigationRegistry::class);';
    $hook = <<<'HOOK'
$this->app->singleton(\App\Modules\Updates\Services\UpdateOperationGate::class);
        $this->app->extend('queue.worker', fn ($worker) => \App\Modules\Updates\Services\CoordinatedQueueWorker::wrap($worker, $this->app->make(\App\Modules\Updates\Services\UpdateOperationGate::class)));
HOOK;
    $content = str_replace($needle, $hook."\n        ".$needle, $content, $count);
    $content = str_replace('$this->app->booting(', '$this->app->booted(', $content);
    if ($count !== 1) {
        throw new RuntimeException('Unsupported baseline AppServiceProvider; do not ship a guessed bridge.');
    }
    file_put_contents($provider, $content);
    $bootstrap = $tree.'/bootstrap/app.php';
    $content = file_get_contents($bootstrap);
    $needle = '->withMiddleware(function (Middleware $middleware) {';
    $content = str_replace($needle, $needle."\n        \$middleware->prepend(\\App\\Http\\Middleware\\CoordinateCoreUpdates::class);", $content, $count);
    if ($count !== 1) {
        throw new RuntimeException('Unsupported baseline middleware bootstrap.');
    }
    file_put_contents($bootstrap, $content);
    // Add a writer gate around the baseline scheduler WITHOUT introducing new schedule schema.
    $scheduler = $tree.'/app/Modules/Ops/Services/PublishSchedulerService.php';
    $content = file_get_contents($scheduler);
    $needle = 'public function runDue(): int';
    if (! str_contains($content, $needle)) {
        throw new RuntimeException('Unsupported baseline scheduler signature.');
    }
    $content = str_replace($needle, 'private function runDueUncoordinated(): int', $content);
    $wrapper = <<<'WRAPPER'
    public function runDue(): int
    {
        $gate=app(\App\Modules\Updates\Services\UpdateOperationGate::class);
        if(app()->isDownForMaintenance() || !$gate->acquire()){return 0;}
        try{return $this->runDueUncoordinated();}finally{$gate->release();}
    }
WRAPPER;
    $position = strrpos($content, '}');
    $content = substr($content, 0, $position).$wrapper."\n}\n";
    file_put_contents($scheduler, $content);
    $filesystem->copyDirectory($assets, $tree.'/html_public/build');
    $filesystem->copyDirectory($root.'/vendor', $tree.'/vendor');
    // Regenerate the map for the actual bridge tree; current-app classmap is unsafe here.
    $run(['composer', 'install', '--no-dev', '--no-scripts', '--no-interaction', '--optimize-autoloader'], $tree);
    $secret = base64_decode(strtr(trim((string) getenv('CMS_UPDATE_PRIVATE_KEY')), '-_', '+/'), true);
    if ($secret !== false && strlen($secret) === SODIUM_CRYPTO_SIGN_SEEDBYTES) {
        $secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($secret));
    }
    if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        throw new RuntimeException('CMS_UPDATE_PRIVATE_KEY must be the real release signing key.');
    }
    $package = $temporary.'/package';
    $filesystem->ensureDirectoryExists($package);
    foreach (['app', 'bootstrap', 'bundled-modules', 'config', 'database', 'html_public', 'lang', 'resources', 'routes', 'vendor', 'artisan', 'composer.json', 'composer.lock'] as $relative) {
        if (is_dir($tree.'/'.$relative)) {
            $filesystem->copyDirectory($tree.'/'.$relative, $package.'/'.$relative);
        } elseif (is_file($tree.'/'.$relative)) {
            $filesystem->copy($tree.'/'.$relative, $package.'/'.$relative);
        }
    }
    file_put_contents($package.'/release.json', json_encode(['artifact' => 'core-updater', 'version' => $options['version'], 'build' => $options['base-ref'], 'signed_at' => gmdate(DATE_ATOM), 'compat' => ['php' => '^8.2.0', 'cms_from' => '1.0.0'], 'bridge' => true], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    $out = $options['out'];
    $filesystem->ensureDirectoryExists(dirname($out));
    $zip = new ZipArchive;
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create bridge ZIP.');
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($package) + 1));
        }
    }
    $zip->close();
    file_put_contents($out.'.sig', base64_encode(sodium_crypto_sign_detached(file_get_contents($out), $secret)));
    echo "Bridge built and signed; no new migrations included.\n";
} finally {
    $filesystem->deleteDirectory($temporary);
}
