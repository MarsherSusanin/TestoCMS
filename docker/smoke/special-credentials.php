<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

if (getenv('CMS_DEPLOYMENT_SMOKE') !== '1') {
    throw new RuntimeException('Use only in a disposable smoke stack.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$environment = Dotenv\Dotenv::parse((string) file_get_contents(app()->environmentFilePath()));
$expected = json_decode((string) file_get_contents(base_path('expected.json')), true, 512, JSON_THROW_ON_ERROR);
$admin = User::query()->where('email', 'special@example.test')->sole();
$result = [
    'writer_runtime_password_match' => hash('sha256', (string) config('database.connections.pgsql.password')) === $expected['sha256'],
    'pdo_authenticated' => DB::selectOne('select current_database() as name')->name === 'p2_special_fixture',
    'admin_password_matches' => Hash::check($environment['CMS_ADMIN_PASSWORD'], $admin->password),
    'readonly_env_unchanged' => hash_file('sha256', app()->environmentFilePath()) === $expected['env_sha256'],
    'shell_substitution_not_executed' => ! is_file('/tmp/cms-env-backtick-executed') && ! is_file('/tmp/cms-env-dollar-executed'),
];
foreach ($result as $value) {
    if (! $value) {
        throw new RuntimeException('Special credential integration failed.');
    }
}
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
