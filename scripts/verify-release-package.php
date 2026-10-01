<?php

// CI/local verification: no external database or application env is modified.
require dirname(__DIR__).'/vendor/autoload.php';
use App\Modules\Updates\Services\CorePackageApplier;
use App\Modules\Updates\Services\PackageSignatureVerifier;
use App\Modules\Updates\Services\UpdatePreflightService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

[$script, $zip, $signature] = $argv + [null, null, null];
if (! is_file((string) $zip) || ! is_file((string) $signature)) {
    throw new RuntimeException('Usage: php scripts/verify-release-package.php package.zip package.sig');
}
$key = trim((string) getenv('CMS_UPDATE_PUBLIC_KEY'));
if ($key === '') {
    $secret = base64_decode(strtr(trim((string) getenv('CMS_UPDATE_PRIVATE_KEY')), '-_', '+/'), true);
    if ($secret !== false && strlen($secret) === SODIUM_CRYPTO_SIGN_SEEDBYTES) {
        $secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($secret));
    }
    if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        throw new RuntimeException('Set CMS_UPDATE_PUBLIC_KEY or the release signing key.');
    }
    $key = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret));
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);
config(['updates.public_key' => $key, 'updates.health_check_url' => '']);
if (! app(PackageSignatureVerifier::class)->verifyFile($zip, trim(file_get_contents($signature)), $key)) {
    throw new RuntimeException('Release signature does not match the ZIP.');
}
$inspection = app(CorePackageApplier::class)->inspectArchive($zip);
$result = app(UpdatePreflightService::class)->run($inspection['release']['version'], [
    'source' => 'manual', 'zip_path' => $zip, 'release' => $inspection['release'],
]);
if (! $result['ok']) {
    throw new RuntimeException('Signed release preflight failed: '.implode(' | ', $result['issues']));
}
echo "Signed ZIP signature, archive and compatibility preflight PASS\n";
