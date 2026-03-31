@extends('admin.layout')

@section('title', 'Конструктор темы')

@section('content')
    <div class="page-header">
        <div>
            <h1>Конструктор темы</h1>
            <p>Настройка шрифтов, цветов и пресетов для публичной части сайта.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ url('/'.config('cms.default_locale', 'ru')) }}" target="_blank" rel="noreferrer">Открыть сайт</a>
        </div>
    </div>

    @include('admin.theme.partials.presets')

    <div class="theme-form-grid" style="margin-top:14px;">
        @include('admin.theme.partials.theme-builder')
        @include('admin.theme.partials.theme-preview')
    </div>

    @include('admin.theme.partials.chrome-builder')

    <script type="application/json" id="testocms-theme-editor-boot">{!! json_encode($adminThemeBootPayload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'editor-shared.js']) }}"></script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'theme-builder.js']) }}"></script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'chrome-builder.js']) }}"></script>
@endsection

@push('head')
    @include('admin.theme.partials.styles')
@endpush
