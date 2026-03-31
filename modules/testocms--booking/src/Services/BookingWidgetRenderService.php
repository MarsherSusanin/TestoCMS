<?php

namespace TestoCms\Booking\Services;

class BookingWidgetRenderService
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $definition
     */
    public function renderCatalogBootstrap(array $config, array $context, array $definition): string
    {
        return $this->bootstrapShell('booking_service_catalog', $config, $context, $definition, 'Открыть каталог услуг');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $definition
     */
    public function renderServiceCardBootstrap(array $config, array $context, array $definition): string
    {
        return $this->bootstrapShell('booking_service_card', $config, $context, $definition, 'Открыть услугу');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $definition
     */
    public function renderBookingFormBootstrap(array $config, array $context, array $definition): string
    {
        return $this->bootstrapShell('booking_booking_form', $config, $context, $definition, 'Открыть бронирование');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $definition
     */
    private function bootstrapShell(string $widget, array $config, array $context, array $definition, string $fallbackLabel): string
    {
        $locale = trim((string) ($context['locale'] ?? config('cms.default_locale', 'ru')), '/');
        $endpoint = route('booking.public.widgets.show', [
            'locale' => $locale,
            'widget' => $widget,
        ], false);
        $query = http_build_query(array_filter($config, static fn (mixed $value): bool => $value !== null && $value !== ''));
        if ($query !== '') {
            $endpoint .= '?'.$query;
        }

        $publicPrefix = trim((string) config('cms.booking_url_prefix', 'book'), '/');
        $fallbackHref = '/'.$locale.'/'.$publicPrefix;
        if ($widget !== 'booking_service_catalog' && trim((string) ($config['service_slug'] ?? '')) !== '') {
            $fallbackHref .= '/services/'.trim((string) $config['service_slug'], '/');
        }

        return sprintf(
            '<div class="cms-module-widget" data-booking-widget-endpoint="%s" data-booking-widget="%s"><div class="cms-module-widget-fallback"><a class="button button-secondary" href="%s">%s</a></div></div><script src="%s" defer></script>',
            e($endpoint),
            e($widget),
            e($fallbackHref),
            e($fallbackLabel),
            e(asset('modules/testocms--booking/booking-public.js'))
        );
    }
}

