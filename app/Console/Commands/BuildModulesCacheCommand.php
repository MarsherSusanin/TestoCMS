<?php

namespace App\Console\Commands;

use App\Modules\Extensibility\Services\ModuleCacheService;
use Illuminate\Console\Command;
use Throwable;

class BuildModulesCacheCommand extends Command
{
    protected $signature = 'cms:modules:cache {--clear : Delete cache file instead of rebuild}';

    protected $description = 'Rebuild or clear TestoCMS modules runtime cache';

    public function __construct(private readonly ModuleCacheService $moduleCache)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ((bool) $this->option('clear')) {
            $this->moduleCache->clearCacheFile();
            $this->info('Modules cache file removed.');

            return self::SUCCESS;
        }

        try {
            $modules = $this->moduleCache->rebuildFromDatabase();
            $this->info(sprintf('Modules cache rebuilt: %d enabled module(s).', count($modules)));
            $this->line('Cache file: '.$this->moduleCache->cachePath());

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Modules cache rebuild failed. Existing cache file was left untouched: '.$this->moduleCache->cachePath());
            $this->line('Error: '.$e->getMessage());

            return self::FAILURE;
        }

    }
}
