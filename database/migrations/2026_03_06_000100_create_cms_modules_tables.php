<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('module_key', 190)->unique();
            $table->string('name', 190);
            $table->string('version', 64);
            $table->text('description')->nullable();
            $table->string('author', 190)->nullable();
            $table->string('install_path');
            $table->string('provider', 255);
            $table->string('checksum', 128)->nullable();
            $table->boolean('enabled')->default(false)->index();
            $table->string('status', 32)->default('installed')->index();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('updated_at_module')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_module_install_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('module_key', 190)->index();
            $table->string('action', 64)->index();
            $table->string('status', 32)->index();
            $table->json('context')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_module_install_logs');
        Schema::dropIfExists('cms_modules');
    }
};
