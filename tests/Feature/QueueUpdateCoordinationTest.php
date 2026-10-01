<?php

namespace Tests\Feature;

use App\Modules\Updates\Services\CoordinatedQueueWorker;
use App\Modules\Updates\Services\UpdateOperationGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QueueUpdateCoordinationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CoordinationProbeJob::$probe = null;
        app(UpdateOperationGate::class)->release();
        Artisan::call('up');
        parent::tearDown();
    }

    private function exclusiveProbe(): int
    {
        $process = new Process([PHP_BINARY, '-r', '$h=fopen($argv[1],"c+");$ok=flock($h,LOCK_EX|LOCK_NB);if($ok){flock($h,LOCK_UN);}fclose($h);exit($ok?0:7);', storage_path('app/private/core-updates.lock')]);
        $process->run();

        return $process->getExitCode();
    }

    private function runOnce(): void
    {
        $this->assertInstanceOf(CoordinatedQueueWorker::class, app('queue.worker'));
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'audit', '--once' => true, '--sleep' => 0, '--tries' => 3]);
    }

    public function test_once_worker_keeps_lock_through_nested_sync_job_then_releases(): void
    {
        $observations = [];
        CoordinationProbeJob::$probe = function () use (&$observations): void {
            $observations[] = $this->exclusiveProbe();
        };
        Queue::connection('database')->push(new CoordinationProbeJob(false, true), '', 'audit');
        $this->runOnce();
        $this->assertSame([7, 7, 7], $observations);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(0, $this->exclusiveProbe());
    }

    public function test_failure_backoff_remains_locked_until_reservation_is_released(): void
    {
        $observations = [];
        Event::listen(JobExceptionOccurred::class, function () use (&$observations): void {
            $observations[] = $this->exclusiveProbe();
        });
        Event::listen(JobReleasedAfterException::class, function () use (&$observations): void {
            $observations[] = $this->exclusiveProbe();
        });
        Queue::connection('database')->push(new CoordinationProbeJob(true), '', 'audit');
        $this->runOnce();
        $this->assertSame([7, 7], $observations);
        $job = DB::table('jobs')->first();
        $this->assertSame(1, $job->attempts);
        $this->assertNull($job->reserved_at);
        $this->assertSame(0, $this->exclusiveProbe());
    }

    public function test_exclusive_update_gate_prevents_database_pop_reservation(): void
    {
        Queue::connection('database')->push(new CoordinationProbeJob, '', 'audit');
        $other = new UpdateOperationGate;
        $this->assertTrue($other->acquire(true));
        try {
            $this->runOnce();
            $job = DB::table('jobs')->first();
            $this->assertSame(0, $job->attempts);
            $this->assertNull($job->reserved_at);
        } finally {
            $other->release();
        }
        $this->assertSame(0, $this->exclusiveProbe());
    }

    public function test_empty_queue_does_not_leave_a_shared_lock_held(): void
    {
        $this->runOnce();
        $this->assertSame(0, $this->exclusiveProbe());
    }
}

class CoordinationProbeJob implements ShouldQueue
{
    use Queueable;

    public static mixed $probe = null;

    public int $tries = 3;

    public int $backoff = 1;

    public function __construct(public bool $fail = false, public bool $nested = false) {}

    public function handle(): void
    {
        if (is_callable(self::$probe)) {
            (self::$probe)();
        }
        if ($this->nested) {
            Queue::connection('sync')->push(new self);
            if (is_callable(self::$probe)) {
                (self::$probe)();
            }
        }
        if ($this->fail) {
            throw new RuntimeException('Audit failure requiring backoff.');
        }
    }
}
