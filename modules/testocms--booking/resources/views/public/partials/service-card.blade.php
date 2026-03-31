@php($translation = $service->getRelation('current_translation') ?? ($service->translations->firstWhere('locale', $locale) ?? $service->translations->first()))
@php($prefix = trim((string) ($bookingSettings['public_prefix'] ?? config('cms.booking_url_prefix', 'book')), '/'))
<article class="booking-card">
    @if($service->image?->public_url)
        <img src="{{ $service->image->public_url }}" alt="{{ $service->image->alt ?: $translation?->title }}" style="width:100%;aspect-ratio:16/9;object-fit:cover;display:block;">
    @endif
    <div class="booking-card-body">
        <div class="meta-row">
            <span class="tag brand">{{ strtoupper($locale) }}</span>
            <span class="tag">{{ $service->confirmation_mode }}</span>
            <span class="tag">{{ $service->duration_minutes }} мин</span>
        </div>
        <div>
            <h3 style="margin:0 0 8px;">{{ $translation?->title ?? '—' }}</h3>
            @if(!empty($showDescription ?? true) && !empty($translation?->short_description))
                <p class="muted">{{ $translation->short_description }}</p>
            @endif
        </div>
        @if(!empty($showPrices ?? true) && ($service->price_amount !== null || $service->price_label))
            <div class="booking-price">{{ $service->price_label ?: number_format((float) $service->price_amount, 2, '.', ' ').' '.$service->price_currency }}</div>
        @endif
        <div class="hero-actions">
            <a class="button button-primary" href="{{ url('/'.$locale.'/'.$prefix.'/services/'.$translation->slug) }}">{{ $ctaLabel ?? ($service->cta_label ?: ($locale === 'ru' ? 'Забронировать' : 'Book now')) }}</a>
        </div>
    </div>
</article>
