<?php

namespace TestoCms\Booking\Services;

class BookingWidgetCatalogService
{
    public function __construct(
        private readonly BookingCatalogService $catalog,
        private readonly BookingWidgetRenderService $renderer,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        $serviceOptions = fn (): array => $this->catalog->activeServices((string) config('cms.default_locale', 'ru'))
            ->map(function ($service): array {
                $translation = $service->getRelation('current_translation');

                return [
                    'value' => (string) ($translation?->slug ?? ''),
                    'label' => (string) ($translation?->title ?? 'Service #'.$service->id),
                ];
            })->filter(fn (array $option): bool => $option['value'] !== '')->values()->all();

        return [
            'booking_service_catalog' => [
                'module_label' => 'Booking',
                'label' => 'Каталог услуг',
                'description' => 'Список доступных услуг бронирования.',
                'config_fields' => [
                    ['name' => 'show_prices', 'label' => 'Показывать цены', 'type' => 'checkbox', 'default' => true],
                    ['name' => 'cta_label', 'label' => 'Текст CTA', 'type' => 'text', 'default' => 'Забронировать'],
                ],
                'renderer' => [$this->renderer, 'renderCatalogBootstrap'],
            ],
            'booking_service_card' => [
                'module_label' => 'Booking',
                'label' => 'Карточка услуги',
                'description' => 'Одна конкретная услуга по slug.',
                'config_fields' => [
                    ['name' => 'service_slug', 'label' => 'Услуга', 'type' => 'select', 'options' => $serviceOptions],
                    ['name' => 'show_description', 'label' => 'Показывать описание', 'type' => 'checkbox', 'default' => true],
                ],
                'renderer' => [$this->renderer, 'renderServiceCardBootstrap'],
            ],
            'booking_booking_form' => [
                'module_label' => 'Booking',
                'label' => 'Форма бронирования',
                'description' => 'Слот-пикер и форма записи на услугу.',
                'config_fields' => [
                    ['name' => 'service_slug', 'label' => 'Услуга', 'type' => 'select', 'options' => $serviceOptions],
                    ['name' => 'show_heading', 'label' => 'Показывать заголовок', 'type' => 'checkbox', 'default' => true],
                ],
                'renderer' => [$this->renderer, 'renderBookingFormBootstrap'],
            ],
        ];
    }
}
