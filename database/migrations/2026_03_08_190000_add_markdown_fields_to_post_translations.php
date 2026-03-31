<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            $table->string('content_format', 16)->default('html')->after('slug');
            $table->longText('content_markdown')->nullable()->after('content_html');
        });

        DB::table('post_translations')
            ->whereNull('content_format')
            ->update(['content_format' => 'html']);
    }

    public function down(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            $table->dropColumn(['content_format', 'content_markdown']);
        });
    }
};
