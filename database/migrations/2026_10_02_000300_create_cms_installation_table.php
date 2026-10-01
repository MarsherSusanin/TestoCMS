<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_installation', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('instance_id')->unique();
            $table->json('identity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_installation');
    }
};
