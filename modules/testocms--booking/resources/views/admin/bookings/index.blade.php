@extends('admin.layout')
@section('title', 'Брони')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Inbox бронирований',
        'description' => 'Операционный контур: входящие заявки, подтверждение, отмена, финальные статусы и внутренние заметки.',
    ])

    <div class="booking-page-grid">
        <section class="panel booking-stack">
            <form method="GET" class="booking-form-grid">
                <label>
                    <span>Статус</span>
                    <select name="status">
                        <option value="">Все</option>
                        @foreach(['requested','confirmed','cancelled','completed','no_show'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Услуга</span>
                    <select name="service_id">
                        <option value="">Все</option>
                        @foreach($services as $service)
                            @php($translation = $service->translations->firstWhere('locale', app()->getLocale()) ?? $service->translations->first())
                            <option value="{{ $service->id }}" @selected((string) request('service_id') === (string) $service->id)>{{ $translation?->title ?? ('Service #'.$service->id) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Локация</span>
                    <select name="location_id">
                        <option value="">Все</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Ресурс</span>
                    <select name="resource_id">
                        <option value="">Все</option>
                        @foreach($resources as $resource)
                            <option value="{{ $resource->id }}" @selected((string) request('resource_id') === (string) $resource->id)>{{ $resource->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>С даты</span>
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </label>
                <label>
                    <span>По дату</span>
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </label>
                <div class="booking-form-actions full">
                    <button class="btn" type="submit">Применить фильтры</button>
                    <a class="btn" href="{{ route('booking.admin.bookings.index') }}">Сбросить</a>
                </div>
            </form>

            @if($bookings->count() === 0)
                <div class="empty-state">Бронирований пока нет.</div>
            @else
                <div class="booking-list">
                    @foreach($bookings as $booking)
                        @php($translation = $booking->service?->translations->firstWhere('locale', app()->getLocale()) ?? $booking->service?->translations->first())
                        @php($isSelected = $selectedBooking && $selectedBooking->id === $booking->id)
                        <a
                            class="booking-list-card {{ $isSelected ? 'is-active' : '' }} {{ $booking->status === 'requested' ? 'is-requested' : '' }}"
                            href="{{ route('booking.admin.bookings.index', array_merge(request()->query(), ['booking_id' => $booking->id])) }}"
                        >
                            <div class="booking-inline">
                                <strong>#{{ $booking->id }} · {{ $translation?->title ?? '—' }}</strong>
                                <span class="status-pill">{{ $booking->status }}</span>
                            </div>
                            <div class="muted">{{ $booking->starts_at?->format('d.m.Y H:i') }} - {{ $booking->ends_at?->format('H:i') }}</div>
                            <div>{{ $booking->customer_name }}</div>
                            <div class="booking-service-meta">
                                <span>{{ $booking->location?->name ?? '—' }}</span>
                                @if($booking->resource)
                                    <span>{{ $booking->resource->name }}</span>
                                @endif
                                <span>Invoice: {{ $booking->invoice_status }}</span>
                                <span>Payment: {{ $booking->payment_status }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                {{ $bookings->links() }}
            @endif
        </section>

        <div class="booking-stack">
            <section class="panel booking-detail-card">
                @php($createTranslation = $createService?->translations->firstWhere('locale', app()->getLocale()) ?? $createService?->translations->first())
                <div class="booking-section">
                    <h2 class="panel-section-title">Создать бронь вручную</h2>
                    <p class="booking-helper">Операционный сценарий для заявки по телефону, из мессенджера или при ручной работе администратора. Используется тот же reservation workflow, что и у публичного бронирования.</p>
                    <form method="GET" action="{{ route('booking.admin.bookings.index') }}" class="booking-form-grid">
                        <label>
                            <span>Услуга</span>
                            <select name="create_service_id">
                                @foreach($services as $service)
                                    @php($translation = $service->translations->firstWhere('locale', app()->getLocale()) ?? $service->translations->first())
                                    <option value="{{ $service->id }}" @selected((string) optional($createService)->id === (string) $service->id)>{{ $translation?->title ?? ('Service #'.$service->id) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Дата</span>
                            <input type="date" name="create_date" value="{{ $createDate }}">
                        </label>
                        @if(($createSlots['requires_resource'] ?? false) || (($createSlots['resources'] ?? []) !== []))
                            <label class="full">
                                <span>Ресурс</span>
                                <select name="create_resource_id">
                                    <option value="">{{ ($createSlots['requires_resource'] ?? false) ? 'Выберите ресурс' : 'Автоподбор' }}</option>
                                    @foreach(($createSlots['resources'] ?? []) as $resource)
                                        <option value="{{ $resource['id'] }}" @selected((string) $createResourceId === (string) $resource['id'])>{{ $resource['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <div class="booking-form-actions full">
                            <button class="btn" type="submit">Обновить доступные слоты</button>
                        </div>
                    </form>
                </div>

                @if($createService)
                    <form method="POST" action="{{ route('booking.admin.bookings.store') }}" class="booking-section">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $createService->id }}">
                        <input type="hidden" name="date" value="{{ $createDate }}">
                        <input type="hidden" name="resource_id" value="{{ $createResourceId }}">
                        <div class="booking-inline">
                            <h3 class="booking-section-title">{{ $createTranslation?->title ?? 'Услуга' }}</h3>
                            <span class="status-pill">{{ $createService->confirmation_mode }}</span>
                            <span class="status-pill">{{ $createSlots['mode'] ?? 'auto_assign' }}</span>
                        </div>
                        <div class="booking-form-grid">
                            <label class="full">
                                <span>Слот</span>
                                <select name="slot_id" required>
                                    <option value="">Выберите слот</option>
                                    @foreach(($createSlots['slots'] ?? []) as $slot)
                                        <option value="{{ $slot['id'] }}">
                                            {{ $slot['label'] }}
                                            @if(!empty($slot['available_count']))
                                                · свободно {{ $slot['available_count'] }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Клиент</span>
                                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required>
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" name="customer_email" value="{{ old('customer_email') }}">
                            </label>
                            <label>
                                <span>Телефон</span>
                                <input type="text" name="customer_phone" value="{{ old('customer_phone') }}">
                            </label>
                            <label>
                                <span>Статус счёта</span>
                                <select name="invoice_status">
                                    @foreach(['pending','issued','paid','cancelled'] as $status)
                                        <option value="{{ $status }}" @selected(old('invoice_status', 'pending') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Статус оплаты</span>
                                <select name="payment_status">
                                    @foreach(['unpaid','pending','paid','refunded','failed'] as $status)
                                        <option value="{{ $status }}" @selected(old('payment_status', 'unpaid') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="full">
                                <span>Комментарий клиента</span>
                                <textarea name="customer_comment" rows="3">{{ old('customer_comment') }}</textarea>
                            </label>
                            <label class="full">
                                <span>Внутренние заметки</span>
                                <textarea name="internal_notes" rows="4">{{ old('internal_notes') }}</textarea>
                            </label>
                        </div>
                        <div class="booking-form-actions">
                            <button class="btn btn-primary" type="submit" @disabled(empty($createSlots['slots']))>Создать бронь</button>
                        </div>
                        @if(empty($createSlots['slots']))
                            <p class="booking-helper">На выбранную дату сейчас нет доступных слотов. Измените дату, услугу или ресурс.</p>
                        @endif
                    </form>
                @endif
            </section>

            <section class="panel booking-detail-card">
                @if(!$selectedBooking)
                    <div class="empty-state">Выберите бронь слева, чтобы увидеть детали и операционные действия.</div>
                @else
                    @php($translation = $selectedBooking->service?->translations->firstWhere('locale', app()->getLocale()) ?? $selectedBooking->service?->translations->first())
                    <div class="booking-section">
                        <div class="booking-inline">
                            <h2 class="panel-section-title">Бронь #{{ $selectedBooking->id }}</h2>
                            <span class="status-pill">{{ $selectedBooking->status }}</span>
                        </div>
                        <div class="booking-stat-grid">
                            <div class="booking-stat">
                                <span class="muted">Услуга</span>
                                <strong>{{ $translation?->title ?? '—' }}</strong>
                            </div>
                            <div class="booking-stat">
                                <span class="muted">Время</span>
                                <strong>{{ $selectedBooking->starts_at?->format('d.m H:i') }} - {{ $selectedBooking->ends_at?->format('H:i') }}</strong>
                            </div>
                            <div class="booking-stat">
                                <span class="muted">Локация</span>
                                <strong>{{ $selectedBooking->location?->name ?? '—' }}</strong>
                            </div>
                            <div class="booking-stat">
                                <span class="muted">Ресурс</span>
                                <strong>{{ $selectedBooking->resource?->name ?? 'Автоподбор / не назначен' }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="booking-section">
                        <h3 class="booking-section-title">Клиент</h3>
                        <div class="booking-stack">
                            <div><strong>{{ $selectedBooking->customer_name }}</strong></div>
                            <div class="muted">{{ $selectedBooking->customer_email ?: 'Email не указан' }}</div>
                            <div class="muted">{{ $selectedBooking->customer_phone ?: 'Телефон не указан' }}</div>
                            @if($selectedBooking->customer_comment)
                                <div class="booking-widget-note">{{ $selectedBooking->customer_comment }}</div>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('booking.admin.bookings.update', $selectedBooking) }}" class="booking-section">
                        @csrf
                        @method('PUT')
                        <h3 class="booking-section-title">Операционные поля</h3>
                        <div class="booking-form-grid">
                            <label>
                                <span>Статус счёта</span>
                                <select name="invoice_status">
                                    @foreach(['pending','issued','paid','cancelled'] as $status)
                                        <option value="{{ $status }}" @selected($selectedBooking->invoice_status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Статус оплаты</span>
                                <select name="payment_status">
                                    @foreach(['unpaid','pending','paid','refunded','failed'] as $status)
                                        <option value="{{ $status }}" @selected($selectedBooking->payment_status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="full">
                                <span>Внутренние заметки</span>
                                <textarea name="internal_notes" rows="8">{{ old('internal_notes', $selectedBooking->internal_notes) }}</textarea>
                            </label>
                        </div>
                        <div class="booking-form-actions">
                            <button class="btn" type="submit">Сохранить карточку</button>
                        </div>
                    </form>

                    @if(!in_array($selectedBooking->status, ['cancelled','completed','no_show'], true))
                        <div class="booking-section">
                            <h3 class="booking-section-title">Перенос брони</h3>
                            <p class="booking-helper">Перенос использует тот же slot allocation, что и публичное бронирование. Для `choose_resource` сначала выбирается ресурс, затем доступный слот.</p>
                            <form method="GET" action="{{ route('booking.admin.bookings.index') }}" class="booking-form-grid">
                                <input type="hidden" name="booking_id" value="{{ $selectedBooking->id }}">
                                <label>
                                    <span>Дата</span>
                                    <input type="date" name="reschedule_date" value="{{ $rescheduleDate }}">
                                </label>
                                @if(($rescheduleSlots['requires_resource'] ?? false) || (($rescheduleSlots['resources'] ?? []) !== []))
                                    <label>
                                        <span>Ресурс</span>
                                        <select name="reschedule_resource_id">
                                            <option value="">{{ ($rescheduleSlots['requires_resource'] ?? false) ? 'Выберите ресурс' : 'Автоподбор' }}</option>
                                            @foreach(($rescheduleSlots['resources'] ?? []) as $resource)
                                                <option value="{{ $resource['id'] }}" @selected((string) $rescheduleResourceId === (string) $resource['id'])>{{ $resource['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                                <div class="booking-form-actions full">
                                    <button class="btn" type="submit">Обновить доступные слоты</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('booking.admin.bookings.reschedule', $selectedBooking) }}" class="booking-form-grid">
                                @csrf
                                <input type="hidden" name="date" value="{{ $rescheduleDate }}">
                                @if($rescheduleResourceId)
                                    <input type="hidden" name="resource_id" value="{{ $rescheduleResourceId }}">
                                @endif
                                <label class="full">
                                    <span>Новый слот</span>
                                    <select name="slot_id" required>
                                        <option value="">Выберите слот</option>
                                        @foreach(($rescheduleSlots['slots'] ?? []) as $slot)
                                            <option value="{{ $slot['id'] }}">
                                                {{ $slot['label'] }}
                                                @if(!empty($slot['available_count']))
                                                    · свободно {{ $slot['available_count'] }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="booking-form-actions full">
                                    <button class="btn" type="submit" @disabled(empty($rescheduleSlots['slots']))>Перенести бронь</button>
                                </div>
                            </form>
                            @if(empty($rescheduleSlots['slots']))
                                <p class="booking-helper">На выбранную дату нет доступных слотов для переноса.</p>
                            @endif
                        </div>
                    @endif

                    <div class="booking-section">
                        <h3 class="booking-section-title">Статусные действия</h3>
                        <div class="booking-form-actions">
                            @if($selectedBooking->status === 'requested')
                                <form method="POST" action="{{ route('booking.admin.bookings.confirm', $selectedBooking) }}">
                                    @csrf
                                    <button class="btn" type="submit">Подтвердить</button>
                                </form>
                            @endif

                            @if(!in_array($selectedBooking->status, ['cancelled','completed','no_show'], true))
                                <form method="POST" action="{{ route('booking.admin.bookings.cancel', $selectedBooking) }}" data-confirm="Отменить бронь?">
                                    @csrf
                                    <button class="btn btn-danger" type="submit">Отменить</button>
                                </form>
                            @endif

                            @if($selectedBooking->status === 'confirmed')
                                <form method="POST" action="{{ route('booking.admin.bookings.complete', $selectedBooking) }}">
                                    @csrf
                                    <button class="btn" type="submit">Завершить</button>
                                </form>
                                <form method="POST" action="{{ route('booking.admin.bookings.no-show', $selectedBooking) }}">
                                    @csrf
                                    <button class="btn" type="submit">No-show</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
@push('head')
    @include('booking-module::admin.partials.styles')
@endpush
