@extends('admin.layout')

@section('title', 'Пользователи')

@push('head')
<style>
    .status-active { background: #ecfdf3; border-color: #86efac; color: #166534; }
    .status-blocked { background: #fef2f2; border-color: #fca5a5; color: #b42318; }
</style>
@endpush

@section('content')
    @include('admin.partials.action-toolbar', [
        'title' => 'Пользователи',
        'description' => 'Управление учётными записями, ролями и доступом в админку.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Создать пользователя',
            'href' => route('admin.users.create'),
            'class' => 'btn-primary',
        ],
    ])

    <div class="tabs">
        <a class="tab" style="background:#0f172a;color:#fff;border-color:#0f172a;" href="{{ route('admin.users.index') }}">Пользователи</a>
        <a class="tab" href="{{ route('admin.users.roles.index') }}">Роли и права</a>
    </div>

    <section class="panel">
        @if($users->isEmpty())
            @include('admin.partials.empty-state', [
                'icon' => 'Пл',
                'title' => 'Пока нет дополнительных пользователей',
                'description' => 'Создайте редакторов, администраторов или наблюдателей, чтобы разделить доступ к контенту, настройкам и API.',
                'hints' => [
                    [
                        'title' => 'Роли и права.',
                        'body' => 'После создания учётки назначьте ей роль. Матрица прав настраивается на соседней вкладке «Роли и права».'
                    ],
                    [
                        'title' => 'Безопасность.',
                        'body' => 'Статус <span class="mono">blocked</span> позволяет отключить доступ без удаления записи и потери истории действий.'
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'link',
                        'href' => route('admin.users.create'),
                        'label' => 'Создать пользователя',
                        'class' => 'btn-primary',
                    ],
                    [
                        'type' => 'link',
                        'href' => route('admin.users.roles.index'),
                        'label' => 'Открыть роли и права',
                    ],
                ],
            ])
        @else
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Логин / Email</th>
                    <th>Роли</th>
                    <th>Статус</th>
                    <th>Последний вход</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($users as $listedUser)
                    @php
                        $isTargetSuperadmin = $listedUser->roles->contains(static fn ($role) => (string) $role->name === 'superadmin');
                        $canEditTarget = auth()->user()?->hasRole('superadmin') || ! $isTargetSuperadmin;
                    @endphp
                    <tr>
                        <td class="mono">#{{ $listedUser->id }}</td>
                        <td>
                            <strong>{{ $listedUser->name }}</strong>
                        </td>
                        <td>
                            <div class="mono">{{ $listedUser->login ?: '—' }}</div>
                            <div>{{ $listedUser->email }}</div>
                        </td>
                        <td>
                            @forelse($listedUser->roles as $role)
                                <span class="tab mono">{{ $role->name }}</span>
                            @empty
                                <span class="muted">Без ролей</span>
                            @endforelse
                        </td>
                        <td>
                            <span class="status-pill status-{{ $listedUser->status === 'blocked' ? 'blocked' : 'active' }}">
                                {{ $listedUser->status }}
                            </span>
                        </td>
                        <td>{{ optional($listedUser->last_login_at)->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>
                            @if($canEditTarget)
                                @php
                                    $statusTarget = $listedUser->status === 'blocked' ? 'active' : 'blocked';
                                    $statusLabel = $listedUser->status === 'blocked' ? 'Разблокировать' : 'Заблокировать';
                                    $rowItems = [
                                        [
                                            'type' => 'form',
                                            'label' => $statusLabel,
                                            'action' => route('admin.users.status', $listedUser),
                                            'fields' => ['status' => $statusTarget],
                                        ],
                                        [
                                            'type' => 'button',
                                            'label' => 'Сменить пароль…',
                                            'attrs' => [
                                                'data-action-modal' => 'user-password-modal',
                                                'data-action-title' => 'Сменить пароль пользователя',
                                                'data-action-form-action' => route('admin.users.password', $listedUser),
                                                'data-action-text' => json_encode([
                                                    'entity' => '#'.$listedUser->id.' · '.$listedUser->name,
                                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                            ],
                                        ],
                                    ];
                                @endphp
                                @include('admin.partials.row-action-menu', [
                                    'primary' => [
                                        'type' => 'link',
                                        'label' => 'Редактировать',
                                        'href' => route('admin.users.edit', $listedUser),
                                    ],
                                    'items' => $rowItems,
                                ])
                            @else
                                <span class="muted">Недоступно</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $users->links() }}</div>
        @endif
    </section>

    @include('admin.partials.action-modal-host', [
        'id' => 'user-password-modal',
        'title' => 'Сменить пароль пользователя',
        'description' => 'Новый пароль будет применён сразу.',
        'bodyView' => 'admin.partials.action-forms.user-password',
        'bodyData' => ['idPrefix' => 'user-password'],
    ])
@endsection
