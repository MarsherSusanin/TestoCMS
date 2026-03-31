<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_core_update_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 64)->index();
            $table->string('status', 32)->index();
            $table->string('from_version', 64)->nullable();
            $table->string('to_version', 64)->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cms_core_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('backup_key', 190)->unique();
            $table->string('from_version', 64)->nullable();
            $table->string('to_version', 64)->nullable();
            $table->string('status', 32)->default('created')->index();
            $table->string('backup_path');
            $table->string('db_dump_path')->nullable();
            $table->string('manifest_path')->nullable();
            $table->string('restore_status', 32)->nullable()->index();
            $table->text('last_error')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_core_backups');
        Schema::dropIfExists('cms_core_update_logs');
    }
};
