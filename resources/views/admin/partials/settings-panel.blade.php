<section class="panel {{ $panelClass ?? '' }}">
    @if(!empty($title))
        <h2 class="{{ $titleClass ?? 'panel-section-title' }}">{{ $title }}</h2>
    @endif

    @if(!empty($description))
        <p class="muted panel-section-description">{{ $description }}</p>
    @endif

    @if(!empty($bodyView))
        @include($bodyView, $bodyData ?? [])
    @endif
</section>
