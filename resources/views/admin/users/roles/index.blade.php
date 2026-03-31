@extends('admin.layout')

@section('title', 'Роли и права')

@section('content')
    <div class="page-header">
        <div>
            <h1>Роли и права</h1>
            <p>Матрица RBAC для ролей админки.</p>
        </div>
    </div>

    <div class="tabs">
        <a class="tab" href="{{ route('admin.users.index') }}">Пользователи</a>
        <a class="tab" style="background:#0f172a;color:#fff;border-color:#0f172a;" href="{{ route('admin.users.roles.index') }}">Роли и права</a>
    </div>

    @unless($canEditMatrix)
        <div class="flash error">
            Доступ только для чтения. Изменение матрицы ролей доступно только superadmin.
        </div>
    @endunless

    <section class="panel">
        <table>
            <thead>
            <tr>
                <th>Роль</th>
                <th>Guard</th>
                <th>Права</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($roles as $role)
                <tr>
                    <td class="mono">{{ $role->name }}</td>
                    <td class="mono">{{ $role->guard_name }}</td>
                    <td>{{ $role->permissions->count() }}</td>
                    <td>
                        <a class="btn btn-small" href="{{ route('admin.users.roles.edit', $role) }}">Открыть</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Роли не найдены.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
