<div
    class="template-modal action-modal {{ !empty($open) ? 'open' : '' }}"
    data-modal="{{ $id }}"
    aria-hidden="{{ !empty($open) ? 'false' : 'true' }}"
>
    <div class="template-modal-card action-modal-card {{ $cardClass ?? '' }}">
        <div class="action-modal-head">
            <div>
                <h3 class="action-modal-title" data-action-modal-title>{{ $title }}</h3>
                @if(!empty($description))
                    <p class="muted action-modal-description" data-action-modal-description>{{ $description }}</p>
                @endif
            </div>
            <button type="button" class="btn btn-small" data-modal-close>Закрыть</button>
        </div>
        @if(!empty($bodyView))
            @include($bodyView, $bodyData ?? [])
        @endif
    </div>
</div>
