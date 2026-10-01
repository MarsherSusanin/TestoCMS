<?php

namespace Tests\Feature;

use App\Modules\Ops\Services\ScheduleIntegrityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicationScheduleMigrationTest extends TestCase
{
    /** Run against a separate legacy schema, without booting current Eloquent models. */
    private function legacy(callable $test): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.schedule_legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('schedule_legacy');
        try {
            foreach (['pages', 'posts'] as $name) {
                Schema::create($name, function (Blueprint $table): void {
                    $table->id();
                    $table->string('status');
                    $table->timestamps();
                });
            }
            Schema::create('publish_schedules', function (Blueprint $table): void {
                $table->id();
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->string('action');
                $table->timestamp('due_at');
                $table->timestamp('executed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
            $test();
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('schedule_legacy');
        }
    }

    private function insertJob(int $id, int $entityId, string $action, string $due): void
    {
        DB::table('publish_schedules')->insert(['id' => $id, 'entity_type' => 'page', 'entity_id' => $entityId, 'action' => $action, 'due_at' => $due, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
    }

    public function test_contradictory_legacy_windows_fail_before_any_schema_or_data_writes(): void
    {
        $this->legacy(function (): void {
            DB::table('pages')->insert(['id' => 1, 'status' => 'scheduled']);
            $this->insertJob(11, 1, 'publish', '2030-01-03 12:00:00');
            $this->insertJob(12, 1, 'unpublish', '2030-01-02 12:00:00');
            $before = DB::table('publish_schedules')->orderBy('id')->get()->toJson();
            $report = app(ScheduleIntegrityService::class)->check();
            $this->assertStringContainsString('#11', implode(' ', $report['errors']));
            $this->assertStringContainsString('#12', implode(' ', $report['errors']));
            $migration = require database_path('migrations/2026_10_01_000100_harden_publication_state_and_schedules.php');
            try {
                $migration->up();
                $this->fail('A contradictory window must stop before DDL.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('preflight failed', $exception->getMessage());
            }
            $this->assertFalse(Schema::hasTable('public_content_versions'));
            $this->assertFalse(Schema::hasColumn('publish_schedules', 'cancelled_at'));
            $this->assertSame($before, DB::table('publish_schedules')->orderBy('id')->get()->toJson());
            $this->assertSame('scheduled', DB::table('pages')->where('id', 1)->value('status'));
        });
    }

    public function test_latest_job_per_action_wins_and_legacy_unpublish_only_never_republishes(): void
    {
        $this->legacy(function (): void {
            DB::table('pages')->insert([['id' => 1, 'status' => 'scheduled'], ['id' => 2, 'status' => 'scheduled']]);
            $this->insertJob(21, 1, 'publish', '2030-01-01 12:00:00');
            $this->insertJob(22, 1, 'publish', '2030-01-02 12:00:00');
            $this->insertJob(23, 1, 'unpublish', '2030-01-03 12:00:00');
            $this->insertJob(24, 2, 'unpublish', '2030-01-03 12:00:00');
            $report = app(ScheduleIntegrityService::class)->check();
            $this->assertSame([], $report['errors']);
            $this->assertStringContainsString('Admin attention', implode(' ', $report['warnings']));
            $migration = require database_path('migrations/2026_10_01_000100_harden_publication_state_and_schedules.php');
            $migration->up();
            $this->assertNotNull(DB::table('publish_schedules')->where('id', 21)->value('cancelled_at'));
            $this->assertSame('legacy-superseded', DB::table('publish_schedules')->where('id', 21)->value('cancellation_reason'));
            $this->assertNull(DB::table('publish_schedules')->where('id', 22)->value('cancelled_at'));
            $this->assertSame('draft', DB::table('pages')->where('id', 1)->value('status'));
            $this->assertSame('scheduled', DB::table('pages')->where('id', 2)->value('status'));
            $this->assertTrue(Schema::hasTable('public_content_versions'));
        });
    }

    public function test_read_only_checker_does_not_modify_legacy_rows(): void
    {
        $this->legacy(function (): void {
            DB::table('pages')->insert(['id' => 1, 'status' => 'scheduled']);
            $this->insertJob(31, 1, 'publish', '2030-01-01 12:00:00');
            $this->insertJob(32, 1, 'publish', '2030-01-02 12:00:00');
            $before = DB::table('publish_schedules')->orderBy('id')->get()->toJson();
            $this->artisan('cms:schedules:check')->assertSuccessful();
            $this->assertSame($before, DB::table('publish_schedules')->orderBy('id')->get()->toJson());
            $this->assertFalse(Schema::hasColumn('publish_schedules', 'cancelled_at'));
        });
    }

    public function test_schedule_migration_rolls_back_its_indexes_and_can_be_applied_again(): void
    {
        $this->legacy(function (): void {
            DB::table('pages')->insert(['id' => 1, 'status' => 'draft']);
            $this->insertJob(41, 1, 'publish', '2030-01-01 12:00:00');
            $migration = require database_path('migrations/2026_10_01_000100_harden_publication_state_and_schedules.php');
            $migration->up();
            $migration->down();
            $this->assertFalse(Schema::hasColumn('publish_schedules', 'cancelled_at'));
            $this->assertFalse(Schema::hasTable('public_content_versions'));
            $this->assertSame('publish', DB::table('publish_schedules')->where('id', 41)->value('action'));
            $migration->up();
            $this->assertTrue(Schema::hasColumn('publish_schedules', 'cancelled_at'));
            $this->assertSame(1, (int) DB::table('publish_schedules')->where('id', 41)->value('pending_slot'));
        });
    }
}
