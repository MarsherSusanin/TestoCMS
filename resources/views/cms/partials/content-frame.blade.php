@if($isPreview)
    <div class="preview-banner">{{ $labels['preview'] }}</div>
@endif

@hasSection('hero')
    <section class="hero-shell">
        @yield('hero')
    </section>
@endif

<main class="content-shell">
    @yield('content')
</main>
