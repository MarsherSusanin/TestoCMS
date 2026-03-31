@extends('setup.layout', ['title' => 'Администратор — Установка', 'currentStep' => 4])

@section('content')
    <h2>Учётная запись администратора</h2>
    <p style="color:#6b7280; margin-bottom:16px;">Создайте аккаунт суперадминистратора.</p>

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('setup.step4.save') }}">
        @csrf

        <div class="form-group">
            <label for="admin_name">Имя</label>
            <input type="text" name="admin_name" id="admin_name" value="{{ old('admin_name', 'Super Admin') }}" required>
        </div>

        <div class="form-group">
            <label for="admin_login">Логин</label>
            <input type="text" name="admin_login" id="admin_login" value="{{ old('admin_login', 'admin') }}" required>
        </div>

        <div class="form-group">
            <label for="admin_email">Email</label>
            <input type="email" name="admin_email" id="admin_email" value="{{ old('admin_email', '') }}" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="admin_password">Пароль</label>
                <input type="password" name="admin_password" id="admin_password" required minlength="8">
                <div class="hint">Минимум 8 символов</div>
            </div>
            <div class="form-group">
                <label for="admin_password_confirmation">Подтверждение</label>
                <input type="password" name="admin_password_confirmation" id="admin_password_confirmation" required minlength="8">
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('setup.step3') }}" class="btn btn-secondary">← Назад</a>
            <button type="submit" class="btn btn-primary">Установить CMS →</button>
        </div>
    </form>
@endsection
