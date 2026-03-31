@extends('admin.layout')

@php
    $selectedPermissions = old('permissions', $role->permissions->pluck('name')->values()->all());
    if (!is_array($selectedPermissions)) {
        $selectedPermissions = [];
    }
    $isReadOnly = !$canEditMatrix || $isSuperadminRole;
@endphp

@section('title', 'Роль: '.$role->name)

@push('head')
<style>
    .permission-grid {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .permission-item {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 10px;
        background: #fff;
    }
    @media (max-width: 1180px) {
        .permission-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 760px) {
        .permission-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Роль: <span class="mono">{{ $role->name }}</span></h1>
            <p>Редактирование permissions для роли.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.users.roles.index') }}" class="btn">Назад к ролям</a>
        </div>
    </div>

    <div class="tabs">
        <a class="tab" href="{{ route('admin.users.index') }}">Пользователи</a>
        <a class="tab" style="background:#0f172a;color:#fff;border-color:#0f172a;" href="{{ route('admin.users.roles.index') }}">Роли и права</a>
    </div>

    @if($isSuperadminRole)
        <div class="flash error">Роль superadmin защищена: редактирование через UI отключено.</div>
    @elseif(!$canEditMatrix)
        <div class="flash error">Доступ только для чтения. Изменение матрицы ролей доступно только superadmin.</div>
    @endif

    <form method="POST" action="{{ route('admin.users.roles.update', $role) }}">
        @csrf
        @method('PUT')

        <section class="panel">
            <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
                <h2 style="margin:0;">Permissions ({{ count($allPermissions) }})</h2>
                <span class="status-pill mono">{{ $role->guard_name }}</span>
            </div>

            <div class="permission-grid">
                @forelse($allPermissions as $permission)
                    @php
                        $permissionName = (string) $permission->name;
                    @endphp
                    <label class="permission-item checkbox">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $permissionName }}"
                            @checked(in_array($permissionName, $selectedPermissions, true))
                            @disabled($isReadOnly)
                        >
                        <span class="mono">{{ $permissionName }}</span>
                    </label>
                @empty
                    <p class="muted">Права не найдены.</p>
                @endforelse
            </div>

            @unless($isReadOnly)
                <div class="actions" style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </div>
            @endunless
        </section>
    </form>
@endsection
