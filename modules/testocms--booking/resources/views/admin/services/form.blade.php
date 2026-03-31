@extends('admin.layout')
@section('title', $isEdit ? 'Редактирование услуги' : 'Новая услуга')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => $isEdit ? 'Редактирование услуги' : 'Новая услуга',
        'description' => 'Услуга определяет длительность, подтверждение, режим выбора ресурса и локализованный публичный контент.',
        'actions' => '<a class="btn" href="'.route('booking.admin.services.index').'">Назад к услугам</a>',
    ])

    <form method="POST" action="{{ $isEdit ? route('booking.admin.services.update', $service) : route('booking.admin.services.store') }}" class="booking-stack">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <section class="panel booking-stack">
            <div class="booking-stat-grid">
                <div class="booking-stat">
                    <span class="muted">Статус</span>
                    <strong>{{ old('is_active', $service->is_active) ? 'Активна' : 'Черновик / выключена' }}</strong>
                </div>
                <div class="booking-stat">
                    <span class="muted">Подтверждение</span>
                    <strong>{{ old('confirmation_mode', $service->confirmation_mode) === 'instant' ? 'Мгновенно' : 'Вручную' }}</strong>
                </div>
                <div class="booking-stat">
                    <span class="muted">Выбор ресурса</span>
                    <strong>{{ old('resource_selection_mode', $service->resource_selection_mode) === 'choose_resource' ? 'Клиент выбирает' : 'Автоподбор' }}</strong>
                </div>
                <div class="booking-stat">
                    <span class="muted">Ресурсов</span>
                    <strong>{{ count(old('resource_ids', $selectedResourceIds)) }}</strong>
                </div>
            </div>
        </section>

        <div class="booking-page-grid">
            <div class="booking-stack">
                <section class="panel booking-stack">
                    <h2 class="panel-section-title">Операционная модель</h2>
                    <div class="booking-helper">Эти параметры определяют длительность услуги, горизонты бронирования и то, выбирает ли клиент конкретный ресурс или система назначает его автоматически.</div>
                    <div class="booking-form-grid">
                        <label>
                            <span>Локация</span>
                            <select name="location_id">
                                <option value="">—</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected((string) old('location_id', $service->location_id) === (string) $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Изображение карточки</span>
                            <select name="featured_asset_id">
                                <option value="">—</option>
                                @foreach($assets as $asset)
                                    <option value="{{ $asset->id }}" @selected((string) old('featured_asset_id', $service->featured_asset_id) === (string) $asset->id)>#{{ $asset->id }} {{ $asset->title ?: $asset->alt ?: basename($asset->storage_path) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Длительность (мин)</span>
                            <input type="number" min="5" name="duration_minutes" value="{{ old('duration_minutes', $service->duration_minutes) }}" required>
                        </label>
                        <label>
                            <span>Шаг слота (мин)</span>
                            <input type="number" min="5" name="slot_step_minutes" value="{{ old('slot_step_minutes', $service->slot_step_minutes) }}" required>
                        </label>
                        <label>
                            <span>Буфер до услуги</span>
                            <input type="number" min="0" name="buffer_before_minutes" value="{{ old('buffer_before_minutes', $service->buffer_before_minutes) }}">
                        </label>
                        <label>
                            <span>Буфер после услуги</span>
                            <input type="number" min="0" name="buffer_after_minutes" value="{{ old('buffer_after_minutes', $service->buffer_after_minutes) }}">
                        </label>
                        <label>
                            <span>Горизонт бронирования (дней)</span>
                            <input type="number" min="1" name="booking_horizon_days" value="{{ old('booking_horizon_days', $service->booking_horizon_days) }}" required>
                        </label>
                        <label>
                            <span>Lead time (мин)</span>
                            <input type="number" min="0" name="lead_time_minutes" value="{{ old('lead_time_minutes', $service->lead_time_minutes) }}" required>
                        </label>
                        <label>
                            <span>Режим подтверждения</span>
                            <select name="confirmation_mode">
                                <option value="manual" @selected(old('confirmation_mode', $service->confirmation_mode) === 'manual')>Заявка с ручным подтверждением</option>
                                <option value="instant" @selected(old('confirmation_mode', $service->confirmation_mode) === 'instant')>Мгновенное бронирование</option>
                            </select>
                        </label>
                        <label>
                            <span>Режим выбора ресурса</span>
                            <select name="resource_selection_mode">
                                <option value="auto_assign" @selected(old('resource_selection_mode', $service->resource_selection_mode) === 'auto_assign')>Автоподбор ресурса системой</option>
                                <option value="choose_resource" @selected(old('resource_selection_mode', $service->resource_selection_mode) === 'choose_resource')>Клиент выбирает ресурс</option>
                            </select>
                        </label>
                        <fieldset class="full">
                            <legend>Доступные ресурсы</legend>
                            <div class="booking-helper">Если включён режим выбора ресурса, привяжите хотя бы один активный ресурс. Для автоподбора система сама найдёт подходящий слот среди привязанных ресурсов.</div>
                            <div class="booking-inline">
                                @foreach($resources as $resource)
                                    <label>
                                        <input type="checkbox" name="resource_ids[]" value="{{ $resource->id }}" @checked(in_array($resource->id, old('resource_ids', $selectedResourceIds), true))>
                                        {{ $resource->name }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </section>

                <section class="panel booking-stack">
                    <h2 class="panel-section-title">Карточка услуги</h2>
                    <div class="booking-helper">Публичные поля карточки используются в каталоге, widgets и на booking page модуля.</div>
                    <div class="booking-form-grid">
                        <label>
                            <span>Валюта</span>
                            <input type="text" name="price_currency" maxlength="3" value="{{ old('price_currency', $service->price_currency) }}" required>
                        </label>
                        <label>
                            <span>Цена</span>
                            <input type="number" step="0.01" min="0" name="price_amount" value="{{ old('price_amount', $service->price_amount) }}">
                        </label>
                        <label>
                            <span>Текст цены</span>
                            <input type="text" name="price_label" value="{{ old('price_label', $service->price_label) }}">
                        </label>
                        <label>
                            <span>CTA label</span>
                            <input type="text" name="cta_label" value="{{ old('cta_label', $service->cta_label) }}">
                        </label>
                        <label class="full">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active))> Активна и доступна для публичного бронирования
                        </label>
                    </div>
                </section>
            </div>

            <section class="panel booking-stack">
                <h2 class="panel-section-title">Локализации услуги</h2>
                <div class="booking-helper">Заполните хотя бы одну локаль. Slug используется в маршруте публичной booking-страницы услуги.</div>
                @foreach($supportedLocales as $locale)
                    @php($translation = $translations[$locale] ?? null)
                    <div class="booking-section">
                        <h3 class="booking-section-title">{{ strtoupper($locale) }}</h3>
                        <div class="booking-form-grid">
                            <label>
                                <span>Заголовок</span>
                                <input type="text" name="translations[{{ $locale }}][title]" value="{{ old("translations.$locale.title", $translation?->title) }}">
                            </label>
                            <label>
                                <span>Slug</span>
                                <input type="text" name="translations[{{ $locale }}][slug]" value="{{ old("translations.$locale.slug", $translation?->slug) }}">
                            </label>
                            <label class="full">
                                <span>Краткое описание</span>
                                <textarea name="translations[{{ $locale }}][short_description]" rows="3">{{ old("translations.$locale.short_description", $translation?->short_description) }}</textarea>
                            </label>
                            <label class="full">
                                <span>Полное описание</span>
                                <textarea name="translations[{{ $locale }}][full_description]" rows="6">{{ old("translations.$locale.full_description", $translation?->full_description) }}</textarea>
                            </label>
                            <label>
                                <span>Meta title</span>
                                <input type="text" name="translations[{{ $locale }}][meta_title]" value="{{ old("translations.$locale.meta_title", $translation?->meta_title) }}">
                            </label>
                            <label>
                                <span>Meta description</span>
                                <textarea name="translations[{{ $locale }}][meta_description]" rows="3">{{ old("translations.$locale.meta_description", $translation?->meta_description) }}</textarea>
                            </label>
                        </div>
                    </div>
                @endforeach
            </section>
        </div>

        <div class="inline-actions">
            <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Сохранить услугу' : 'Создать услугу' }}</button>
        </div>
    </form>
@endsection
@push('head')
    @include('booking-module::admin.partials.styles')
@endpush
