@extends('admin.layout')
@section('title', 'Календарь')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Календарь бронирований',
        'description' => 'Day/week view для операционного контроля бронирований по услугам, локациям и ресурсам.',
    ])
    <section class="panel booking-stack">
        <form method="GET" class="booking-form-grid">
            <input type="hidden" name="offset" value="{{ request('offset', 0) }}">
            <input type="hidden" name="date" value="{{ request('date', $start->format('Y-m-d')) }}">
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
                <span>Базовая дата</span>
                <input type="date" name="date" value="{{ request('date', $start->format('Y-m-d')) }}">
            </label>
            <label>
                <span>Вид</span>
                <select name="view">
                    <option value="week" @selected($viewMode === 'week')>Неделя</option>
                    <option value="day" @selected($viewMode === 'day')>День</option>
                </select>
            </label>
            <div class="booking-form-actions full">
                <button class="btn" type="submit">Применить</button>
                <a class="btn" href="{{ route('booking.admin.calendar', array_merge(request()->except('page'), ['view' => $viewMode, 'offset' => (int) request('offset', 0) - ($viewMode === 'day' ? 1 : 7)])) }}">← Назад</a>
                <a class="btn" href="{{ route('booking.admin.calendar', array_merge(request()->except('page'), ['view' => $viewMode, 'offset' => (int) request('offset', 0) + ($viewMode === 'day' ? 1 : 7)])) }}">Вперёд →</a>
            </div>
        </form>

        <div class="booking-helper">
            Период: {{ $start->format('d.m.Y') }} — {{ $end->format('d.m.Y') }}.
            Клик по брони ведёт в inbox для операционных действий и заметок.
        </div>

        <div class="booking-day-grid {{ $viewMode === 'day' ? 'booking-day-grid--day' : '' }}">
            @php($daysCount = $viewMode === 'day' ? 1 : 7)
            @for($i = 0; $i < $daysCount; $i++)
                @php($day = $start->copy()->addDays($i))
                @php($dayBookings = $bookings->get($day->format('Y-m-d'), collect()))
                <div class="booking-day">
                    <h3>{{ $day->format('D d.m') }}</h3>
                    @forelse($dayBookings as $booking)
                        @php($translation = $booking->service?->translations->firstWhere('locale', app()->getLocale()) ?? $booking->service?->translations->first())
                        <a class="booking-booking" href="{{ route('booking.admin.bookings.index', ['booking_id' => $booking->id]) }}">
                            <strong>{{ $booking->starts_at?->format('H:i') }} · {{ $translation?->title ?? '—' }}</strong>
                            <span>{{ $booking->customer_name }}</span>
                            <span class="muted">{{ $booking->location?->name ?? '—' }} @if($booking->resource) · {{ $booking->resource->name }} @endif</span>
                            <span class="status-pill">{{ $booking->status }}</span>
                        </a>
                    @empty
                        <p class="muted">Броней нет.</p>
                    @endforelse
                </div>
            @endfor
        </div>
    </section>
@endsection
@push('head')
    @include('booking-module::admin.partials.styles')
@endpush
