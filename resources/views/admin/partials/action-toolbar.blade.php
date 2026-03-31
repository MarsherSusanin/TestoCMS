@php
    $title = $title ?? '';
    $description = $description ?? '';
    $meta = $meta ?? null;
    $primaryAction = $primaryAction ?? null;
    $secondaryActions = array_values(array_filter((array) ($secondaryActions ?? []), static fn ($item) => is_array($item) && !empty($item['label'])));
@endphp

<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if($description !== '')
            <p>{!! $description !!}</p>
        @endif
        @if(!empty($meta))
            <div class="page-header-meta">{!! $meta !!}</div>
        @endif
    </div>
    @if((is_array($primaryAction) && !empty($primaryAction['label'])) || $secondaryActions !== [])
        <div class="actions">
            @include('admin.partials.row-action-menu', [
                'primary' => $primaryAction,
                'items' => $secondaryActions,
                'class' => 'page-header-actions',
                'menuLabel' => 'Дополнительные действия страницы',
            ])
        </div>
    @endif
</div>
