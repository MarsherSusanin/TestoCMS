<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Every process uses a private SQLite file or an explicitly opted-in disposable DB. */
class ContentMutationConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'delete wins over a new reference' => ['delete', 'reference', 204, 422],
            'reference wins over deletion' => ['reference', 'delete', 201, 409],
            'mutual reparent cannot create a cycle' => ['reparent-a', 'reparent-b', 200, 422],
            'duplicate category slug is a validation error' => ['category-slug', 'category-slug', 201, 422],
        ];
    }

    #[DataProvider('races')]
    public function test_competing_mutations_observe_the_committed_state(string $first, string $second, int $firstStatus, int $secondStatus): void
    {
        $directory = sys_get_temp_dir().'/testocms-p2-race-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($directory);
        $original = DB::getDefaultConnection();
        $workers = [];
        $driver = getenv('CMS_MUTATION_TEST_CONNECTION') ?: 'sqlite';
        $database = $directory.'/fixture.sqlite';
        $configuration = ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 15000];
        if ($driver === 'sqlite') {
            touch($database);
        } else {
            $database = (string) getenv('CMS_MUTATION_TEST_DATABASE');
            if (getenv('CMS_RUN_MUTATION_CONCURRENCY_TESTS') !== '1' || ! in_array($driver, ['pgsql', 'mysql'], true) || ! preg_match('/^testocms_p2_[a-z0-9_]+$/D', $database)) {
                $this->fail('External concurrency tests require explicit opt-in and a disposable testocms_p2_* database.');
            }
            $configuration = array_replace((array) config('database.connections.'.$driver), [
                'database' => $database,
                'host' => getenv('CMS_MUTATION_TEST_HOST') ?: '127.0.0.1',
                'port' => getenv('CMS_MUTATION_TEST_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306),
                'username' => getenv('CMS_MUTATION_TEST_USERNAME') ?: ($driver === 'pgsql' ? 'postgres' : 'root'),
                'password' => getenv('CMS_MUTATION_TEST_PASSWORD') ?: '',
            ]);
        }
        config(['database.connections.p2_mutation_fixture' => $configuration, 'cms.seed_demo_content' => false]);
        DB::setDefaultConnection('p2_mutation_fixture');

        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA journal_mode=WAL');
            }
            app(RolesAndPermissionsSeeder::class)->run();
            $actor = User::create(['name' => 'Race', 'login' => 'race', 'email' => 'race@p2.invalid', 'password' => 'password', 'status' => 'active']);
            $actor->assignRole('superadmin');
            $path = 'assets/p2-race-'.bin2hex(random_bytes(8)).'.txt';
            File::ensureDirectoryExists(storage_path('app/public/assets'));
            file_put_contents(storage_path('app/public/'.$path), 'original file');
            $asset = Asset::create(['disk' => 'public', 'storage_path' => $path, 'public_url' => '/storage/'.$path, 'type' => 'document', 'mime_type' => 'text/plain', 'size' => 13]);
            $a = Category::create(['is_active' => true]);
            $b = Category::create(['is_active' => true]);
            foreach ([$a, $b] as $category) {
                $category->translations()->create(['locale' => 'ru', 'title' => 'Category '.$category->id, 'slug' => 'category-'.$category->id]);
            }

            $script = <<<'WORKER'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
    \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=15000');
}
config(['cms.seed_demo_content' => false]);
$armed = $argv[4] === 'first';
\Illuminate\Support\Facades\DB::listen(function ($query) use ($argv, &$armed): void {
    if ($armed && str_contains($query->sql, 'cms_mutation_guards')) {
        $armed = false;
        file_put_contents($argv[2].'/ready', 'ready');
        $deadline = microtime(true)+15;
        while (!is_file($argv[2].'/release')) {
            if (microtime(true)>$deadline) { throw new RuntimeException('Mutation barrier timed out.'); }
            usleep(10000);
        }
    }
});
$actor = \App\Models\User::findOrFail((int) $argv[5]);
file_put_contents($argv[2].'/attempt-'.$argv[4], 'attempt');
try {
    switch ($argv[3]) {
        case 'delete':
            app(\App\Modules\Content\Services\AssetDeletionService::class)->delete(\App\Models\Asset::findOrFail((int)$argv[6]));
            $status = 204;
            break;
        case 'reference':
            app(\App\Modules\Content\Services\PostContentService::class)->createFromValidated([
                'status'=>'draft', 'featured_asset_id'=>(int)$argv[6],
                'translations'=>[['locale'=>'ru','title'=>'Race reference','slug'=>'race-reference','content_html'=>'<p>Body</p>']],
            ], $actor);
            $status = 201;
            break;
        case 'category-slug':
            app(\App\Modules\Content\Services\CategoryContentService::class)->create([
                'translations'=>[['locale'=>'ru','title'=>'Concurrent category','slug'=>'concurrent-slug']],
            ], $actor);
            $status = 201;
            break;
        default:
            $own = $argv[3]==='reparent-a' ? (int)$argv[7] : (int)$argv[8];
            $parent = $argv[3]==='reparent-a' ? (int)$argv[8] : (int)$argv[7];
            app(\App\Modules\Content\Services\CategoryContentService::class)->update(\App\Models\Category::findOrFail($own), ['parent_id'=>$parent], $actor, true);
            $status = 200;
    }
    echo json_encode(['status'=>$status]);
} catch (\App\Modules\Content\Exceptions\AssetInUseException $e) {
    echo json_encode(['status'=>409]);
} catch (\Illuminate\Validation\ValidationException $e) {
    echo json_encode(['status'=>422,'fields'=>array_keys($e->errors())]);
}
WORKER;
            file_put_contents($directory.'/worker.php', $script);
            $environment = [
                'APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                'DB_CONNECTION' => $driver, 'DB_DATABASE' => $database,
                'DB_HOST' => (string) ($configuration['host'] ?? ''), 'DB_PORT' => (string) ($configuration['port'] ?? ''),
                'DB_USERNAME' => (string) ($configuration['username'] ?? ''), 'DB_PASSWORD' => (string) ($configuration['password'] ?? ''),
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'CMS_SEED_DEMO_CONTENT' => 'false',
            ];
            $workers['first'] = new Process([PHP_BINARY, $directory.'/worker.php', base_path(), $directory, $first, 'first', (string) $actor->id, (string) $asset->id, (string) $a->id, (string) $b->id], base_path(), $environment, null, 25);
            $workers['first']->start();
            $this->awaitFile($directory.'/ready', $workers['first']);
            $workers['second'] = new Process([PHP_BINARY, $directory.'/worker.php', base_path(), $directory, $second, 'second', (string) $actor->id, (string) $asset->id, (string) $a->id, (string) $b->id], base_path(), $environment, null, 25);
            $workers['second']->start();
            $this->awaitFile($directory.'/attempt-second', $workers['second']);
            touch($directory.'/release');
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            }
            $this->assertSame($firstStatus, json_decode($workers['first']->getOutput(), true)['status']);
            $this->assertSame($secondStatus, json_decode($workers['second']->getOutput(), true)['status']);
            if ($first === 'delete') {
                $this->assertNull($asset->fresh());
                $this->assertSame(0, Post::count());
                $this->assertFileDoesNotExist(storage_path('app/public/'.$path));
            } elseif ($first === 'reference') {
                $this->assertNotNull($asset->fresh());
                $this->assertSame((int) $asset->id, (int) Post::first()->featured_asset_id);
                $this->assertSame('original file', file_get_contents(storage_path('app/public/'.$path)));
            } elseif ($first === 'category-slug') {
                $this->assertSame(1, CategoryTranslation::where('locale', 'ru')->where('slug', 'concurrent-slug')->count());
            } else {
                $this->assertSame((int) $b->id, (int) $a->fresh()->parent_id);
                $this->assertNull($b->fresh()->parent_id);
            }
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (isset($path)) {
                @unlink(storage_path('app/public/'.$path));
            }
            DB::setDefaultConnection($original);
            DB::purge('p2_mutation_fixture');
            File::deleteDirectory($directory);
        }
    }

    private function awaitFile(string $path, Process $worker): void
    {
        $deadline = microtime(true) + 15;
        while (! is_file($path)) {
            if (! $worker->isRunning() || microtime(true) > $deadline) {
                $this->fail('Worker failed to reach the barrier: '.$worker->getErrorOutput().$worker->getOutput());
            }
            usleep(10000);
        }
    }
}
