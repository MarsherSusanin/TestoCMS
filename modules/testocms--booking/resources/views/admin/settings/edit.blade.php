@extends('admin.layout')
@section('title', 'Настройки Booking')

@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Настройки Booking',
        'description' => 'Глобальные параметры проекции слотов, webhook endpoints и операционные интеграции.',
    ])

    @include('admin.partials.management-stats', [
        'items' => $webhookStats,
        'class' => 'booking-management-stats',
    ])

    @if(filled($revealedSecret))
        <section class="panel booking-stack">
            <div class="booking-section">
                <h2 class="panel-section-title">Новый secret</h2>
                <p class="booking-helper">Показывается один раз после создания или rotate. Сохраните его во внешней системе сейчас.</p>
                <div class="booking-widget-note">
                    <code>{{ $revealedSecret }}</code>
                </div>
            </div>
        </section>
    @endif

    <div class="booking-grid booking-grid--settings">
        <form method="POST" action="{{ route('booking.admin.settings.update') }}" class="panel booking-stack">
            @csrf
            @method('PUT')
            <div class="booking-section">
                <h2 class="panel-section-title">Глобальные параметры</h2>
                <p class="booking-helper">Определяют длительность hold для заявок, глубину проекции календаря и публичный префикс маршрутов бронирования.</p>
                <div class="booking-form-grid">
                    <label>
                        <span>Hold TTL (мин)</span>
                        <input type="number" min="15" name="hold_ttl_minutes" value="{{ old('hold_ttl_minutes', $settings['hold_ttl_minutes']) }}" required>
                    </label>
                    <label>
                        <span>Projection horizon (дней)</span>
                        <input type="number" min="7" name="projection_horizon_days" value="{{ old('projection_horizon_days', $settings['projection_horizon_days']) }}" required>
                    </label>
                    <label class="full">
                        <span>Публичный prefix</span>
                        <input type="text" name="public_prefix" value="{{ old('public_prefix', $settings['public_prefix']) }}" required>
                    </label>
                </div>
            </div>

            <div class="booking-section">
                <h2 class="panel-section-title">Интеграции и уведомления</h2>
                <div class="booking-stack">
                    <label class="booking-checkbox">
                        <input type="checkbox" name="webhooks_enabled" value="1" @checked(old('webhooks_enabled', $settings['webhooks_enabled']))>
                        <span>
                            <strong>Webhooks включены</strong>
                            <small>События бронирования отправляются во все активные endpoints.</small>
                        </span>
                    </label>
                    <label class="booking-checkbox">
                        <input type="checkbox" name="email_notifications" value="1" @checked(old('email_notifications', $settings['email_notifications']))>
                        <span>
                            <strong>Email-уведомления</strong>
                            <small>Если почтовая конфигурация отсутствует, бронирование не ломается, но письма не отправляются.</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="booking-form-actions">
                <button class="btn btn-primary" type="submit">Сохранить настройки</button>
            </div>
        </form>

        <section class="panel booking-stack">
            <div class="booking-section">
                <h2 class="panel-section-title">Новый webhook endpoint</h2>
                <p class="booking-helper">Если не отметить события, endpoint будет получать все поддерживаемые booking events.</p>
                <form method="POST" action="{{ route('booking.admin.settings.webhooks.store') }}" class="booking-stack">
                    @csrf
                    <div class="booking-form-grid">
                        <label class="full">
                            <span>URL</span>
                            <input type="url" name="url" value="{{ old('url') }}" placeholder="https://example.com/hooks/booking" required>
                        </label>
                        <label class="full">
                            <span>Secret</span>
                            <input type="text" name="secret" value="{{ old('secret') }}" placeholder="Необязательный HMAC secret, иначе будет сгенерирован автоматически">
                        </label>
                        <div class="full booking-stack">
                            <span>События</span>
                            <div class="booking-event-grid">
                                @foreach($supportedEvents as $event)
                                    <label class="booking-checkbox booking-checkbox--inline">
                                        <input type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', []), true))>
                                        <span><code>{{ $event }}</code></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <label class="full booking-checkbox booking-checkbox--inline">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                            <span>Endpoint активен</span>
                        </label>
                    </div>
                    <div class="booking-form-actions">
                        <button class="btn btn-primary" type="submit">Создать endpoint</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <section class="panel booking-stack">
        <div class="booking-inline booking-inline--between">
            <div>
                <h2 class="panel-section-title">Webhook endpoints</h2>
                <p class="booking-helper">Поддерживаемые события: @foreach($supportedEvents as $event)<code>{{ $event }}</code>@if(!$loop->last), @endif @endforeach.</p>
            </div>
        </div>
        @if($webhookEndpoints->isEmpty())
            <p class="muted">Endpoints пока не настроены.</p>
        @else
            <div class="booking-stack">
                @foreach($webhookEndpoints as $endpoint)
                    <div class="booking-widget-note booking-stack">
                        <div class="booking-inline booking-inline--between">
                            <div class="booking-stack" style="gap:4px;">
                                <strong>Endpoint #{{ $endpoint->id }}</strong>
                                <div class="mono">{{ $endpoint->url }}</div>
                            </div>
                            <div class="booking-inline">
                                <span class="status-pill">{{ $endpoint->is_active ? 'active' : 'inactive' }}</span>
                                @if(filled($endpoint->secret))
                                    <span class="status-pill">Secret сохранён</span>
                                @endif
                            </div>
                        </div>
                        <div class="booking-service-meta">
                            <span>Доставок: {{ $endpoint->deliveries_total ?? 0 }}</span>
                            <span>Ошибок: {{ $endpoint->deliveries_failed ?? 0 }}</span>
                            <span>События: {{ blank($endpoint->subscribed_events) ? 'все' : implode(', ', $endpoint->subscribed_events) }}</span>
                        </div>
                        <form method="POST" action="{{ route('booking.admin.settings.webhooks.update', $endpoint) }}" class="booking-form-grid">
                            @csrf
                            @method('PUT')
                            <label class="full">
                                <span>URL</span>
                                <input type="url" name="url" value="{{ $endpoint->url }}" required>
                            </label>
                            <label class="full">
                                <span>Новый secret</span>
                                <input type="text" name="secret" placeholder="Оставьте пустым, чтобы сохранить текущий secret">
                            </label>
                            <div class="full booking-stack">
                                <span>События</span>
                                <div class="booking-event-grid">
                                    @foreach($supportedEvents as $event)
                                        <label class="booking-checkbox booking-checkbox--inline">
                                            <input type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, is_array($endpoint->subscribed_events) ? $endpoint->subscribed_events : [], true))>
                                            <span><code>{{ $event }}</code></span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <label class="full booking-checkbox booking-checkbox--inline">
                                <input type="checkbox" name="is_active" value="1" @checked($endpoint->is_active)>
                                <span>Endpoint активен</span>
                            </label>
                            <div class="booking-form-actions full">
                                <button class="btn" type="submit">Сохранить endpoint</button>
                            </div>
                        </form>
                        <div class="booking-form-actions">
                            <form method="POST" action="{{ route('booking.admin.settings.webhooks.rotate-secret', $endpoint) }}" data-confirm="Сгенерировать новый secret для webhook endpoint?">
                                @csrf
                                <button class="btn" type="submit">Rotate secret</button>
                            </form>
                            <form method="POST" action="{{ route('booking.admin.settings.webhooks.destroy', $endpoint) }}" data-confirm="Удалить webhook endpoint?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger" type="submit">Удалить endpoint</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="panel booking-stack">
        <div class="booking-inline booking-inline--between">
            <div>
                <h2 class="panel-section-title">Последние доставки webhook</h2>
                <p class="booking-helper">Последние попытки отправки событий. Здесь удобно быстро увидеть ошибки интеграции и ответ удалённой системы.</p>
            </div>
        </div>
        @include('admin.partials.operation-log-table', [
            'headers' => ['Время', 'Событие', 'Endpoint', 'Статус', 'HTTP', 'Payload', 'Response', 'Действия'],
            'logs' => $recentDeliveries,
            'emptyMessage' => 'Доставок пока не было.',
            'rowView' => 'booking-module::admin.settings.partials.delivery-row',
            'tableClass' => 'booking-log-table',
        ])
    </section>
@endsection

@push('head')
    @include('booking-module::admin.partials.styles')
@endpush
