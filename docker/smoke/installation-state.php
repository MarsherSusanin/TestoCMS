<?php

use App\Models\User;
use App\Modules\Setup\Services\InstallationIdentityService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

if (getenv('CMS_DEPLOYMENT_SMOKE') !== '1') {
    throw new RuntimeException('Use only in a disposable smoke stack.');
}
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app->make(InstallationIdentityService::class)->assertReady();
$admin = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'superadmin'))->orderBy('id')->firstOrFail();
$marker = json_decode((string) file_get_contents(storage_path('installed')), true, 512, JSON_THROW_ON_ERROR);
$curl = curl_init('http://web/up');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
if ($status !== 200) {
    throw new RuntimeException('HTTP health endpoint failed.');
}
echo json_encode(['instance_id' => $marker['instance_id'], 'identity_bound' => true, 'admin_count' => User::count(), 'admin_hash_fingerprint' => hash('sha256', $admin->password), 'provided_password_valid' => Hash::check((string) config('setup.admin.password'), $admin->password), 'env_sha256' => hash_file('sha256', $app->environmentFilePath()), 'app_environment' => $app->environment(), 'deployment_profile' => config('setup.deployment_profile'), 'public_root' => public_path(), 'private_local_keys' => is_file(storage_path('app/private/docker-bootstrap-keys.json')), 'http_health' => $status], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
