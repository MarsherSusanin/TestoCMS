@extends('cms.layout')
@section('hero')
    <div class="hero-grid"><div><span class="eyebrow">Booking</span><h1 class="hero-title">{{ $locale === 'ru' ? 'Онлайн-бронирование услуг' : 'Online service booking' }}</h1><p class="hero-description">{{ $locale === 'ru' ? 'Выберите услугу, проверьте слоты и оформите запись без звонка.' : 'Choose a service, inspect slots and submit a reservation without a phone call.' }}</p></div><aside class="hero-panel"><h3>{{ $locale === 'ru' ? 'Booking module' : 'Booking module' }}</h3><p>{{ $locale === 'ru' ? 'Универсальный модуль для записи на услуги, консультации, аренду и визиты.' : 'Universal module for booking services, consultations, rentals and appointments.' }}</p></aside></div>
@endsection
@section('content')
    <section class="surface"><div class="surface-body"><div class="booking-grid">@forelse($services as $service) @include('booking-module::public.partials.service-card', ['showDescription' => true, 'showPrices' => true, 'ctaLabel' => $locale === 'ru' ? 'Открыть услугу' : 'Open service']) @empty <div class="empty-state">{{ $locale === 'ru' ? 'Пока нет активных услуг.' : 'No active services yet.' }}</div> @endforelse</div></div></section>
@endsection
@push('head')@include('booking-module::public.partials.styles')<script src="{{ asset('modules/testocms--booking/booking-public.js') }}" defer></script>@endpush
