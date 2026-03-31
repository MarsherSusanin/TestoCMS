@extends('setup.layout', ['title' => 'Установка завершена', 'currentStep' => 5])

@section('content')
    @if($hasErrors)
        <h2>⚠️ Установка завершена с ошибками</h2>
        <p style="color:#6b7280; margin-bottom:16px;">Некоторые этапы не выполнены. Исправьте ошибки и попробуйте снова.</p>
    @else
        <h2>🎉 Установка завершена!</h2>
        <p style="color:#6b7280; margin-bottom:16px;">TestoCMS успешно установлена и готова к работе.</p>
    @endif

    <ul class="check-list">
        @foreach($steps as $step)
            <li>
                <span class="check-icon {{ $step['ok'] ? 'pass' : 'fail' }}">{{ $step['ok'] ? '✓' : '✗' }}</span>
                <span>{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ul>

    @if(! empty($errors))
        <div class="alert alert-error" style="margin-top:16px;">
            <strong>Ошибки:</strong>
            @foreach($errors as $error)
                <div style="margin-top:4px;">• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(! $hasErrors)
        <div class="alert alert-success" style="margin-top:16px;">
            <strong>Что дальше:</strong>
            <ul style="margin-top:8px; padding-left:20px;">
                <li>Войдите в админку с указанными email и паролем</li>
                <li>Настройте тему оформления и навигацию</li>
                <li>Создайте первую страницу или публикацию</li>
            </ul>
        </div>

        <div class="actions" style="justify-content:center;">
            <a href="/admin/login" class="btn btn-success">Войти в админку →</a>
        </div>
    @else
        <div class="alert alert-info" style="margin-top:16px;">
            Проверьте параметры подключения к БД, права на запись каталогов и попробуйте запустить установку повторно.
        </div>
        <div class="actions" style="justify-content:center;">
            <a href="{{ route('setup.step1') }}" class="btn btn-primary">Начать заново</a>
        </div>
    @endif
@endsection
