<?php

namespace App\Modules\Core\Services;

use App\Modules\Auth\Services\DefaultAdminBootstrapService;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Caching\Services\PublicContentVersionService;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LocalBaselineBootstrapService
{
    public function __construct(
        private readonly DefaultAdminBootstrapService $defaultAdminBootstrap,
        private readonly PageCacheService $pageCacheService,
    ) {}

    public function ensureBaseline(): void
    {
        try {
            if (! (bool) config('cms.seed_demo_content', false)) {
                return;
            }

            $this->defaultAdminBootstrap->ensureDefaultAdminExists();

            if (! $this->contentTablesExist() || ! $this->isContentBaselineEmpty()) {
                return;
            }

            DB::transaction(function (): void {
                // Serialize baseline initialization with all other public mutations.
                DB::table('public_content_versions')->where('id', 1)->lockForUpdate()->first();
                if ($this->isContentBaselineEmpty()) {
                    app(DemoContentSeeder::class)->run();
                    app(PublicContentVersionService::class)->bump();
                }
            }, 3);
            $this->pageCacheService->flushAll(false);

            Log::info('Local baseline demo content restored from empty database.');
        } catch (\Throwable $e) {
            Log::warning('Local baseline bootstrap skipped', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function contentTablesExist(): bool
    {
        foreach ($this->contentTables() as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function isContentBaselineEmpty(): bool
    {
        foreach ($this->contentTables() as $table) {
            if (DB::table($table)->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function contentTables(): array
    {
        return [
            'pages',
            'page_translations',
            'posts',
            'post_translations',
            'categories',
            'category_translations',
        ];
    }
}
