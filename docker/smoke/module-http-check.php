<?php

use App\Models\CmsModule;
use Illuminate\Contracts\Console\Kernel;

// This checker installs/removes only fixed audit fixtures in a disposable stack.
if (getenv('CMS_DEPLOYMENT_SMOKE') !== '1') {
    throw new RuntimeException('Run only in an explicitly authorized disposable smoke stack.');
}
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$jar = tempnam(sys_get_temp_dir(), 'cms-module-http-');
$archive = tempnam(sys_get_temp_dir(), 'cms-module-zip-').'.zip';
$host = parse_url((string) config('app.url'), PHP_URL_HOST);
function moduleHttp(string $path, ?array $fields = null): array
{
    global $jar, $host;
    $curl = curl_init('http://web'.$path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HTTPHEADER => ['Host: '.$host]]);
    if ($fields !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
    }
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException('Module HTTP request failed.');
    }
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return ['status' => $status, 'body' => $body];
}
function assertModule(bool $valid, string $label): void
{
    if (! $valid) {
        throw new RuntimeException($label);
    }
}
function moduleCsrf(string $path): string
{
    $response = moduleHttp($path);
    assertModule($response['status'] === 200 && preg_match('/name="_token"\s+value="([^"]+)"/', $response['body'], $token) === 1, 'CSRF form '.$path);

    return html_entity_decode($token[1]);
}
function fixtureZip(string $version): void
{
    global $archive;
    $zip = new ZipArchive;
    assertModule($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Create fixture archive');
    $zip->addFromString('module.json', json_encode(['id' => 'audit/deployment-fixture', 'name' => 'Deployment fixture', 'version' => $version, 'provider' => 'Audit\\Deployment\\ModuleServiceProvider', 'autoload' => ['psr-4' => ['Audit\\Deployment\\' => 'src/']], 'requires' => ['cms' => '>=1.0.0', 'php' => '>=8.2'], 'capabilities' => ['assets' => true]], JSON_THROW_ON_ERROR));
    $zip->addFromString('src/ModuleServiceProvider.php', '<?php namespace Audit\Deployment; class ModuleServiceProvider extends \Illuminate\Support\ServiceProvider {}');
    $zip->addFromString('public/fixture.css', '/* fixture '.$version.' */');
    $zip->addFromString('public/unsafe.php', '<?php echo "must never execute";');
    $zip->close();
}
try {
    assertModule(! CmsModule::query()->whereIn('module_key', ['audit/deployment-fixture', 'testocms/booking'])->exists(), 'Disposable stack must not already contain the checked modules.');
    $login = moduleHttp('/admin/login', ['_token' => moduleCsrf('/admin/login'), 'email' => config('setup.admin.email'), 'password' => config('setup.admin.password')]);
    assertModule($login['status'] === 302, 'Login through real FPM');
    fixtureZip('1.0.0');
    $install = moduleHttp('/admin/modules/upload', ['_token' => moduleCsrf('/admin/modules'), 'module_zip' => new CURLFile($archive, 'application/zip', 'fixture.zip')]);
    $module = CmsModule::query()->where('module_key', 'audit/deployment-fixture')->first();
    assertModule($install['status'] === 302 && $module !== null, 'ZIP installation through FPM');
    $asset = moduleHttp('/modules/audit--deployment-fixture/fixture.css');
    assertModule($asset['status'] === 200 && $asset['body'] === '/* fixture 1.0.0 */', 'Published nginx asset');
    $uid = posix_getpwnam('www-data')['uid'];
    assertModule(fileowner($module->install_path.'/module.json') === $uid && fileowner(public_path('modules/audit--deployment-fixture/fixture.css')) === $uid, 'FPM owns installed module and public assets');
    assertModule(moduleHttp('/modules/audit--deployment-fixture/unsafe.php')['status'] === 404, 'Executable public asset excluded');
    fixtureZip('1.0.1');
    $update = moduleHttp('/admin/modules/'.$module->id.'/update', ['_token' => moduleCsrf('/admin/modules'), 'module_zip' => new CURLFile($archive, 'application/zip', 'fixture.zip')]);
    assertModule($update['status'] === 302 && $module->fresh()->version === '1.0.1', 'ZIP update through FPM');
    assertModule(moduleHttp('/modules/audit--deployment-fixture/fixture.css')['body'] === '/* fixture 1.0.1 */', 'Updated asset immediately visible');
    $delete = moduleHttp('/admin/modules/'.$module->id, ['_token' => moduleCsrf('/admin/modules'), '_method' => 'DELETE']);
    assertModule($delete['status'] === 302 && ! CmsModule::query()->whereKey($module->id)->exists() && ! is_dir($module->install_path), 'Uninstall through FPM');
    assertModule(moduleHttp('/modules/audit--deployment-fixture/fixture.css')['status'] === 404, 'Removed asset inaccessible');
    $bundled = moduleHttp('/admin/modules/install-bundled/testocms--booking', ['_token' => moduleCsrf('/admin/modules')]);
    $booking = CmsModule::query()->where('module_key', 'testocms/booking')->first();
    assertModule($bundled['status'] === 302 && $booking !== null, 'Bundled installation through FPM');
    assertModule(moduleHttp('/modules/testocms--booking/booking-public.js')['status'] === 200, 'Bundled public asset');
    moduleHttp('/admin/modules/'.$booking->id, ['_token' => moduleCsrf('/admin/modules'), '_method' => 'DELETE']);
    assertModule(! CmsModule::query()->whereKey($booking->id)->exists(), 'Bundled fixture removed');
    echo json_encode(['zip_install' => true, 'zip_update' => true, 'zip_uninstall' => true, 'bundled_install' => true, 'bundled_asset' => true, 'public_script_excluded' => true, 'fpm_uid' => $uid, 'module_owner' => 'www-data', 'public_asset_owner' => 'www-data'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} finally {
    @unlink($jar);
    @unlink($archive);
}
