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
@include('booking-module::admin.partials.nav')
