<?php

use App\Modules\Setup\Services\EnvWriterService;
use Illuminate\Contracts\Console\Kernel;

if (getenv('CMS_DEPLOYMENT_SMOKE') !== '1' || ($argv[1] ?? '') !== '--replace-fixture-env') {
    throw new RuntimeException('Use only in a disposable copy with --replace-fixture-env.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$password = 'Spec$ ${CMS_SHOULD_NOT_EXPAND} "quoted" \'single\' `touch /tmp/cms-env-backtick-executed` $(touch /tmp/cms-env-dollar-executed) # пробел';
$data = ['deployment_profile' => 'docker_vps', 'db_connection' => 'pgsql', 'db_host' => 'db', 'db_port' => '5432', 'db_database' => 'p2_special_fixture', 'db_username' => 'p2_special_fixture', 'db_password' => $password, 'app_url' => 'http://127.0.0.1:18976', 'admin_name' => 'Special Fixture', 'admin_email' => 'special@example.test', 'admin_login' => 'special', 'admin_password' => $password];
$content = app(EnvWriterService::class)->buildEnvContent($data, '');
file_put_contents(app()->environmentFilePath(), $content);
chmod(app()->environmentFilePath(), 0600);
$parsed = Dotenv\Dotenv::parse($content);
if ($parsed['DB_PASSWORD'] !== $password || $parsed['CMS_ADMIN_PASSWORD'] !== $password) {
    throw new RuntimeException('Writer parse mismatch');
}
file_put_contents(base_path('expected.json'), json_encode(['sha256' => hash('sha256', $password), 'env_sha256' => hash('sha256', $content)]));
echo "writer_parse_match=true\n";
