@if(session('status'))
    <div class="flash success">{{ session('status') }}</div>
@endif

@if(session('preview_link'))
    <div class="flash success">
        Ссылка предпросмотра: <a href="{{ session('preview_link') }}" target="_blank" rel="noreferrer" class="mono">{{ session('preview_link') }}</a>
    </div>
@endif

@if($errors->any())
    @php
        $uniqueErrors = collect($errors->all())
            ->map(fn ($message) => (string) $message)
            ->unique()
            ->values();
    @endphp
    <div class="flash error">
        <strong>Ошибка валидации</strong>
        <ul style="margin:8px 0 0 18px;">
            @foreach($uniqueErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
