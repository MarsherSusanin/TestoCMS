<?php

use App\Modules\Ops\Services\ScheduleIntegrityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Reject contradictory legacy windows BEFORE any DDL/data writes. This
        // also matters on MySQL where schema changes implicitly commit.
        $integrity = app(ScheduleIntegrityService::class);
        $integrity->assertValid();

        Schema::create('public_content_versions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('version');
        });
        DB::table('public_content_versions')->insert(['id' => 1, 'version' => (string) Str::uuid()]);

        Schema::table('publish_schedules', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancellation_reason')->nullable();
            // NULL for history, 1 for pending. SQL UNIQUE permits many NULLs,
            // so this portable constraint retains history and one pending/action.
            $table->unsignedTinyInteger('pending_slot')->nullable();
        });
        $integrity->normalizeLegacy();
        Schema::table('publish_schedules', function (Blueprint $table): void {
            $table->unique(['entity_type', 'entity_id', 'action', 'pending_slot'], 'publish_schedules_one_pending_action');
        });
    }

    public function down(): void
    {
        Schema::table('publish_schedules', function (Blueprint $table): void {
            $table->dropUnique('publish_schedules_one_pending_action');
            $table->dropIndex('publish_schedules_cancelled_at_index');
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancellation_reason', 'pending_slot']);
        });
        Schema::dropIfExists('public_content_versions');
    }
};
