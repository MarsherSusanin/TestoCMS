<?php

namespace App\Console\Commands;

use App\Modules\Ops\Services\PublishSchedulerService;
use Illuminate\Console\Command;

class RunPublishSchedulerCommand extends Command
{
    protected $signature = 'cms:publish-due';

    protected $description = 'Publish or unpublish content whose schedule is due';

    public function __construct(private readonly PublishSchedulerService $publishSchedulerService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->publishSchedulerService->runDue();
        $this->info("Processed {$count} schedule(s).");

        return self::SUCCESS;
    }
}
