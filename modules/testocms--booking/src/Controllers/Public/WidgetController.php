<?php

namespace TestoCms\Booking\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use TestoCms\Booking\Services\BookingCatalogService;
use TestoCms\Booking\Services\BookingSettingsService;

class WidgetController extends Controller
{
    public function __construct(
        private readonly BookingCatalogService $catalog,
        private readonly BookingSettingsService $settings,
    ) {}

    public function show(Request $request, string $locale, string $widget): Response
    {
        $config = [
            'service_slug' => trim((string) $request->query('service_slug', '')),
            'show_prices' => filter_var($request->query('show_prices', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'show_description' => filter_var($request->query('show_description', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'show_heading' => filter_var($request->query('show_heading', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'cta_label' => (string) $request->query('cta_label', ($locale === 'ru' ? 'Забронировать' : 'Book now')),
        ];

        return match ($widget) {
            'booking_service_catalog' => response()->view('booking-module::public.widgets.catalog', [
                'locale' => $locale,
                'services' => $this->catalog->activeServices($locale),
                'config' => $config,
                'bookingSettings' => $this->settings->resolved(),
            ]),
            'booking_service_card' => response()->view('booking-module::public.widgets.service-card', [
                'locale' => $locale,
                'service' => $config['service_slug'] !== '' ? $this->catalog->findActiveServiceBySlug($locale, $config['service_slug']) : null,
                'config' => $config,
                'bookingSettings' => $this->settings->resolved(),
            ]),
            'booking_booking_form' => response()->view('booking-module::public.widgets.booking-form', [
                'locale' => $locale,
                'service' => $config['service_slug'] !== '' ? $this->catalog->findActiveServiceBySlug($locale, $config['service_slug']) : null,
                'config' => $config,
                'bookingSettings' => $this->settings->resolved(),
            ]),
            default => abort(404),
        };
    }
}
