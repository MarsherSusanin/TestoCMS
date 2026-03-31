@extends('admin.layout')
@section('title', 'Доступность')

@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Доступность и слоты',
        'description' => 'Weekly rules, blackout dates, ручная пересборка и preview материализованных слотов.',
    ])

    <section class="panel booking-stack">
        <form method="GET" class="booking-inline">
            <label>
                <span>Услуга</span>
                <select name="service_id" onchange="this.form.submit()">
                    <option value="">—</option>
                    @foreach($services as $service)
                        @php($translation = $service->translations->firstWhere('locale', app()->getLocale()) ?? $service->translations->first())
                        <option value="{{ $service->id }}" @selected((string) request('service_id', $selectedService?->id) === (string) $service->id)>
                            {{ $translation?->title ?? ('Service #'.$service->id) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button class="btn" type="submit">Показать</button>
        </form>
        @if($selectedService)
            <div class="booking-form-actions">
                <form method="POST" action="{{ route('booking.admin.availability.rebuild') }}">
                    @csrf
                    <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
                    <input type="hidden" name="preview_location_id" value="{{ $previewFilters['location_id'] }}">
                    <input type="hidden" name="preview_resource_id" value="{{ $previewFilters['resource_id'] }}">
                    <button class="btn" type="submit">Пересобрать текущую услугу</button>
                </form>
                <form method="POST" action="{{ route('booking.admin.availability.rebuild') }}" data-confirm="Пересобрать слоты для всех активных услуг?">
                    @csrf
                    <input type="hidden" name="preview_location_id" value="{{ $previewFilters['location_id'] }}">
                    <input type="hidden" name="preview_resource_id" value="{{ $previewFilters['resource_id'] }}">
                    <button class="btn" type="submit">Пересобрать всё</button>
                </form>
            </div>
        @endif
    </section>

    @if(!$selectedService)
        <div class="panel">
            <div class="empty-state">Сначала выберите услугу. Если услуг ещё нет, создайте её в разделе «Услуги».</div>
        </div>
    @else
        @include('admin.partials.management-stats', [
            'items' => $availabilityStats,
            'class' => 'booking-management-stats',
        ])

        @if(!empty($rebuildReportStats))
            @include('admin.partials.management-stats', [
                'items' => $rebuildReportStats,
                'class' => 'booking-management-stats',
            ])
        @endif

        <div class="booking-grid booking-grid--availability">
            <section class="panel booking-stack">
                <div class="booking-section">
                    <h2 class="panel-section-title">Новое weekly rule</h2>
                    <p class="booking-helper">Правила формируют базовую сетку слотов. Если правило привязано к ресурсу, проекция создаёт отдельные occurrences для этого ресурса.</p>
                    <form method="POST" action="{{ route('booking.admin.availability.rules.store') }}" class="booking-stack">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
                        <div class="booking-form-grid">
                            <label>
                                <span>День недели</span>
                                <select name="weekday">
                                    @foreach($weekdayLabels as $day => $label)
                                        <option value="{{ $day }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Локация</span>
                                <select name="location_id">
                                    <option value="">—</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Ресурс</span>
                                <select name="resource_id">
                                    <option value="">—</option>
                                    @foreach($resources as $resource)
                                        <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Capacity</span>
                                <input type="number" min="1" name="capacity" value="1">
                            </label>
                            <label>
                                <span>Начало</span>
                                <input type="time" name="start_time" required>
                            </label>
                            <label>
                                <span>Окончание</span>
                                <input type="time" name="end_time" required>
                            </label>
                            <label>
                                <span>Step (мин)</span>
                                <input type="number" min="5" name="slot_step_minutes" value="{{ $selectedService->slot_step_minutes }}">
                            </label>
                            <label class="full booking-checkbox booking-checkbox--inline">
                                <input type="checkbox" name="is_active" value="1" checked>
                                <span>Правило активно</span>
                            </label>
                        </div>
                        <div class="booking-form-actions">
                            <button class="btn btn-primary" type="submit">Добавить правило</button>
                        </div>
                    </form>
                </div>

                <div class="booking-section">
                    <h2 class="panel-section-title">Новое исключение</h2>
                    <p class="booking-helper">Исключения перекрывают weekly rules и позволяют закрывать весь день или отдельное временное окно.</p>
                    <form method="POST" action="{{ route('booking.admin.availability.exceptions.store') }}" class="booking-stack">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
                        <div class="booking-form-grid">
                            <label>
                                <span>Дата</span>
                                <input type="date" name="date" required>
                            </label>
                            <label>
                                <span>Локация</span>
                                <select name="location_id">
                                    <option value="">—</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Ресурс</span>
                                <select name="resource_id">
                                    <option value="">—</option>
                                    @foreach($resources as $resource)
                                        <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Начало</span>
                                <input type="time" name="start_time">
                            </label>
                            <label>
                                <span>Окончание</span>
                                <input type="time" name="end_time">
                            </label>
                            <label>
                                <span>Комментарий</span>
                                <input type="text" name="note" placeholder="Например: техническое окно">
                            </label>
                            <label class="full booking-checkbox booking-checkbox--inline">
                                <input type="checkbox" name="is_closed" value="1" checked>
                                <span>Закрыть слот или весь день</span>
                            </label>
                        </div>
                        <div class="booking-form-actions">
                            <button class="btn btn-primary" type="submit">Добавить исключение</button>
                        </div>
                    </form>
                </div>
            </section>

            <div class="booking-stack">
                <section class="panel booking-stack">
                    <div class="booking-inline booking-inline--between">
                        <div>
                            <h2 class="panel-section-title">Превью ближайших слотов</h2>
                            <p class="booking-helper">Окно ближайших projected occurrences после применения правил, исключений, buffers и lead time.</p>
                        </div>
                    </div>
                    <form method="GET" class="booking-inline">
                        <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
                        <label>
                            <span>Локация</span>
                            <select name="preview_location_id">
                                <option value="">Все локации</option>
                                @foreach($previewLocations as $location)
                                    <option value="{{ $location->id }}" @selected((string) $previewFilters['location_id'] === (string) $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Ресурс</span>
                            <select name="preview_resource_id">
                                <option value="">Все ресурсы</option>
                                @foreach($previewResources as $resource)
                                    <option value="{{ $resource->id }}" @selected((string) $previewFilters['resource_id'] === (string) $resource->id)>{{ $resource->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="booking-form-actions">
                            <button class="btn" type="submit">Применить фильтры</button>
                            @if($previewFilters['location_id'] || $previewFilters['resource_id'])
                                <a class="btn" href="{{ route('booking.admin.availability.index', ['service_id' => $selectedService->id]) }}">Сбросить</a>
                            @endif
                        </div>
                    </form>
                    @if(($previewSlots->count() ?? 0) === 0)
                        <p class="muted">Ближайшие слоты пока не сформированы для выбранного набора фильтров.</p>
                    @else
                        <div class="booking-stack">
                            @foreach($previewSlots as $slot)
                                <div class="booking-widget-note">
                                    <div class="booking-inline booking-inline--between">
                                        <strong>{{ $slot->starts_at?->format('d.m.Y H:i') }} - {{ $slot->ends_at?->format('H:i') }}</strong>
                                        <span class="status-pill">{{ $slot->status }}</span>
                                    </div>
                                    <div class="booking-service-meta">
                                        <span>capacity {{ $slot->capacity_total }}</span>
                                        <span>reserved {{ $slot->reserved_count }}</span>
                                        <span>confirmed {{ $slot->confirmed_count }}</span>
                                        @if($slot->location)
                                            <span>Локация: {{ $slot->location->name }}</span>
                                        @endif
                                        @if($slot->resource)
                                            <span>Ресурс: {{ $slot->resource->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="panel booking-stack">
                    <h2 class="panel-section-title">Правила</h2>
                    @if($selectedService->rules->isEmpty())
                        <p class="muted">Для услуги ещё нет правил.</p>
                    @else
                        @foreach($selectedService->rules as $rule)
                            <div class="booking-widget-note booking-stack">
                                <form method="POST" action="{{ route('booking.admin.availability.rules.update', $rule) }}" class="booking-stack">
                                    @csrf
                                    @method('PUT')
                                    <div class="booking-form-grid">
                                        <label>
                                            <span>День недели</span>
                                            <select name="weekday">
                                                @foreach($weekdayLabels as $day => $label)
                                                    <option value="{{ $day }}" @selected((int) $rule->weekday === $day)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span>Capacity</span>
                                            <input type="number" min="1" name="capacity" value="{{ $rule->capacity }}">
                                        </label>
                                        <label>
                                            <span>Начало</span>
                                            <input type="time" name="start_time" value="{{ substr((string) $rule->start_time, 0, 5) }}">
                                        </label>
                                        <label>
                                            <span>Окончание</span>
                                            <input type="time" name="end_time" value="{{ substr((string) $rule->end_time, 0, 5) }}">
                                        </label>
                                        <label>
                                            <span>Локация</span>
                                            <select name="location_id">
                                                <option value="">—</option>
                                                @foreach($locations as $location)
                                                    <option value="{{ $location->id }}" @selected((string) $rule->location_id === (string) $location->id)>{{ $location->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span>Ресурс</span>
                                            <select name="resource_id">
                                                <option value="">—</option>
                                                @foreach($resources as $resource)
                                                    <option value="{{ $resource->id }}" @selected((string) $rule->resource_id === (string) $resource->id)>{{ $resource->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span>Step (мин)</span>
                                            <input type="number" min="5" name="slot_step_minutes" value="{{ $rule->slot_step_minutes }}">
                                        </label>
                                        <label class="full booking-checkbox booking-checkbox--inline">
                                            <input type="checkbox" name="is_active" value="1" @checked($rule->is_active)>
                                            <span>Правило активно</span>
                                        </label>
                                    </div>
                                    <div class="booking-form-actions">
                                        <button class="btn" type="submit">Сохранить</button>
                                    </div>
                                </form>
                                <div class="booking-form-actions">
                                    <form method="POST" action="{{ route('booking.admin.availability.rules.destroy', $rule) }}" data-confirm="Удалить правило доступности?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </section>

                <section class="panel booking-stack">
                    <h2 class="panel-section-title">Исключения</h2>
                    @if($selectedService->exceptions->isEmpty())
                        <p class="muted">Для услуги ещё нет исключений.</p>
                    @else
                        @foreach($selectedService->exceptions as $exception)
                            <div class="booking-widget-note booking-stack">
                                <div class="booking-inline booking-inline--between">
                                    <strong>{{ $exception->date?->format('d.m.Y') }}</strong>
                                    @if($exception->is_closed)
                                        <span class="status-pill">Закрыто</span>
                                    @endif
                                </div>
                                <div class="booking-service-meta">
                                    <span>{{ $exception->start_time && $exception->end_time ? substr((string) $exception->start_time, 0, 5).' - '.substr((string) $exception->end_time, 0, 5) : 'Закрыт весь день' }}</span>
                                    @if($exception->location)
                                        <span>Локация: {{ $exception->location->name }}</span>
                                    @endif
                                    @if($exception->resource)
                                        <span>Ресурс: {{ $exception->resource->name }}</span>
                                    @endif
                                </div>
                                <div class="muted">{{ $exception->note ?: 'Без комментария' }}</div>
                                <div class="booking-form-actions">
                                    <form method="POST" action="{{ route('booking.admin.availability.exceptions.destroy', $exception) }}" data-confirm="Удалить исключение?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </section>
            </div>
        </div>
    @endif
@endsection

@push('head')
    @include('booking-module::admin.partials.styles')
@endpush
