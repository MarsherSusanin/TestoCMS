<?php

namespace App\Console\Commands;

use App\Modules\Content\Services\CategoryContentService;
use Illuminate\Console\Command;

class CheckCategoriesCommand extends Command
{
    protected $signature = 'cms:categories:check';

    protected $description = 'Read-only category hierarchy cycle check';

    public function handle(CategoryContentService $categories): int
    {
        $ids = $categories->cyclicIds();
        if ($ids !== []) {
            $this->error('Categories in cycles: '.implode(', ', $ids).'. Correct parent_id explicitly; no data was changed.');

            return self::FAILURE;
        }
        $this->info('Category hierarchy is acyclic. No data was changed.');

        return self::SUCCESS;
    }
}
