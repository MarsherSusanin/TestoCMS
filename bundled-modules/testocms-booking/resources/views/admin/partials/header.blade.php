@if(!empty($primaryAction) || !empty($secondaryActions))
    @include('admin.partials.action-toolbar', [
        'title' => $title,
        'description' => $description ?? '',
        'primaryAction' => $primaryAction ?? null,
        'secondaryActions' => $secondaryActions ?? [],
    ])
@else
    <div class="page-header">
        <div>
            <h1>{{ $title }}</h1>
            @if(!empty($description))
                <p>{{ $description }}</p>
            @endif
        </div>
        @if(!empty($actions))
            <div class="actions">{!! $actions !!}</div>
        @endif
    </div>
@endif
@include('booking-module::admin.partials.nav')
