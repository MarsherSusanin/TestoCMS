<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_services', 'resource_selection_mode')) {
                $table->string('resource_selection_mode', 30)
                    ->default('auto_assign')
                    ->after('confirmation_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table): void {
            if (Schema::hasColumn('booking_services', 'resource_selection_mode')) {
                $table->dropColumn('resource_selection_mode');
            }
        });
    }
};
