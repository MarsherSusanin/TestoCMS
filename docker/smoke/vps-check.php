<?php

use App\Models\User;
use App\Modules\Setup\Services\PublicMediaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$jar = tempnam(sys_get_temp_dir(), 'cms-vps-smoke-');
$host = parse_url((string) config('app.url'), PHP_URL_HOST);
$port = parse_url((string) config('app.url'), PHP_URL_PORT);
$host .= $port === null ? '' : ':'.$port;
$directory = null;
function httpCheck(string $path, string $method = 'GET', array $headers = [], ?array $post = null): array
{
    global $jar, $host;
    $curl = curl_init('http://web'.$path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HTTPHEADER => array_merge(['Host: '.$host], $headers)]);
    if ($method === 'HEAD') {
        curl_setopt($curl, CURLOPT_NOBODY, true);
    }
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($curl);
    if ($raw === false) {
        throw new RuntimeException('Internal HTTP check failed.');
    }
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $offset = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $offset);
    $body = substr($raw, $offset);
    curl_close($curl);

    return compact('status', 'head', 'body');
}
function verify(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
}
try {
    $health = httpCheck('/up');
    verify($health['status'] === 200, 'health /up');
    $healthz = httpCheck('/healthz');
    verify($healthz['status'] === 200, 'health /healthz');
    $login = httpCheck('/admin/login');
    verify($login['status'] === 200, 'login form');
    verify((bool) preg_match('/name="_token"\s+value="([^"]+)"/', $login['body'], $token), 'csrf token');
    $signed = httpCheck('/admin/login', 'POST', [], ['_token' => html_entity_decode($token[1]), 'email' => config('setup.admin.email'), 'password' => config('setup.admin.password')]);
    verify($signed['status'] === 302, 'admin login credentials');
    $dashboard = httpCheck('/admin');
    verify(in_array($dashboard['status'], [200, 302], true), 'admin dashboard');
    $pages = httpCheck('/admin/pages');
    verify($pages['status'] === 200, 'authenticated admin pages');
    $media = app(PublicMediaService::class);
    $fixture = 'vps-smoke-'.bin2hex(random_bytes(6));
    $directory = config('filesystems.disks.public.root').'/'.$fixture;
    File::ensureDirectoryExists($directory);
    file_put_contents($directory.'/clip.txt', '0123456789');
    $get = httpCheck('/storage/'.$fixture.'/clip.txt');
    verify($get['status'] === 200 && $get['body'] === '0123456789', 'public media GET');
    $head = httpCheck('/storage/'.$fixture.'/clip.txt', 'HEAD');
    verify($head['status'] === 200 && $head['body'] === '' && preg_match('/content-length:\s*10/i', $head['head']), 'public media HEAD');
    $range = httpCheck('/storage/'.$fixture.'/clip.txt', 'GET', ['Range: bytes=2-5']);
    verify($range['status'] === 206 && $range['body'] === '2345', 'public media Range');
    verify((bool) preg_match('/^etag:\s*(.+)$/mi', $get['head'], $originalEtag), 'initial media ETag');
    $replacement = 'replacement-content';
    file_put_contents($directory.'/clip.txt', $replacement);
    $overwritten = httpCheck('/storage/'.$fixture.'/clip.txt');
    verify($overwritten['status'] === 200 && $overwritten['body'] === $replacement, 'overwritten media GET');
    verify((bool) preg_match('/^etag:\s*(.+)$/mi', $overwritten['head'], $replacementEtag)
        && trim($replacementEtag[1]) !== trim($originalEtag[1]), 'overwritten media ETag');
    $overwrittenHead = httpCheck('/storage/'.$fixture.'/clip.txt', 'HEAD');
    verify($overwrittenHead['status'] === 200 && $overwrittenHead['body'] === ''
        && preg_match('/^content-length:\s*'.strlen($replacement).'\s*$/mi', $overwrittenHead['head']), 'overwritten media HEAD');
    $conditional = httpCheck('/storage/'.$fixture.'/clip.txt', 'GET', ['If-None-Match: '.trim($originalEtag[1])]);
    verify($conditional['status'] === 200 && $conditional['body'] === $replacement, 'old media ETag revalidation');
    file_put_contents($directory.'/fresh.txt', 'fresh');
    $fresh = httpCheck('/storage/'.$fixture.'/fresh.txt');
    verify($fresh['status'] === 200 && $fresh['body'] === 'fresh', 'fresh upload visibility');
    unlink($directory.'/fresh.txt');
    verify(httpCheck('/storage/'.$fixture.'/fresh.txt')['status'] === 404, 'deleted media visibility');
    File::deleteDirectory($directory);
    $admin = User::query()->where('email', config('setup.admin.email'))->firstOrFail();
    $dump = new Process(['pg_dump', '--version']);
    $dump->mustRun();
    verify((bool) preg_match('/PostgreSQL\) 16\./', $dump->getOutput()), 'PG16 client major');
    $state = ['health_up' => $health['status'], 'healthz' => $healthz['status'], 'login_form' => $login['status'], 'login_post' => $signed['status'], 'admin_pages' => $pages['status'], 'media_get' => $get['status'], 'media_head' => $head['status'], 'media_range' => $range['status'], 'uploads_and_deletes' => true, 'installed' => is_file(storage_path('installed')), 'admin_count' => User::count(), 'admin_hash_fingerprint' => hash('sha256', $admin->password), 'provided_password_valid' => Hash::check((string) config('setup.admin.password'), $admin->password), 'dotenv_shell_not_executed' => ! file_exists('/tmp/testocms-vps-shell-should-not-exist'), 'env_sha256' => hash_file('sha256', base_path('.env')), 'database_driver' => config('database.default'), 'database_version' => DB::selectOne('select version() as version')->version, 'physical_media_link_absent' => ! is_link(public_path('storage')) && ! is_dir(public_path('storage'))];
    $state['pg_dump_version'] = trim($dump->getOutput());
    $state['overwrite_get_head_etag_conditional'] = true;
    $state['overwrite_length'] = strlen($replacement);
    $state['overwrite_conditional_old_etag_status'] = $conditional['status'];
    echo json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Smoke failed: '.$error->getMessage()."\n");
    exit(1);
} finally {
    if ($directory !== null) {
        File::deleteDirectory($directory);
    }
    @unlink($jar);
}
