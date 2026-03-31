@include('booking-module::public.partials.styles')
@if($service)
    @include('booking-module::public.partials.service-card', ['showDescription' => (bool) ($config['show_description'] ?? true), 'showPrices' => true, 'ctaLabel' => $service->cta_label ?: ($locale === 'ru' ? 'Забронировать' : 'Book now')])
@else
    <div class="empty-state">{{ $locale === 'ru' ? 'Выберите услугу в настройках виджета.' : 'Choose a service in widget settings.' }}</div>
@endif
