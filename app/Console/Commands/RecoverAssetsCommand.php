<?php

namespace App\Console\Commands;

use App\Modules\Content\Services\AssetDeletionService;
use Illuminate\Console\Command;

class RecoverAssetsCommand extends Command
{
    protected $signature = 'cms:assets:recover';

    protected $description = 'Restore rolled-back media or purge committed private quarantines from durable journals';

    public function handle(AssetDeletionService $assets): int
    {
        $result = $assets->recover();
        $this->line('Restored: '.$result['restored'].'; purged: '.$result['purged'].'; failed: '.$result['failed']);

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
