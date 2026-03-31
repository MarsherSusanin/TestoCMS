<!DOCTYPE html>
@php
    $isEn = str_starts_with((string) app()->getLocale(), 'en');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isEn ? 'Admin Login' : 'Вход в админку' }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui; background:#f3f4f6; }
        .card { max-width:420px; margin:80px auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:24px; }
        label { display:block; margin-top:12px; font-size:14px; }
        input { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; margin-top:4px; }
        button { margin-top:16px; padding:10px 14px; border:0; border-radius:8px; background:#111827; color:#fff; }
        .error { color:#b91c1c; font-size:13px; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ config('app.name') }} · {{ $isEn ? 'Admin' : 'Админка' }}</h1>
    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        <label>{{ $isEn ? 'Email' : 'Email' }}
            <input type="email" name="email" value="{{ old('email') }}" required>
        </label>
        <label>{{ $isEn ? 'Password' : 'Пароль' }}
            <input type="password" name="password" required>
        </label>
        @if($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif
        <button type="submit">{{ $isEn ? 'Sign in' : 'Войти' }}</button>
    </form>
</div>
</body>
</html>
