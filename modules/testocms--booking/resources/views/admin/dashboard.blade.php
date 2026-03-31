@extends('admin.layout')

@section('title', 'Booking')

@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Booking',
        'description' => 'Универсальное бронирование услуг, слотов и входящих заявок.',
    ])

    <div class="cards booking-card-grid">
        <div class="card"><div class="label">Услуги</div><div class="value">{{ $stats['services'] }}</div><div class="muted">Активных: {{ $stats['active_services'] }}</div></div>
        <div class="card"><div class="label">Ресурсы</div><div class="value">{{ $stats['resources'] }}</div><div class="muted">Сотрудники, комнаты, оборудование</div></div>
        <div class="card"><div class="label">Локации</div><div class="value">{{ $stats['locations'] }}</div><div class="muted">Точки оказания услуг</div></div>
        <div class="card"><div class="label">Входящие</div><div class="value">{{ $stats['requested'] }}</div><div class="muted">Ожидают подтверждения</div></div>
        <div class="card"><div class="label">Ближайшие</div><div class="value">{{ $stats['upcoming'] }}</div><div class="muted">requested + confirmed</div></div>
    </div>

    <section class="panel" style="margin-top:14px;">
        <h2 class="panel-section-title">Последние брони</h2>
        @if($recentBookings->isEmpty())
            <div class="empty-state">Пока нет бронирований. Сначала создайте услугу и настройте доступность.</div>
        @else
            <table>
                <thead><tr><th>ID</th><th>Услуга</th><th>Клиент</th><th>Старт</th><th>Статус</th></tr></thead>
                <tbody>
                @foreach($recentBookings as $booking)
                    @php($translation = $booking->service?->translations->firstWhere('locale', app()->getLocale()) ?? $booking->service?->translations->first())
                    <tr>
                        <td>#{{ $booking->id }}</td>
                        <td>{{ $translation?->title ?? '—' }}</td>
                        <td>{{ $booking->customer_name }}</td>
                        <td>{{ $booking->starts_at?->format('d.m.Y H:i') }}</td>
                        <td><span class="status-pill">{{ $booking->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection

@push('head')
    @include('booking-module::admin.partials.styles')
@endpush
