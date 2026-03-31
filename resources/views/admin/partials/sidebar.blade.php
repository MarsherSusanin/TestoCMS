@php
    $shell = $adminShell ?? [];
    $nav = is_array($shell['nav'] ?? null) ? $shell['nav'] : [];
    $mainItems = is_array($nav['main'] ?? null) ? $nav['main'] : [];
    $extensionItems = is_array($nav['extensions'] ?? null) ? $nav['extensions'] : [];
    $publicItems = is_array($nav['public'] ?? null) ? $nav['public'] : [];
    $isAdminUiEn = (bool) ($shell['is_admin_ui_en'] ?? false);
@endphp
<aside class="sidebar">
    <div class="sidebar-header">
        <h1 class="brand">
            <a href="{{ route('admin.dashboard') }}">
                <span class="brand-text">{{ config('app.name') }}</span>
                <span class="brand-short">TC</span>
            </a>
        </h1>
        <button
            type="button"
            class="sidebar-toggle"
            data-admin-sidebar-toggle
            aria-label="Свернуть меню"
            aria-expanded="true"
            title="Свернуть/развернуть меню"
        >
            <span class="sidebar-toggle-icon" aria-hidden="true">‹</span>
        </button>
    </div>

    <div class="nav-group">
        <div class="nav-title">Контент</div>
        @foreach($mainItems as $item)
            <a class="nav-link {{ !empty($item['active']) ? 'active' : '' }}" href="{{ $item['href'] }}" title="{{ $item['label'] }}">
                <span class="nav-short" aria-hidden="true">@include('admin.partials.nav-icon', ['icon' => $item['icon'] ?? null, 'fallback' => $isAdminUiEn ? ($item['short_en'] ?? $item['short_ru']) : ($item['short_ru'] ?? '')])</span>
                <span class="nav-label">{{ $item['label'] }}</span>
                @if(!empty($item['badge']))
                    <span class="nav-badge mono" aria-label="update version">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if($extensionItems !== [])
        <div class="nav-group">
            <div class="nav-title">Расширения</div>
            @foreach($extensionItems as $item)
                <a
                    class="nav-link {{ !empty($item['active']) ? 'active' : '' }}"
                    href="{{ $item['href'] }}"
                    title="{{ $item['title'] ?? $item['label'] }}"
                    @if(!empty($item['external'])) target="_blank" rel="noreferrer" @endif
                >
                    <span class="nav-short" aria-hidden="true">@include('admin.partials.nav-icon', ['icon' => $item['icon'] ?? null, 'fallback' => $item['short'] ?? 'Мд'])</span>
                    <span class="nav-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
            <a href="{{ route('admin.settings.edit') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('admin.settings.edit') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                @include('admin.partials.nav-icon', ['name' => 'cog-6-tooth', 'class' => 'mr-3 h-5 w-5 flex-shrink-0 '. (request()->routeIs('admin.settings.edit') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500')])
                UI
            </a>
            <a href="{{ route('admin.settings.seo.edit') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('admin.settings.seo.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                @include('admin.partials.nav-icon', ['name' => 'globe-alt', 'class' => 'mr-3 h-5 w-5 flex-shrink-0 '. (request()->routeIs('admin.settings.seo.*') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500')])
                SEO
            </a>
        </div>
    @endif

    <div class="nav-group">
        <div class="nav-title">Публичный сайт</div>
        @foreach($publicItems as $item)
            <a class="nav-link" href="{{ $item['href'] }}" title="{{ $item['title'] ?? $item['label'] }}" @if(!empty($item['external'])) target="_blank" rel="noreferrer" @endif>
                <span class="nav-short" aria-hidden="true">@include('admin.partials.nav-icon', ['icon' => $item['icon'] ?? null, 'fallback' => $item['short'] ?? '•'])</span>
                <span class="nav-label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="logout-btn" title="Выйти">
            <span class="logout-icon" aria-hidden="true">{{ $isAdminUiEn ? 'L' : 'В' }}</span>
            <span class="logout-label">Выйти</span>
        </button>
    </form>
</aside>
