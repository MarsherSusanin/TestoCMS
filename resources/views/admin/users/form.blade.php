@extends('admin.layout')

@php
    $selectedRoles = old('roles', $assignedRoleNames ?? []);
    if (!is_array($selectedRoles)) {
        $selectedRoles = [];
    }
    $currentStatus = old('status', $userModel->status ?? 'active');
@endphp

@section('title', $isEdit ? 'Редактирование пользователя' : 'Создание пользователя')

@push('head')
<style>
    .roles-grid {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .role-item {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 10px;
        background: #fff;
    }
    .status-active { background: #ecfdf3; border-color: #86efac; color: #166534; }
    .status-blocked { background: #fef2f2; border-color: #fca5a5; color: #b42318; }
    @media (max-width: 1100px) {
        .roles-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 720px) {
        .roles-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $isEdit ? 'Редактирование пользователя' : 'Создание пользователя' }}</h1>
            <p>{{ $isEdit ? 'Пользователь #'.$userModel->id : 'Новая учётная запись для админки CMS.' }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.users.index') }}" class="btn">Назад к пользователям</a>
        </div>
    </div>

    <div class="tabs">
        <a class="tab" style="background:#0f172a;color:#fff;border-color:#0f172a;" href="{{ route('admin.users.index') }}">Пользователи</a>
        <a class="tab" href="{{ route('admin.users.roles.index') }}">Роли и права</a>
    </div>

    <div class="split">
        <form method="POST" action="{{ $isEdit ? route('admin.users.update', $userModel) : route('admin.users.store') }}" class="panel">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

                <h2 style="margin-top:0;">Профиль</h2>

                <div class="grid cols-2">
                    <div class="field">
                        <label for="user-name">Имя</label>
                        <input id="user-name" type="text" name="name" value="{{ old('name', $userModel->name) }}" maxlength="255" required>
                    </div>
                    <div class="field">
                        <label for="user-login">Логин</label>
                        <input id="user-login" type="text" name="login" value="{{ old('login', $userModel->login) }}" maxlength="120" required>
                    </div>
                </div>

                <div class="grid cols-2">
                    <div class="field">
                        <label for="user-email">Email</label>
                        <input id="user-email" type="email" name="email" value="{{ old('email', $userModel->email) }}" maxlength="255" required>
                    </div>
                    <div class="field">
                        <label for="user-status">Статус</label>
                        <select id="user-status" name="status" required>
                            <option value="active" @selected($currentStatus === 'active')>active</option>
                            <option value="blocked" @selected($currentStatus === 'blocked')>blocked</option>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label>Роли</label>
                    <div class="roles-grid">
                        @forelse($allRoleNames as $roleName)
                            <label class="role-item checkbox">
                                <input
                                    type="checkbox"
                                    name="roles[]"
                                    value="{{ $roleName }}"
                                    @checked(in_array($roleName, $selectedRoles, true))
                                >
                                <span class="mono">{{ $roleName }}</span>
                            </label>
                        @empty
                            <p class="muted" style="margin:0;">Нет доступных ролей для назначения.</p>
                        @endforelse
                    </div>
                </div>

                @unless($isEdit)
                    <div class="grid cols-2">
                        <div class="field">
                            <label for="user-password">Пароль</label>
                            <input id="user-password" type="password" name="password" required>
                            <small>Минимум 10 символов, mixed-case, цифра и спецсимвол.</small>
                        </div>
                        <div class="field">
                            <label for="user-password-confirmation">Подтверждение пароля</label>
                            <input id="user-password-confirmation" type="password" name="password_confirmation" required>
                        </div>
                    </div>
                @endunless

                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        {{ $isEdit ? 'Сохранить изменения' : 'Создать пользователя' }}
                    </button>
                </div>
        </form>

        <div>
            @if($isEdit)
                <section class="panel">
                    <h2 style="margin-top:0;">Состояние учётки</h2>
                    <p class="muted" style="margin-top:4px;">
                        Текущий статус:
                        <span class="status-pill status-{{ ($userModel->status ?? 'active') === 'blocked' ? 'blocked' : 'active' }}">
                            {{ $userModel->status ?? 'active' }}
                        </span>
                    </p>
                    <form method="POST" action="{{ route('admin.users.status', $userModel) }}" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="status" value="{{ ($userModel->status ?? 'active') === 'active' ? 'blocked' : 'active' }}">
                        <button type="submit" class="btn {{ ($userModel->status ?? 'active') === 'active' ? 'btn-danger' : 'btn-primary' }}">
                            {{ ($userModel->status ?? 'active') === 'active' ? 'Заблокировать пользователя' : 'Разблокировать пользователя' }}
                        </button>
                    </form>
                </section>

                <section class="panel">
                    <h2 style="margin-top:0;">Смена пароля</h2>
                    <p class="muted" style="margin-top:4px;">Пароль меняется администратором без текущего пароля пользователя.</p>
                    <form method="POST" action="{{ route('admin.users.password', $userModel) }}">
                        @csrf
                        <div class="field">
                            <label for="edit-user-password">Новый пароль</label>
                            <input id="edit-user-password" type="password" name="password" required>
                        </div>
                        <div class="field">
                            <label for="edit-user-password-confirmation">Подтверждение пароля</label>
                            <input id="edit-user-password-confirmation" type="password" name="password_confirmation" required>
                            <small>После смены пароля активные сессии пользователя завершатся.</small>
                        </div>
                        <button type="submit" class="btn">Сменить пароль</button>
                    </form>
                </section>
            @else
                <section class="panel">
                    <h2 style="margin-top:0;">Подсказка</h2>
                    <p class="muted" style="margin:0;">
                        Для новых пользователей обязательны минимум одна роль и пароль.
                    </p>
                </section>
            @endif
        </div>
    </div>
@endsection
