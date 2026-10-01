@extends('setup.layout', ['title' => 'Подтверждение установки', 'currentStep' => 5])

@section('content')
    <h2>Подтверждение установки</h2>
    <p>Сайт: {{ $site['app_name'] }} ({{ $site['app_url'] }})</p>
    <p>Администратор: {{ $admin['admin_email'] }}</p>
    <p>Web root: {{ $publicRoot ?? public_path() }}</p>
    <p style="margin-top:16px;">После подтверждения будут сохранены настройки, созданы таблицы и учетная запись администратора.</p>
    <form method="POST" action="{{ route('setup.finalize') }}">
        @csrf
        <div class="actions">
            <a href="{{ route('setup.step4') }}" class="btn btn-secondary">Назад</a>
            <button type="submit" class="btn btn-primary">Установить TestoCMS</button>
        </div>
    </form>
@endsection
