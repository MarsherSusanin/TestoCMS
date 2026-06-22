@if($isPreview)
    <div class="preview-banner">{{ $labels['preview'] }}</div>
@endif

@hasSection('hero')
    <section class="hero-shell">
        @yield('hero')
    </section>
@endif

<main id="main" class="content-shell" tabindex="-1">
    @yield('content')
</main>
