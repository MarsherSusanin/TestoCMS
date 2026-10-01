<?php

namespace App\Modules\Updates\Services;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;

/** Covers POP/reservation through failure backoff, including --once/listen. */
class CoordinatedQueueWorker extends Worker
{
    private UpdateOperationGate $gate;

    private bool $ownsPopLock = false;

    public static function wrap(Worker $worker, UpdateOperationGate $gate): self
    {
        $instance = new self($worker->manager, $worker->events, $worker->exceptions, $worker->isDownForMaintenance, $worker->resetScope);
        $instance->gate = $gate;
        $instance->cache = $worker->cache;
        $instance->name = $worker->name;

        return $instance;
    }

    protected function getNextJob($connection, $queue)
    {
        if (call_user_func($this->isDownForMaintenance) || ! $this->gate->acquire()) {
            return null;
        }
        $this->ownsPopLock = true;
        try {
            $job = parent::getNextJob($connection, $queue);
            if ($job === null) {
                $this->releasePopLock();
            }

            return $job;
        } catch (\Throwable $e) {
            $this->releasePopLock();
            throw $e;
        }
    }

    protected function runJob($job, $connectionName, WorkerOptions $options)
    {
        try {
            parent::runJob($job, $connectionName, $options);
        } finally {
            $this->releasePopLock();
        }
    }

    private function releasePopLock(): void
    {
        if ($this->ownsPopLock) {
            $this->ownsPopLock = false;
            $this->gate->release();
        }
    }
}
