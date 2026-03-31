<?php

use Illuminate\Support\Facades\Route;
use TestoCms\Booking\Controllers\Admin\AvailabilityController;
use TestoCms\Booking\Controllers\Admin\BookingController;
use TestoCms\Booking\Controllers\Admin\CalendarController;
use TestoCms\Booking\Controllers\Admin\DashboardController;
use TestoCms\Booking\Controllers\Admin\LocationController;
use TestoCms\Booking\Controllers\Admin\ResourceController;
use TestoCms\Booking\Controllers\Admin\ServiceController;
use TestoCms\Booking\Controllers\Admin\SettingsController;

Route::middleware(['web', 'auth'])->prefix('admin/booking')->group(function (): void {
    Route::get('/', DashboardController::class)->name('booking.admin.dashboard');

    Route::get('/services', [ServiceController::class, 'index'])->name('booking.admin.services.index');
    Route::get('/services/create', [ServiceController::class, 'create'])->name('booking.admin.services.create');
    Route::post('/services', [ServiceController::class, 'store'])->name('booking.admin.services.store');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('booking.admin.services.edit');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('booking.admin.services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('booking.admin.services.destroy');

    Route::get('/resources', [ResourceController::class, 'index'])->name('booking.admin.resources.index');
    Route::get('/resources/create', [ResourceController::class, 'create'])->name('booking.admin.resources.create');
    Route::post('/resources', [ResourceController::class, 'store'])->name('booking.admin.resources.store');
    Route::get('/resources/{resource}/edit', [ResourceController::class, 'edit'])->name('booking.admin.resources.edit');
    Route::put('/resources/{resource}', [ResourceController::class, 'update'])->name('booking.admin.resources.update');
    Route::delete('/resources/{resource}', [ResourceController::class, 'destroy'])->name('booking.admin.resources.destroy');

    Route::get('/locations', [LocationController::class, 'index'])->name('booking.admin.locations.index');
    Route::get('/locations/create', [LocationController::class, 'create'])->name('booking.admin.locations.create');
    Route::post('/locations', [LocationController::class, 'store'])->name('booking.admin.locations.store');
    Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('booking.admin.locations.edit');
    Route::put('/locations/{location}', [LocationController::class, 'update'])->name('booking.admin.locations.update');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('booking.admin.locations.destroy');

    Route::get('/availability', [AvailabilityController::class, 'index'])->name('booking.admin.availability.index');
    Route::post('/availability/rules', [AvailabilityController::class, 'storeRule'])->name('booking.admin.availability.rules.store');
    Route::put('/availability/rules/{rule}', [AvailabilityController::class, 'updateRule'])->name('booking.admin.availability.rules.update');
    Route::delete('/availability/rules/{rule}', [AvailabilityController::class, 'destroyRule'])->name('booking.admin.availability.rules.destroy');
    Route::post('/availability/exceptions', [AvailabilityController::class, 'storeException'])->name('booking.admin.availability.exceptions.store');
    Route::delete('/availability/exceptions/{exception}', [AvailabilityController::class, 'destroyException'])->name('booking.admin.availability.exceptions.destroy');
    Route::post('/availability/rebuild', [AvailabilityController::class, 'rebuild'])->name('booking.admin.availability.rebuild');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('booking.admin.calendar');
    Route::get('/bookings', [BookingController::class, 'index'])->name('booking.admin.bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('booking.admin.bookings.store');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('booking.admin.bookings.update');
    Route::post('/bookings/{booking}/reschedule', [BookingController::class, 'reschedule'])->name('booking.admin.bookings.reschedule');
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('booking.admin.bookings.confirm');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('booking.admin.bookings.cancel');
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete'])->name('booking.admin.bookings.complete');
    Route::post('/bookings/{booking}/no-show', [BookingController::class, 'noShow'])->name('booking.admin.bookings.no-show');
    Route::post('/bookings/{booking}/invoice', [BookingController::class, 'updateInvoiceStatus'])->name('booking.admin.bookings.invoice');
    Route::post('/bookings/{booking}/payment-status', [BookingController::class, 'updatePaymentStatus'])->name('booking.admin.bookings.payment-status');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('booking.admin.settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('booking.admin.settings.update');
    Route::post('/settings/webhooks', [SettingsController::class, 'storeWebhook'])->name('booking.admin.settings.webhooks.store');
    Route::put('/settings/webhooks/{endpoint}', [SettingsController::class, 'updateWebhook'])->name('booking.admin.settings.webhooks.update');
    Route::post('/settings/webhooks/{endpoint}/rotate-secret', [SettingsController::class, 'rotateWebhookSecret'])->name('booking.admin.settings.webhooks.rotate-secret');
    Route::delete('/settings/webhooks/{endpoint}', [SettingsController::class, 'destroyWebhook'])->name('booking.admin.settings.webhooks.destroy');
    Route::post('/settings/webhook-deliveries/{delivery}/retry', [SettingsController::class, 'retryWebhookDelivery'])->name('booking.admin.settings.webhook-deliveries.retry');
});
