<?php

namespace App\Modules\Content\Services;

use Illuminate\Support\Facades\DB;

class SlugUniquenessService
{
    public function duplicateSeed(string $slug): string
    {
        $base = trim(strtolower($slug), '/');
        if ($base === '') {
            return 'copy';
        }

        return $base.'-copy';
    }

    public function uniquePageSlug(string $locale, string $baseSlug, ?int $excludePageId = null): string
    {
        return $this->uniqueSlug(
            translationTable: 'page_translations',
            ownerColumn: 'page_id',
            locale: $locale,
            baseSlug: $baseSlug,
            excludeOwnerId: $excludePageId,
        );
    }

    public function uniquePostSlug(string $locale, string $baseSlug, ?int $excludePostId = null): string
    {
        return $this->uniqueSlug(
            translationTable: 'post_translations',
            ownerColumn: 'post_id',
            locale: $locale,
            baseSlug: $baseSlug,
            excludeOwnerId: $excludePostId,
        );
    }

    private function uniqueSlug(
        string $translationTable,
        string $ownerColumn,
        string $locale,
        string $baseSlug,
        ?int $excludeOwnerId
    ): string {
        $locale = strtolower(trim($locale));
        $base = trim(strtolower($baseSlug), '/');
        if ($base === '') {
            $base = 'item';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->slugExists($translationTable, $ownerColumn, $locale, $candidate, $excludeOwnerId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(
        string $translationTable,
        string $ownerColumn,
        string $locale,
        string $slug,
        ?int $excludeOwnerId
    ): bool {
        $query = DB::table($translationTable)
            ->where('locale', $locale)
            ->where('slug', $slug);

        if ($excludeOwnerId !== null) {
            $query->where($ownerColumn, '!=', $excludeOwnerId);
        }

        return $query->exists();
    }
}
