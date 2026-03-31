@php
    $iconMarkup = app(\App\Modules\Core\Services\AdminSidebarIconRegistry::class)->render(isset($icon) ? trim((string) $icon) : null);
@endphp
@if($iconMarkup !== null)
    {!! $iconMarkup !!}
@else
    {{ $fallback ?? '' }}
@endif
