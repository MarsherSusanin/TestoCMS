<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            $table->longText('custom_head_html')->nullable()->after('canonical_url');
        });

        Schema::table('page_translations', function (Blueprint $table): void {
            $table->longText('custom_head_html')->nullable()->after('canonical_url');
        });
    }

    public function down(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            $table->dropColumn('custom_head_html');
        });

        Schema::table('page_translations', function (Blueprint $table): void {
            $table->dropColumn('custom_head_html');
        });
    }
};

