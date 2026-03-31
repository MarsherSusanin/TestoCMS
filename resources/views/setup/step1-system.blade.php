@extends('setup.layout', ['title' => 'Проверка системы — Установка', 'currentStep' => 1])

@section('content')
    <h2>Проверка системы</h2>
    <p style="color:#6b7280; margin-bottom:16px;">Проверяем совместимость сервера с TestoCMS.</p>

    <ul class="check-list">
        @foreach($checks as $key => $check)
            <li>
                @if($check['passed'])
                    <span class="check-icon {{ ($check['optional'] ?? false) ? 'opt' : 'pass' }}">✓</span>
                @else
                    <span class="check-icon {{ ($check['optional'] ?? false) ? 'opt' : 'fail' }}">✗</span>
                @endif
                <span>{{ $check['label'] }}</span>
                <span class="check-detail">{{ $check['detail'] }}</span>
            </li>
        @endforeach
    </ul>

    @if(! $allPassed)
        <div class="alert alert-error" style="margin-top:16px;">
            Не все обязательные требования выполнены. Установите недостающие расширения PHP и убедитесь, что каталоги доступны для записи.
        </div>
    @endif

    <div class="actions">
        <div></div>
        @if($allPassed)
            <a href="{{ route('setup.step2') }}" class="btn btn-primary">Далее →</a>
        @else
            <button class="btn btn-primary" disabled>Далее →</button>
        @endif
    </div>
@endsection
