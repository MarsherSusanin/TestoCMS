<?php

use Illuminate\Support\Facades\Route;
use TestoCms\Booking\Controllers\Public\BookingApiController;
use TestoCms\Booking\Controllers\Public\BookingPageController;
use TestoCms\Booking\Controllers\Public\WidgetController;

$bookingPrefix = trim((string) config('cms.booking_url_prefix', 'book'), '/');

Route::prefix('{locale}')
    ->whereIn('locale', config('cms.supported_locales', ['ru', 'en']))
    ->group(function () use ($bookingPrefix): void {
        Route::get('/'.$bookingPrefix, [BookingPageController::class, 'index'])->name('booking.public.index');
        Route::get('/'.$bookingPrefix.'/services/{slug}', [BookingPageController::class, 'showService'])->name('booking.public.service');
        Route::get('/'.$bookingPrefix.'/widgets/{widget}', [WidgetController::class, 'show'])->name('booking.public.widgets.show');
        Route::get('/'.$bookingPrefix.'/api/services', [BookingApiController::class, 'services'])->name('booking.public.api.services');
        Route::get('/'.$bookingPrefix.'/api/services/{slug}/slots', [BookingApiController::class, 'slots'])->name('booking.public.api.slots');
        Route::post('/'.$bookingPrefix.'/api/services/{slug}/book', [BookingApiController::class, 'book'])->name('booking.public.api.book');
    });
