<?php

namespace App\Console\Commands;

use App\Modules\Setup\Services\SetupRedoService;
use Illuminate\Console\Command;
use Throwable;

class RecoverSetupCommand extends Command
{
    protected $signature = 'cms:setup:recover {operation : Operation UUID printed on an interrupted redo}';

    protected $description = 'Restore an interrupted setup redo using its private journal';

    public function handle(SetupRedoService $redo): int
    {
        try {
            $redo->recover((string) $this->argument('operation'));
            $this->info('Setup recovery completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
