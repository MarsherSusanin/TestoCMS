<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_mutation_guards', function (Blueprint $table): void {
            $table->string('key', 32)->primary();
        });

        DB::table('cms_mutation_guards')->insert([
            ['key' => 'media'],
            ['key' => 'categories'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_mutation_guards');
    }
};
