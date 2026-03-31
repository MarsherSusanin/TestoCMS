<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('timezone', 64)->default('Asia/Vladivostok');
            $table->string('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('booking_locations')->nullOnDelete();
            $table->string('name');
            $table->string('resource_type', 40)->default('staff');
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('booking_locations')->nullOnDelete();
            $table->foreignId('featured_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedInteger('slot_step_minutes')->default(30);
            $table->unsignedInteger('buffer_before_minutes')->default(0);
            $table->unsignedInteger('buffer_after_minutes')->default(0);
            $table->unsignedInteger('booking_horizon_days')->default(90);
            $table->unsignedInteger('lead_time_minutes')->default(60);
            $table->string('confirmation_mode', 20)->default('manual');
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->string('price_currency', 3)->default('RUB');
            $table->string('price_label')->nullable();
            $table->string('cta_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_service_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title');
            $table->string('slug');
            $table->string('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('booking_service_resource', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('booking_resources')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'resource_id']);
        });

        Schema::create('booking_availability_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('booking_locations')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('booking_resources')->nullOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('slot_step_minutes')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_availability_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('booking_locations')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('booking_resources')->nullOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_closed')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_slot_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('booking_locations')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('booking_resources')->nullOnDelete();
            $table->foreignId('source_rule_id')->nullable()->constrained('booking_availability_rules')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('capacity_total')->default(1);
            $table->unsignedInteger('reserved_count')->default(0);
            $table->unsignedInteger('confirmed_count')->default(0);
            $table->string('status', 20)->default('open');
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'location_id', 'resource_id', 'starts_at'], 'booking_slot_unique');
        });

        Schema::create('booking_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('booking_locations')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('booking_resources')->nullOnDelete();
            $table->foreignId('slot_occurrence_id')->nullable()->constrained('booking_slot_occurrences')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_comment')->nullable();
            $table->string('status', 20)->default('requested');
            $table->string('invoice_status', 20)->default('pending');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('source', 32)->default('public_page');
            $table->text('internal_notes')->nullable();
            $table->dateTime('hold_expires_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('subscribed_events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained('booking_webhook_endpoints')->cascadeOnDelete();
            $table->string('event_name');
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
        Schema::dropIfExists('booking_webhook_deliveries');
        Schema::dropIfExists('booking_webhook_endpoints');
        Schema::dropIfExists('booking_bookings');
        Schema::dropIfExists('booking_slot_occurrences');
        Schema::dropIfExists('booking_availability_exceptions');
        Schema::dropIfExists('booking_availability_rules');
        Schema::dropIfExists('booking_service_resource');
        Schema::dropIfExists('booking_service_translations');
        Schema::dropIfExists('booking_services');
        Schema::dropIfExists('booking_resources');
        Schema::dropIfExists('booking_locations');
    }
};
