@extends('admin.layout')

@section('title', 'Настройки')

@section('content')
    <div class="page-header">
        <div>
            <h1>Настройки</h1>
            <p>Параметры интерфейса админ-панели для текущего пользователя.</p>
        </div>
    </div>

    <div class="grid cols-2">
        <section class="panel">
            <h2 style="margin-top:0;">Язык интерфейса</h2>
            <p class="muted" style="margin-top:0;">Выбранный язык сохраняется в сессии и cookie браузера.</p>

            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="admin-ui-locale">Язык</label>
                    <select id="admin-ui-locale" name="ui_locale">
                        @foreach($supportedUiLocales as $localeCode)
                            <option value="{{ $localeCode }}" @selected($currentUiLocale === $localeCode)>
                                {{ $localeCode === 'ru' ? 'Русский' : 'English' }}
                            </option>
                        @endforeach
                    </select>
                    <small>Сейчас активен: <span class="mono">{{ strtoupper($currentUiLocale) }}</span>.</small>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2 style="margin-top:0;">Примечание</h2>
            <p class="muted" style="margin-top:0;">Интерфейс админки поддерживает русский и английский языки. Переключение влияет только на админ-панель.</p>
            <ul style="margin:0 0 0 18px; color:var(--muted);">
                <li>Выбор языка влияет на язык интерфейса админки (через сессию/cookie).</li>
                <li>Публичный сайт продолжает использовать языки контента (`ru`, `en`, ...).</li>
                <li>Настройка применяется сразу после сохранения.</li>
            </ul>
        </section>
    </div>
@endsection
