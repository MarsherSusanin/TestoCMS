@extends('setup.layout', ['title' => 'Настройки сайта — Установка', 'currentStep' => 3])

@section('content')
    <h2>Настройки сайта</h2>
    <p style="color:#6b7280; margin-bottom:16px;">Основные параметры вашего сайта.</p>

    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('setup.step3.save') }}">
        @csrf

        <div class="form-group">
            <label>Профиль размещения</label>
            <div class="choice-grid">
                @foreach($profiles as $profileKey => $profile)
                    <div class="choice-card">
                        <label for="deployment_profile_{{ $profileKey }}">
                            <input
                                type="radio"
                                name="deployment_profile"
                                id="deployment_profile_{{ $profileKey }}"
                                value="{{ $profileKey }}"
                                {{ old('deployment_profile', $defaultDeploymentProfile) === $profileKey ? 'checked' : '' }}
                            >
                            <span>
                                <span class="choice-card-title">{{ $profile['label'] }}</span>
                                <span class="choice-card-text">{{ $profile['description'] }}</span>
                            </span>
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="hint">По умолчанию рекомендуется shared hosting. Docker/VPS пригоден для отдельного сервера с queue worker.</div>
        </div>

        <div class="form-group">
            <label for="app_name">Название сайта</label>
            <input type="text" name="app_name" id="app_name" value="{{ old('app_name', 'TestoCMS') }}" required>
        </div>

        <div class="form-group">
            <label for="app_url">URL сайта</label>
            <input type="url" name="app_url" id="app_url" value="{{ old('app_url', $auto['app_url'] ?? 'https://') }}" required>
            <div class="hint">Полный URL с протоколом, без слеша в конце</div>
        </div>

        <div class="form-group">
            <label>Поддерживаемые языки</label>
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="supported_locales[]" value="ru"
                        {{ in_array('ru', old('supported_locales', ['ru', 'en'])) ? 'checked' : '' }}>
                    Русский
                </label>
                <label>
                    <input type="checkbox" name="supported_locales[]" value="en"
                        {{ in_array('en', old('supported_locales', ['ru', 'en'])) ? 'checked' : '' }}>
                    English
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="default_locale">Язык по умолчанию</label>
            <select name="default_locale" id="default_locale">
                <option value="ru" {{ old('default_locale', 'ru') === 'ru' ? 'selected' : '' }}>Русский</option>
                <option value="en" {{ old('default_locale') === 'en' ? 'selected' : '' }}>English</option>
            </select>
        </div>

        <div class="actions">
            <a href="{{ route('setup.step2') }}" class="btn btn-secondary">← Назад</a>
            <button type="submit" class="btn btn-primary">Далее →</button>
        </div>
    </form>
@endsection
