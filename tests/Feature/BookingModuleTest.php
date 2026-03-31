<?php

namespace Tests\Feature;

use App\Models\CmsModule;
use App\Models\User;
use App\Modules\Extensibility\Registry\ModuleWidgetRegistry;
use App\Modules\Extensibility\Services\EnabledModulePublicRoutesLoader;
use App\Modules\Extensibility\Services\ModuleInstallerService;
use App\Modules\Extensibility\Services\ModuleRuntimeService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use TestoCms\Booking\Models\BookingAvailabilityRule;
use TestoCms\Booking\Models\BookingBooking;
use TestoCms\Booking\Models\BookingLocation;
use TestoCms\Booking\Models\BookingResource;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Models\BookingServiceTranslation;
use TestoCms\Booking\Models\BookingWebhookDelivery;
use TestoCms\Booking\Models\BookingWebhookEndpoint;
use TestoCms\Booking\Services\BookingSlotProjectionService;
use Tests\TestCase;
use ZipArchive;

class BookingModuleTest extends TestCase
{
    use RefreshDatabase;

    private string $testingModulesRoot;

    private string $testingCacheFile;

    private string $testingLocalModulesRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteFileIfExists(base_path('bootstrap/cache/cms_modules.php'));

        $this->testingModulesRoot = storage_path('framework/testing/modules');
        $this->testingCacheFile = storage_path('framework/testing/cms_modules.php');
        $this->testingLocalModulesRoot = storage_path('framework/testing/modules-dev');

        File::deleteDirectory($this->testingModulesRoot);
        File::deleteDirectory($this->testingLocalModulesRoot);
        File::deleteDirectory(public_path('modules/testocms--booking'));
        $this->deleteFileIfExists($this->testingCacheFile);
        $this->deleteFileIfExists(base_path('bootstrap/cache/cms_modules.php'));
        EnabledModulePublicRoutesLoader::reset();

        config()->set('modules.modules_root', $this->testingModulesRoot);
        config()->set('modules.cache_file', $this->testingCacheFile);
        config()->set('modules.local_install_roots', [$this->testingLocalModulesRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testingModulesRoot);
        File::deleteDirectory($this->testingLocalModulesRoot);
        File::deleteDirectory(public_path('modules/testocms--booking'));
        $this->deleteFileIfExists($this->testingCacheFile);
        EnabledModulePublicRoutesLoader::reset();

        parent::tearDown();
    }

    public function test_booking_bundled_module_is_listed_and_can_be_installed_and_activated(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-admin@testocms.local', 'superadmin');

        $this->actingAs($user)
            ->get('/admin/modules')
            ->assertOk()
            ->assertSee('Bundled модули')
            ->assertSee('Booking')
            ->assertSee('Установить bundled-модуль');

        $this->actingAs($user)
            ->post('/admin/modules/install-bundled/testocms--booking', [
                'activate_now' => 1,
            ])
            ->assertRedirect('/admin/modules');

        $this->assertDatabaseHas('cms_modules', [
            'module_key' => 'testocms/booking',
            'enabled' => 1,
        ]);
        $this->assertTrue(Schema::hasTable('booking_services'));
        $module = CmsModule::query()->where('module_key', 'testocms/booking')->first();
        $this->assertNotNull($module);
        $this->assertSame('calendar-days', data_get($module->metadata, 'admin.nav.0.icon'));

        $this->actingAs($user)
            ->get('/admin/booking')
            ->assertOk()
            ->assertSee('Booking');

        $this->actingAs($user)
            ->get('/admin/pages')
            ->assertOk()
            ->assertSee('Расширения')
            ->assertSee('data-nav-icon="calendar-days"', false)
            ->assertSee('Booking');
    }

    public function test_existing_admin_gets_booking_permissions_and_sidebar_entry_after_module_activation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = $this->makeUser('booking-activator@testocms.local', 'superadmin');
        $admin = $this->makeUser('booking-existing-admin@testocms.local', 'admin');

        $this->actingAs($superadmin)
            ->post('/admin/modules/install-bundled/testocms--booking', [
                'activate_now' => 1,
            ])
            ->assertRedirect('/admin/modules');

        $this->actingAs($admin)
            ->get('/admin/pages')
            ->assertOk()
            ->assertSee('Расширения')
            ->assertSee('data-nav-icon="calendar-days"', false)
            ->assertSee('Booking');

        $this->actingAs($admin)
            ->get('/admin/booking')
            ->assertOk()
            ->assertSee('Booking');
    }

    public function test_bundled_install_recovers_orphaned_booking_directory_and_marks_card_as_recoverable(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-recover-bundled@testocms.local', 'superadmin');
        $installPath = $this->orphanedBookingInstallPath();

        $this->copyBundledBookingModule($installPath);

        $this->actingAs($user)
            ->get('/admin/modules')
            ->assertOk()
            ->assertSee('recoverable')
            ->assertSee('Восстановить модуль')
            ->assertSee($installPath, false);

        $this->actingAs($user)
            ->post('/admin/modules/install-bundled/testocms--booking', [
                'activate_now' => 1,
            ])
            ->assertRedirect('/admin/modules');

        $module = CmsModule::query()->where('module_key', 'testocms/booking')->first();
        $this->assertNotNull($module);
        $this->assertTrue((bool) $module->enabled);
        $this->assertSame('recovered_existing_directory', data_get($module->metadata, 'install_source.type'));
        $this->assertSame('bundled', data_get($module->metadata, 'install_source.requested_install_type'));
        $this->assertSame(base_path('bundled-modules/testocms-booking'), data_get($module->metadata, 'install_source.requested_source_path'));
        $this->assertTrue(Schema::hasTable('booking_services'));

        $this->actingAs($user)
            ->get('/admin/booking')
            ->assertOk()
            ->assertSee('Booking');
    }

    public function test_active_booking_module_survives_fresh_application_boot_without_module_cache_file(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-fresh-boot@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        $this->deleteFileIfExists($this->testingCacheFile);
        $this->deleteFileIfExists(base_path('bootstrap/cache/cms_modules.php'));
        $this->resetModuleRuntimeBootstrapState();
        EnabledModulePublicRoutesLoader::reset();

        $this->withFreshApplicationPreservingInMemoryDatabase(function () use ($user): void {
            $this->assertTrue(app('router')->has('booking.admin.dashboard'));
            $this->assertTrue(app('router')->has('booking.public.index'));

            $freshUser = User::query()->findOrFail($user->id);

            $this->actingAs($freshUser)
                ->get('/admin/booking')
                ->assertOk()
                ->assertSee('Booking');

            $this->actingAs($freshUser)
                ->get('/admin/pages')
                ->assertOk()
                ->assertSee('Расширения')
                ->assertSee('data-nav-icon="calendar-days"', false)
                ->assertSee('Booking');

            $this->get('/ru/book')
                ->assertOk()
                ->assertSee('Онлайн-бронирование услуг');
        });
    }

    public function test_active_booking_module_self_heals_poisoned_empty_module_cache_on_fresh_boot(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-empty-cache@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        app(\App\Modules\Extensibility\Services\ModuleCacheService::class)->writeCacheFile([]);
        $this->writeCachePayload(base_path('bootstrap/cache/cms_modules.php'), []);
        $this->assertSame([], $this->readTestingCacheModules());
        $this->assertSame([], $this->readCacheModules(base_path('bootstrap/cache/cms_modules.php')));

        $this->resetModuleRuntimeBootstrapState();
        EnabledModulePublicRoutesLoader::reset();

        $this->withFreshApplicationPreservingInMemoryDatabase(function () use ($user): void {
            $freshUser = User::query()->findOrFail($user->id);

            $this->actingAs($freshUser)
                ->get('/admin/booking')
                ->assertOk()
                ->assertSee('Booking');

            $this->actingAs($freshUser)
                ->get('/admin/pages')
                ->assertOk()
                ->assertSee('Расширения')
                ->assertSee('data-nav-icon="calendar-days"', false)
                ->assertSee('Booking');

            $this->assertSame('testocms/booking', $this->readCacheModules(base_path('bootstrap/cache/cms_modules.php'))[0]['module_key'] ?? null);
        });
    }

    public function test_stale_booking_cache_does_not_keep_routes_alive_when_module_registry_is_empty(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-stale-cache@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        $this->assertSame('testocms/booking', $this->readTestingCacheModules()[0]['module_key'] ?? null);

        CmsModule::query()->delete();
        $this->resetModuleRuntimeBootstrapState();
        EnabledModulePublicRoutesLoader::reset();

        $this->withFreshApplicationPreservingInMemoryDatabase(function () use ($user): void {
            $this->assertFalse(app('router')->has('booking.admin.dashboard'));
            $this->assertSame([], $this->readCacheModules(base_path('bootstrap/cache/cms_modules.php')));

            $freshUser = User::query()->findOrFail($user->id);

            $this->actingAs($freshUser)
                ->get('/admin/pages')
                ->assertOk()
                ->assertDontSee('Booking');

            $this->actingAs($freshUser)
                ->get('/admin/booking')
                ->assertNotFound();
        });
    }

    public function test_local_install_recovers_orphaned_existing_directory_without_overwriting_files(): void
    {
        $sourcePath = $this->makeLocalBookingSourceCopy();
        $installPath = $this->orphanedBookingInstallPath();

        $this->copyBundledBookingModule($installPath);
        file_put_contents($installPath.'/recovery-marker.txt', 'keep-me');

        $module = app(ModuleInstallerService::class)->installFromLocalPath($sourcePath);

        $this->assertSame($installPath, $module->install_path);
        $this->assertSame('recovered_existing_directory', data_get($module->metadata, 'install_source.type'));
        $this->assertSame('local_path', data_get($module->metadata, 'install_source.requested_install_type'));
        $this->assertSame(realpath($sourcePath), data_get($module->metadata, 'install_source.requested_source_path'));
        $this->assertNull($module->checksum);
        $this->assertFileExists($installPath.'/recovery-marker.txt');
    }

    public function test_zip_install_recovers_orphaned_existing_directory_without_overwriting_files(): void
    {
        $installPath = $this->orphanedBookingInstallPath();
        $sourcePath = $this->makeLocalBookingSourceCopy('zip-source');

        $this->copyBundledBookingModule($installPath);
        file_put_contents($installPath.'/recovery-marker.txt', 'keep-me');

        $zipPath = $this->makeModuleZipFromDirectory($sourcePath, 'booking-recovery.zip');
        $uploaded = new UploadedFile($zipPath, 'booking-recovery.zip', 'application/zip', null, true);

        $module = app(ModuleInstallerService::class)->installFromZip($uploaded);

        $this->assertSame($installPath, $module->install_path);
        $this->assertSame('recovered_existing_directory', data_get($module->metadata, 'install_source.type'));
        $this->assertSame('zip', data_get($module->metadata, 'install_source.requested_install_type'));
        $this->assertNull(data_get($module->metadata, 'install_source.requested_source_path'));
        $this->assertNull($module->checksum);
        $this->assertFileExists($installPath.'/recovery-marker.txt');

        $this->deleteFileIfExists($zipPath);
    }

    public function test_bundled_install_recovery_fails_when_existing_directory_belongs_to_another_module(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-recover-mismatch@testocms.local', 'superadmin');
        $installPath = $this->orphanedBookingInstallPath();

        $this->copyBundledBookingModule($installPath);

        $manifestPath = $installPath.'/module.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
        $manifest['id'] = 'acme/other-module';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->actingAs($user)
            ->from('/admin/modules')
            ->post('/admin/modules/install-bundled/testocms--booking')
            ->assertRedirect('/admin/modules')
            ->assertSessionHasErrors([
                'bundled' => 'Ошибка установки bundled-модуля: Target module directory belongs to another module: '.$installPath.' (acme/other-module)',
            ]);

        $this->assertDatabaseMissing('cms_modules', [
            'module_key' => 'testocms/booking',
        ]);
    }

    public function test_bundled_install_still_rejects_module_already_registered_in_database(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-recover-installed@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        $this->actingAs($user)
            ->from('/admin/modules')
            ->post('/admin/modules/install-bundled/testocms--booking')
            ->assertRedirect('/admin/modules')
            ->assertSessionHasErrors([
                'bundled' => 'Ошибка установки bundled-модуля: Module is already installed: testocms/booking',
            ]);
    }

    public function test_booking_public_flow_and_widget_registry_render_work_after_activation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-flow@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        $location = BookingLocation::query()->create([
            'name' => 'Main office',
            'timezone' => 'Asia/Vladivostok',
            'is_active' => true,
        ]);

        $service = BookingService::query()->create([
            'location_id' => $location->id,
            'duration_minutes' => 60,
            'slot_step_minutes' => 30,
            'booking_horizon_days' => 30,
            'lead_time_minutes' => 0,
            'confirmation_mode' => 'manual',
            'price_currency' => 'RUB',
            'is_active' => true,
        ]);

        BookingServiceTranslation::query()->create([
            'service_id' => $service->id,
            'locale' => 'ru',
            'title' => 'Консультация',
            'slug' => 'konsultatsiya',
            'short_description' => 'Краткое описание услуги',
            'full_description' => 'Полное описание услуги',
            'meta_title' => 'Консультация',
            'meta_description' => 'Описание',
        ]);

        $tomorrow = CarbonImmutable::now('Asia/Vladivostok')->addDay();
        BookingAvailabilityRule::query()->create([
            'service_id' => $service->id,
            'location_id' => $location->id,
            'weekday' => ((int) $tomorrow->dayOfWeekIso) % 7,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_step_minutes' => 30,
            'capacity' => 1,
            'is_active' => true,
        ]);

        app(BookingSlotProjectionService::class)->rebuildService($service->fresh(['rules', 'exceptions']) ?? $service);

        $this->get('/ru/book')
            ->assertOk()
            ->assertSee('Онлайн-бронирование услуг')
            ->assertSee('Консультация');

        $this->get('/ru/book/services/konsultatsiya')
            ->assertOk()
            ->assertSee('Полное описание услуги');

        $slotsResponse = $this->getJson('/ru/book/api/services/konsultatsiya/slots?date='.$tomorrow->toDateString())
            ->assertOk();

        $slotId = (int) data_get($slotsResponse->json(), 'data.0.id');
        $this->assertGreaterThan(0, $slotId);

        $this->postJson('/ru/book/api/services/konsultatsiya/book', [
            'slot_id' => $slotId,
            'customer_name' => 'Иван Клиент',
            'customer_email' => 'client@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested');

        $this->assertDatabaseHas('booking_bookings', [
            'service_id' => $service->id,
            'customer_name' => 'Иван Клиент',
            'status' => 'requested',
        ]);

        $rendered = app(ModuleWidgetRegistry::class)->render('testocms/booking', 'booking_service_card', [
            'service_slug' => 'konsultatsiya',
        ], [
            'locale' => 'ru',
        ]);

        $this->assertStringContainsString('data-booking-widget-endpoint', $rendered);
        $this->assertStringContainsString('booking-public.js', $rendered);
    }

    public function test_auto_assign_service_aggregates_slots_and_assigns_resource_inside_transaction(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-auto@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        [$location, $service, $resources, $date] = $this->buildResourceBackedService('auto_assign');

        $slotsResponse = $this->getJson('/ru/book/api/services/'.$service->translations()->firstOrFail()->slug.'/slots?date='.$date->toDateString())
            ->assertOk();

        $slotsResponse
            ->assertJsonPath('meta.resource_selection_mode', 'auto_assign')
            ->assertJsonPath('meta.requires_resource', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.available_count', 2);

        $slotId = (int) data_get($slotsResponse->json(), 'data.0.id');

        $bookingResponse = $this->postJson('/ru/book/api/services/'.$service->translations()->firstOrFail()->slug.'/book', [
            'slot_id' => $slotId,
            'customer_name' => 'Автоподбор',
            'customer_email' => 'auto@example.test',
        ])->assertCreated();

        $assignedResourceId = (int) data_get($bookingResponse->json(), 'data.resource_id');
        $this->assertTrue(in_array($assignedResourceId, $resources->pluck('id')->all(), true));
        $this->assertDatabaseHas('booking_bookings', [
            'service_id' => $service->id,
            'customer_name' => 'Автоподбор',
            'resource_id' => $assignedResourceId,
            'status' => 'requested',
        ]);
    }

    public function test_choose_resource_service_requires_resource_and_books_selected_resource(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-resource@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        [$location, $service, $resources, $date] = $this->buildResourceBackedService('choose_resource');
        $slug = $service->translations()->firstOrFail()->slug;
        $selectedResource = $resources->first();
        $this->assertNotNull($selectedResource);

        $this->getJson('/ru/book/api/services/'.$slug.'/slots?date='.$date->toDateString())
            ->assertOk()
            ->assertJsonPath('meta.resource_selection_mode', 'choose_resource')
            ->assertJsonPath('meta.requires_resource', true)
            ->assertJsonCount(0, 'data');

        $slotsResponse = $this->getJson('/ru/book/api/services/'.$slug.'/slots?date='.$date->toDateString().'&resource_id='.$selectedResource->id)
            ->assertOk()
            ->assertJsonPath('data.0.resource_id', $selectedResource->id);

        $slotId = (int) data_get($slotsResponse->json(), 'data.0.id');

        $this->postJson('/ru/book/api/services/'.$slug.'/book', [
            'slot_id' => $slotId,
            'customer_name' => 'Без ресурса',
        ])->assertStatus(422);

        $this->postJson('/ru/book/api/services/'.$slug.'/book', [
            'slot_id' => $slotId,
            'resource_id' => $selectedResource->id,
            'customer_name' => 'С ресурсом',
        ])
            ->assertCreated()
            ->assertJsonPath('data.resource_id', $selectedResource->id);

        $this->assertDatabaseHas('booking_bookings', [
            'service_id' => $service->id,
            'customer_name' => 'С ресурсом',
            'resource_id' => $selectedResource->id,
        ]);
    }

    public function test_admin_can_update_operational_booking_fields_and_view_day_calendar(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-ops@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        [$location, $service, $resources, $date] = $this->buildResourceBackedService('choose_resource');
        $slot = $service->fresh(['translations'])?->rules()->first();
        $this->assertNotNull($slot);

        $slotOccurrence = \TestoCms\Booking\Models\BookingSlotOccurrence::query()
            ->where('service_id', $service->id)
            ->orderBy('id')
            ->firstOrFail();

        $booking = BookingBooking::query()->create([
            'service_id' => $service->id,
            'location_id' => $location->id,
            'resource_id' => $slotOccurrence->resource_id,
            'slot_occurrence_id' => $slotOccurrence->id,
            'starts_at' => $slotOccurrence->starts_at,
            'ends_at' => $slotOccurrence->ends_at,
            'customer_name' => 'Ops Client',
            'status' => 'requested',
            'invoice_status' => 'pending',
            'payment_status' => 'unpaid',
            'source' => 'admin',
        ]);

        $this->actingAs($user)
            ->put(route('booking.admin.bookings.update', $booking), [
                'internal_notes' => 'Позвонить за час',
                'invoice_status' => 'issued',
                'payment_status' => 'pending',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_bookings', [
            'id' => $booking->id,
            'internal_notes' => 'Позвонить за час',
            'invoice_status' => 'issued',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get('/admin/booking/calendar?view=day&date='.$slotOccurrence->starts_at->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Календарь бронирований')
            ->assertSee('Ops Client');
    }

    public function test_admin_can_create_booking_manually_from_inbox(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-manual@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        [, $service, $resources, $date] = $this->buildResourceBackedService('auto_assign');
        $slot = \TestoCms\Booking\Models\BookingSlotOccurrence::query()
            ->where('service_id', $service->id)
            ->orderBy('starts_at')
            ->firstOrFail();

        $this->actingAs($user)
            ->get('/admin/booking/bookings?create_service_id='.$service->id.'&create_date='.$date->toDateString())
            ->assertOk()
            ->assertSee('Создать бронь вручную');

        $response = $this->actingAs($user)
            ->post('/admin/booking/bookings', [
                'service_id' => $service->id,
                'date' => $date->toDateString(),
                'slot_id' => $slot->id,
                'customer_name' => 'Manual Client',
                'customer_email' => 'manual@example.test',
                'customer_phone' => '+79990000000',
                'customer_comment' => 'Позвонил по телефону',
                'internal_notes' => 'Создано вручную администратором',
                'invoice_status' => 'issued',
                'payment_status' => 'pending',
            ]);

        $response->assertRedirect();

        $booking = BookingBooking::query()
            ->where('service_id', $service->id)
            ->where('customer_name', 'Manual Client')
            ->first();

        $this->assertNotNull($booking);
        $this->assertSame('admin', $booking->source);
        $this->assertSame('issued', $booking->invoice_status);
        $this->assertSame('pending', $booking->payment_status);
        $this->assertSame('Создано вручную администратором', $booking->internal_notes);
        $this->assertTrue(in_array($booking->resource_id, $resources->pluck('id')->all(), true));
    }

    public function test_admin_can_reschedule_requested_booking(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-reschedule@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        [, $service, $resources, $date] = $this->buildResourceBackedService('choose_resource');
        $slug = $service->translations()->firstOrFail()->slug;
        $firstResource = $resources->values()->get(0);
        $secondResource = $resources->values()->get(1);

        $firstSlots = $this->getJson('/ru/book/api/services/'.$slug.'/slots?date='.$date->toDateString().'&resource_id='.$firstResource->id)
            ->assertOk()
            ->json('data');
        $secondSlots = $this->getJson('/ru/book/api/services/'.$slug.'/slots?date='.$date->toDateString().'&resource_id='.$secondResource->id)
            ->assertOk()
            ->json('data');

        $booking = app(\TestoCms\Booking\Services\BookingReservationService::class)->reserveForPublicSelection(
            $service,
            (int) $firstSlots[0]['id'],
            (int) $firstResource->id,
            ['customer_name' => 'Reschedule Client'],
            'admin'
        );

        $originalSlot = \TestoCms\Booking\Models\BookingSlotOccurrence::query()->findOrFail($booking->slot_occurrence_id);
        $targetSlot = \TestoCms\Booking\Models\BookingSlotOccurrence::query()->findOrFail((int) $secondSlots[0]['id']);

        $this->assertSame(1, $originalSlot->reserved_count);
        $this->assertSame(0, $targetSlot->reserved_count);

        $this->actingAs($user)
            ->post(route('booking.admin.bookings.reschedule', $booking), [
                'date' => $date->toDateString(),
                'resource_id' => $secondResource->id,
                'slot_id' => $targetSlot->id,
            ])
            ->assertRedirect();

        $booking->refresh();
        $originalSlot->refresh();
        $targetSlot->refresh();

        $this->assertSame($targetSlot->id, $booking->slot_occurrence_id);
        $this->assertSame($secondResource->id, $booking->resource_id);
        $this->assertSame(0, $originalSlot->reserved_count);
        $this->assertSame(1, $targetSlot->reserved_count);
    }

    public function test_booking_settings_manage_webhook_endpoints_by_id_and_show_recent_deliveries(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-settings@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        $this->actingAs($user)
            ->get('/admin/booking/settings')
            ->assertOk()
            ->assertSee('Webhook endpoints');

        $this->actingAs($user)
            ->post(route('booking.admin.settings.webhooks.store'), [
                'url' => 'https://hooks.example.test/original',
                'secret' => 'keep-me',
                'events' => ['booking.created'],
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/booking/settings');

        $endpoint = BookingWebhookEndpoint::query()->firstOrFail();

        BookingWebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'booking.created',
            'payload' => ['booking_id' => 10],
            'status' => 'failed',
            'http_status' => 500,
            'response_body' => 'Upstream failed',
            'attempted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/admin/booking/settings')
            ->assertOk()
            ->assertSee('Последние доставки webhook')
            ->assertSee('hooks.example.test/original')
            ->assertSee('booking.created');

        $this->actingAs($user)
            ->put(route('booking.admin.settings.webhooks.update', $endpoint), [
                'url' => 'https://hooks.example.test/rotated',
                'secret' => '',
                'events' => ['booking.created', 'booking.cancelled', 'unknown.event'],
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/booking/settings');

        $endpoint->refresh();

        $this->assertSame('https://hooks.example.test/rotated', $endpoint->url);
        $this->assertSame('keep-me', $endpoint->secret);
        $this->assertSame(['booking.created', 'booking.cancelled'], $endpoint->subscribed_events);
        $this->assertTrue($endpoint->is_active);

        $this->actingAs($user)
            ->post(route('booking.admin.settings.webhooks.rotate-secret', $endpoint))
            ->assertRedirect('/admin/booking/settings');

        $rotatedSecret = $endpoint->fresh()->secret;
        $this->assertNotSame('keep-me', $rotatedSecret);
        $this->assertNotEmpty($rotatedSecret);

        $this->actingAs($user)
            ->delete(route('booking.admin.settings.webhooks.destroy', $endpoint))
            ->assertRedirect('/admin/booking/settings');

        $this->assertDatabaseMissing('booking_webhook_endpoints', [
            'id' => $endpoint->id,
        ]);
    }

    public function test_admin_can_retry_failed_webhook_delivery(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-retry@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        $endpoint = BookingWebhookEndpoint::query()->create([
            'url' => 'https://hooks.example.test/retry',
            'secret' => 'retry-secret',
            'subscribed_events' => ['booking.created'],
            'is_active' => true,
        ]);

        $delivery = BookingWebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'booking.created',
            'payload' => ['booking_id' => 42],
            'status' => 'failed',
            'http_status' => 500,
            'response_body' => 'temporary outage',
            'attempted_at' => now(),
        ]);

        Http::fake([
            'https://hooks.example.test/retry' => Http::response('ok', 200),
        ]);

        $this->actingAs($user)
            ->post(route('booking.admin.settings.webhook-deliveries.retry', $delivery))
            ->assertRedirect('/admin/booking/settings');

        $this->assertDatabaseCount('booking_webhook_deliveries', 2);
        $this->assertDatabaseHas('booking_webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'booking.created',
            'status' => 'delivered',
            'http_status' => 200,
        ]);
    }

    public function test_availability_screen_shows_preview_and_manual_rebuild_flow(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('booking-availability@testocms.local', 'superadmin');
        $this->installAndActivateBooking($user);

        [$location, $service] = $this->buildResourceBackedService('auto_assign');
        $idleResource = BookingResource::query()->create([
            'location_id' => $location->id,
            'name' => 'Ресурс без правил',
            'resource_type' => 'staff',
            'capacity' => 1,
            'is_active' => true,
        ]);
        $service->resources()->syncWithoutDetaching([$idleResource->id]);

        $this->actingAs($user)
            ->get('/admin/booking/availability?service_id='.$service->id)
            ->assertOk()
            ->assertSee('Превью ближайших слотов')
            ->assertSee('Пересобрать текущую услугу')
            ->assertSee('Пересобрать всё');

        $this->actingAs($user)
            ->post('/admin/booking/availability/rebuild', [
                'service_id' => $service->id,
                'preview_resource_id' => $idleResource->id,
            ])
            ->assertRedirect('/admin/booking/availability?service_id='.$service->id.'&preview_resource_id='.$idleResource->id)
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'создано') && str_contains($status, 'обновлено'))
            ->assertSessionHas('availability_rebuild_report', fn (array $report): bool => ($report['scope'] ?? null) === 'service'
                && array_key_exists('created', $report)
                && array_key_exists('updated', $report)
                && array_key_exists('pruned', $report)
                && array_key_exists('projected', $report));

        $this->actingAs($user)
            ->get('/admin/booking/availability?service_id='.$service->id.'&preview_resource_id='.$idleResource->id)
            ->assertOk()
            ->assertSee('Применить фильтры')
            ->assertSee('Ближайшие слоты пока не сформированы для выбранного набора фильтров.');
    }

    private function installAndActivateBooking(User $user): void
    {
        $this->actingAs($user)
            ->post('/admin/modules/install-bundled/testocms--booking', [
                'activate_now' => 1,
            ])
            ->assertRedirect('/admin/modules');

        $module = CmsModule::query()->where('module_key', 'testocms/booking')->first();
        $this->assertNotNull($module);
        $this->assertTrue((bool) $module->enabled);

        app(EnabledModulePublicRoutesLoader::class)->load();
    }

    private function resetModuleRuntimeBootstrapState(): void
    {
        $reflection = new \ReflectionClass(ModuleRuntimeService::class);

        $autoloadPrefixes = $reflection->getProperty('autoloadPrefixes');
        $autoloadPrefixes->setAccessible(true);
        $autoloadPrefixes->setValue(null, []);

        $autoloadRegistered = $reflection->getProperty('autoloadRegistered');
        $autoloadRegistered->setAccessible(true);
        $autoloadRegistered->setValue(null, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readTestingCacheModules(): array
    {
        if (! is_file($this->testingCacheFile)) {
            return [];
        }

        /** @var array{modules?: array<int, array<string, mixed>>} $payload */
        $payload = require $this->testingCacheFile;

        return is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
    }

    private function withFreshApplicationPreservingInMemoryDatabase(callable $callback): void
    {
        $originalApp = $this->app;
        $originalContainer = Container::getInstance();
        $originalFacadeApplication = Facade::getFacadeApplication();
        $originalConnectionResolver = Model::getConnectionResolver();
        $originalConnection = $originalApp->make('db')->connection();
        $pdo = $originalConnection->getPdo();
        $readPdo = $originalConnection->getReadPdo() ?: $pdo;

        $freshApp = require base_path('bootstrap/app.php');
        $freshApp->resolving('config', function ($config): void {
            $config->set('modules.modules_root', $this->testingModulesRoot);
            $config->set('modules.cache_file', $this->testingCacheFile);
        });
        $freshApp->resolving('db', function ($database) use ($pdo, $readPdo): void {
            $connection = $database->connection();
            $connection->setPdo($pdo);
            $connection->setReadPdo($readPdo);
        });
        $freshApp->make(ConsoleKernel::class)->bootstrap();

        $this->app = $freshApp;
        Container::setInstance($freshApp);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($freshApp);
        Model::setConnectionResolver($freshApp->make('db'));

        try {
            $callback();
        } finally {
            $this->app = $originalApp;
            Container::setInstance($originalContainer);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($originalFacadeApplication);
            Model::setConnectionResolver($originalConnectionResolver);
        }
    }

    private function deleteFileIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    private function writeCachePayload(string $path, array $modules): void
    {
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, '<?php return '.var_export([
            'generated_at' => now()->toIso8601String(),
            'modules' => $modules,
        ], true).';'.PHP_EOL);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCacheModules(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        /** @var array{modules?: array<int, array<string, mixed>>} $payload */
        $payload = require $path;

        return is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
    }

    private function orphanedBookingInstallPath(): string
    {
        return $this->testingModulesRoot.'/testocms--booking';
    }

    private function copyBundledBookingModule(string $targetPath): void
    {
        File::deleteDirectory($targetPath);
        File::ensureDirectoryExists(dirname($targetPath));
        File::copyDirectory(base_path('bundled-modules/testocms-booking'), $targetPath);
    }

    private function makeLocalBookingSourceCopy(string $name = 'booking-local-source'): string
    {
        $sourcePath = $this->testingLocalModulesRoot.'/'.$name;

        File::deleteDirectory($sourcePath);
        File::ensureDirectoryExists(dirname($sourcePath));
        File::copyDirectory(base_path('bundled-modules/testocms-booking'), $sourcePath);

        return $sourcePath;
    }

    private function makeModuleZipFromDirectory(string $sourcePath, string $filename = 'booking-module.zip'): string
    {
        $zipPath = storage_path('framework/testing/'.uniqid($filename.'-', true).'.zip');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Failed to create module ZIP archive.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $pathname = (string) $item->getPathname();
            $relative = ltrim(str_replace(rtrim($sourcePath, DIRECTORY_SEPARATOR), '', $pathname), DIRECTORY_SEPARATOR);
            if ($relative === '') {
                continue;
            }

            if ($item->isDir()) {
                $zip->addEmptyDir(str_replace(DIRECTORY_SEPARATOR, '/', $relative));

                continue;
            }

            $zip->addFile($pathname, str_replace(DIRECTORY_SEPARATOR, '/', $relative));
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return array{0: BookingLocation, 1: BookingService, 2: \Illuminate\Support\Collection<int, BookingResource>, 3: CarbonImmutable}
     */
    private function buildResourceBackedService(string $resourceSelectionMode): array
    {
        $location = BookingLocation::query()->create([
            'name' => 'Main office',
            'timezone' => 'Asia/Vladivostok',
            'is_active' => true,
        ]);

        $resources = collect([
            BookingResource::query()->create([
                'location_id' => $location->id,
                'name' => 'Специалист 1',
                'resource_type' => 'staff',
                'capacity' => 1,
                'is_active' => true,
            ]),
            BookingResource::query()->create([
                'location_id' => $location->id,
                'name' => 'Специалист 2',
                'resource_type' => 'staff',
                'capacity' => 1,
                'is_active' => true,
            ]),
        ]);

        $service = BookingService::query()->create([
            'location_id' => $location->id,
            'duration_minutes' => 60,
            'slot_step_minutes' => 30,
            'booking_horizon_days' => 30,
            'lead_time_minutes' => 0,
            'confirmation_mode' => 'manual',
            'resource_selection_mode' => $resourceSelectionMode,
            'price_currency' => 'RUB',
            'is_active' => true,
        ]);
        $service->resources()->sync($resources->pluck('id')->all());

        BookingServiceTranslation::query()->create([
            'service_id' => $service->id,
            'locale' => 'ru',
            'title' => $resourceSelectionMode === 'choose_resource' ? 'Услуга с выбором ресурса' : 'Услуга с автоподбором',
            'slug' => $resourceSelectionMode === 'choose_resource' ? 'resource-choice' : 'auto-assign',
            'short_description' => 'Краткое описание услуги',
            'full_description' => 'Полное описание услуги',
            'meta_title' => 'Услуга',
            'meta_description' => 'Описание',
        ]);

        $targetDay = CarbonImmutable::now('Asia/Vladivostok')->addDay();
        foreach ($resources as $resource) {
            BookingAvailabilityRule::query()->create([
                'service_id' => $service->id,
                'location_id' => $location->id,
                'resource_id' => $resource->id,
                'weekday' => ((int) $targetDay->dayOfWeekIso) % 7,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'slot_step_minutes' => 60,
                'capacity' => 1,
                'is_active' => true,
            ]);
        }

        app(BookingSlotProjectionService::class)->rebuildService($service->fresh(['rules', 'exceptions']) ?? $service);

        return [$location, $service->fresh(['translations', 'resources']) ?? $service, $resources, $targetDay];
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => str_replace(['@', '.'], '_', explode('@', $email)[0]).'_'.random_int(10, 999),
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
