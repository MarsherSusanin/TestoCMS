<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The hot public-listing predicate is status = 'published' AND published_at <=
 * now() (see Post/Page scopePublished). Only two independent single-column
 * indexes existed; add a composite so the listing query is sargable.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'posts'] as $table) {
            // Idempotent: an ops person may have created this exact index by
            // hand before the migration ran; CREATE INDEX would then fail.
            if (Schema::hasIndex($table, $table.'_status_published_at_index')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->index(['status', 'published_at'], $table.'_status_published_at_index');
            });
        }
    }

    public function down(): void
    {
        foreach (['pages', 'posts'] as $table) {
            if (! Schema::hasIndex($table, $table.'_status_published_at_index')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex($table.'_status_published_at_index');
            });
        }
    }
};
