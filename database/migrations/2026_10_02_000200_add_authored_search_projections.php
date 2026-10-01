<?php

use App\Modules\Caching\Services\PublicContentVersionService;
use App\Modules\Content\Services\SearchTextProjectionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['page_translations', 'post_translations'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->longText('search_text')->nullable());
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
                $projection = app(SearchTextProjectionService::class);
                foreach ($rows as $row) {
                    $text = $table === 'page_translations'
                        ? $projection->forPage((array) $row)
                        : $projection->forPost((array) $row);
                    // Preserve authored data, snapshots and editorial timestamps.
                    DB::table($table)->where('id', $row->id)->update(['search_text' => $text]);
                }
            });
            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->fullText('search_text', $table.'_search_text_fulltext'));
            } elseif (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement("CREATE INDEX {$table}_search_text_gin ON {$table} USING GIN (to_tsvector('simple', coalesce(search_text, ''))) ");
            }
        }
        app(PublicContentVersionService::class)->bump();
    }

    public function down(): void
    {
        foreach (['page_translations', 'post_translations'] as $table) {
            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropFullText($table.'_search_text_fulltext'));
            } elseif (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement("DROP INDEX IF EXISTS {$table}_search_text_gin");
            }
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('search_text'));
        }
    }
};
