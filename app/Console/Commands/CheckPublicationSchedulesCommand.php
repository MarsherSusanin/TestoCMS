<?php

namespace App\Console\Commands;

use App\Modules\Ops\Services\ScheduleIntegrityService;
use Illuminate\Console\Command;

class CheckPublicationSchedulesCommand extends Command
{
    protected $signature = 'cms:schedules:check';

    protected $description = 'Read-only publication schedule preflight (also supports legacy schema)';

    public function handle(ScheduleIntegrityService $integrity): int
    {
        $report = $integrity->check();
        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($report['errors'] as $error) {
            $this->error($error);
        }
        if ($report['errors'] === []) {
            $this->info('Publication schedules passed preflight. No data was changed.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
