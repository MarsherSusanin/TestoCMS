@php($translation = $service?->getRelation('current_translation') ?? ($service?->translations?->firstWhere('locale', $locale) ?? $service?->translations?->first()))
@if(!$service || !$translation)
    <div class="empty-state">{{ $locale === 'ru' ? 'Услуга недоступна.' : 'Service unavailable.' }}</div>
@else
    @php($requiresResource = $service->usesResourceChoiceMode())
    <div
        class="booking-widget-shell"
        data-booking-form
        data-booking-resource-mode="{{ $service->resource_selection_mode }}"
        data-booking-requires-resource="{{ $requiresResource ? '1' : '0' }}"
        data-slots-endpoint="{{ route('booking.public.api.slots', ['locale' => $locale, 'slug' => $translation->slug]) }}"
        data-book-endpoint="{{ route('booking.public.api.book', ['locale' => $locale, 'slug' => $translation->slug]) }}"
    >
        @if(($showHeading ?? true) === true)
            <div>
                <h3 style="margin:0 0 6px;">{{ $translation->title }}</h3>
                @if($translation->short_description)
                    <p class="muted">{{ $translation->short_description }}</p>
                @endif
            </div>
        @endif
        <div class="booking-form">
            <div class="grid-2">
                <label><span>{{ $locale === 'ru' ? 'Дата' : 'Date' }}</span><input type="date" data-booking-date value="{{ now($service->location?->timezone ?: config('app.timezone'))->toDateString() }}"></label>
                <label @if(!$requiresResource) hidden @endif><span>{{ $locale === 'ru' ? 'Ресурс' : 'Resource' }}</span><select data-booking-resource><option value="">{{ $requiresResource ? ($locale === 'ru' ? 'Сначала выберите ресурс' : 'Choose resource') : ($locale === 'ru' ? 'Автоподбор' : 'Auto assign') }}</option>@foreach($service->resources->where('is_active', true) as $resource)<option value="{{ $resource->id }}">{{ $resource->name }}</option>@endforeach</select></label>
            </div>
            @if($requiresResource)
                <div class="muted">{{ $locale === 'ru' ? 'Для этой услуги клиент выбирает конкретный ресурс перед показом слотов.' : 'This service requires selecting a specific resource before loading slots.' }}</div>
            @endif
            <div>
                <strong>{{ $locale === 'ru' ? 'Доступные слоты' : 'Available slots' }}</strong>
                <div class="booking-slot-list" data-booking-slots></div>
            </div>
            <div class="grid-2">
                <label><span>{{ $locale === 'ru' ? 'Имя' : 'Name' }}</span><input type="text" data-booking-customer-name required></label>
                <label><span>Email</span><input type="email" data-booking-customer-email></label>
                <label><span>{{ $locale === 'ru' ? 'Телефон' : 'Phone' }}</span><input type="text" data-booking-customer-phone></label>
                <label><span>{{ $locale === 'ru' ? 'Комментарий' : 'Comment' }}</span><input type="text" data-booking-customer-comment></label>
            </div>
            <div class="hero-actions">
                <button class="button button-primary" type="button" data-booking-submit>{{ $locale === 'ru' ? 'Отправить бронирование' : 'Submit booking' }}</button>
            </div>
            <div class="booking-status" data-booking-status>{{ $locale === 'ru' ? 'Выберите дату и слот.' : 'Choose date and slot.' }}</div>
        </div>
    </div>
@endif
