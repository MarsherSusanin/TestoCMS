@php($bookingNavItems = [
    ['route' => 'booking.admin.dashboard', 'label' => 'Обзор'],
    ['route' => 'booking.admin.services.index', 'label' => 'Услуги'],
    ['route' => 'booking.admin.resources.index', 'label' => 'Ресурсы'],
    ['route' => 'booking.admin.locations.index', 'label' => 'Локации'],
    ['route' => 'booking.admin.availability.index', 'label' => 'Доступность'],
    ['route' => 'booking.admin.calendar', 'label' => 'Календарь'],
    ['route' => 'booking.admin.bookings.index', 'label' => 'Брони'],
    ['route' => 'booking.admin.settings.edit', 'label' => 'Настройки'],
])
<div class="booking-subnav">
    @foreach($bookingNavItems as $item)
        <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*') ? 'active' : '' }}">{{ $item['label'] }}</a>
    @endforeach
</div>
