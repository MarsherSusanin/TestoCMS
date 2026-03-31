@extends('cms.layout')
@section('hero')
    <div class="hero-grid"><div><span class="eyebrow">Booking</span><h1 class="hero-title">{{ $translation->title }}</h1><p class="hero-description">{{ $translation->short_description ?: ($locale === 'ru' ? 'Выберите слот и отправьте бронирование.' : 'Choose a slot and submit a booking.') }}</p></div><aside class="hero-panel"><div class="hero-kpis"><div class="hero-kpi"><strong>{{ $service->duration_minutes }}</strong><span>{{ $locale === 'ru' ? 'Минут' : 'Minutes' }}</span></div><div class="hero-kpi"><strong>{{ strtoupper($service->confirmation_mode) }}</strong><span>{{ $locale === 'ru' ? 'Режим' : 'Mode' }}</span></div>@if($service->price_amount !== null || $service->price_label)<div class="hero-kpi"><strong>{{ $service->price_label ?: number_format((float) $service->price_amount, 2, '.', ' ').' '.$service->price_currency }}</strong><span>{{ $locale === 'ru' ? 'Стоимость' : 'Price' }}</span></div>@endif</div></aside></div>
@endsection
@section('content')
    <section class="surface"><div class="surface-body booking-stack">@if($translation->full_description)<div class="content-prose">{!! nl2br(e($translation->full_description)) !!}</div>@endif @include('booking-module::public.partials.booking-form', ['service' => $service, 'showHeading' => false])</div></section>
@endsection
@push('head')@include('booking-module::public.partials.styles')<script src="{{ asset('modules/testocms--booking/booking-public.js') }}" defer></script>@endpush
