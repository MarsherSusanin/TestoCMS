<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PublishSchedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PublicationSchedulerConcurrencyTest extends TestCase
{
    public function test_two_scheduler_processes_execute_each_due_job_once(): void
    {
        $directory = sys_get_temp_dir().'/cms-scheduler-concurrency-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($directory);
        $database = $directory.'/fixture.sqlite';
        touch($database);
        $original = DB::getDefaultConnection();
        $workers = [];
        config(['database.connections.scheduler_fixture' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 10000]]);
        DB::setDefaultConnection('scheduler_fixture');
        try {
            Artisan::call('migrate', ['--force' => true]);
            DB::statement('PRAGMA journal_mode=WAL');
            $page = Page::create(['status' => 'draft', 'page_type' => 'landing']);
            $job = PublishSchedule::create(['entity_type' => 'page', 'entity_id' => $page->id, 'action' => 'publish', 'due_at' => now()->subMinute()]);
            $script = <<<'WORKER'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=10000');
\Illuminate\Support\Facades\DB::listen(function ($query) use ($argv) {
    if (str_starts_with($query->sql, 'select "id" from "publish_schedules"')) {
        file_put_contents($argv[2].'/ready-'.$argv[3], 'ready');
        $deadline = microtime(true)+10;
        while (!file_exists($argv[2].'/go')) {
            if (microtime(true)>$deadline) {throw new RuntimeException('Scheduler barrier timed out.');}
            usleep(10000);
        }
    }
});
echo app(\App\Modules\Ops\Services\PublishSchedulerService::class)->runDue();
WORKER;
            file_put_contents($directory.'/worker.php', $script);
            $environment = ['APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'CMS_SEED_DEMO_CONTENT' => 'false'];
            foreach (['a', 'b'] as $name) {
                $workers[$name] = new Process([PHP_BINARY, $directory.'/worker.php', base_path(), $directory, $name], base_path(), $environment, null, 20);
                $workers[$name]->start();
            }
            $deadline = microtime(true) + 12;
            while (! file_exists($directory.'/ready-a') || ! file_exists($directory.'/ready-b')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers failed to reach due-query barrier: '.implode(' ', array_map(fn (Process $worker) => $worker->getErrorOutput(), $workers)));
                }
                usleep(10000);
            }
            touch($directory.'/go');
            $processed = 0;
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $processed += (int) $worker->getOutput();
            }
            $this->assertSame(1, $processed);
            $this->assertSame('published', $page->fresh()->status);
            $this->assertNotNull($job->fresh()->executed_at);
            $this->assertSame(1, DB::table('audit_logs')->where('action', 'scheduler.publish')->where('entity_id', $page->id)->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            DB::setDefaultConnection($original);
            DB::purge('scheduler_fixture');
            File::deleteDirectory($directory);
        }
    }
}
