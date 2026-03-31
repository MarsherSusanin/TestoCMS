@extends('admin.layout')

@section('title', 'API доступ')

@push('head')
    @include('admin.api-keys.partials.styles')
@endpush

@section('content')
    @include('admin.partials.action-toolbar', [
        'title' => 'API доступ',
        'description' => 'Управление внешними API-ключами для Admin API и Content API.',
    ])

    @php
        $createdToken = session('api_key_created');
        $oldSurfaces = old('surfaces', ['admin']);
        if (!is_array($oldSurfaces)) { $oldSurfaces = ['admin']; }
        $oldSurfaces = array_values(array_unique(array_map('strval', $oldSurfaces)));
        $oldAbilities = old('abilities', []);
        if (!is_array($oldAbilities)) { $oldAbilities = []; }
        $oldAbilities = array_values(array_unique(array_map('strval', $oldAbilities)));
        $oldFullAccessRaw = old('full_access');
        $oldFullAccess = $oldFullAccessRaw === null ? true : filter_var($oldFullAccessRaw, FILTER_VALIDATE_BOOL);
    @endphp

    @if(is_array($createdToken) && !empty($createdToken['plain_token']))
        @include('admin.api-keys.partials.created-secret', ['createdToken' => $createdToken])
    @endif

    <div class="grid cols-2">
        @include('admin.partials.settings-panel', [
            'title' => 'Создать интеграционный ключ',
            'bodyView' => 'admin.api-keys.partials.create-form',
            'bodyData' => compact('ownerUsers', 'defaultOwnerId', 'surfaceLabels', 'abilityCatalog', 'oldSurfaces', 'oldAbilities', 'oldFullAccess'),
        ])

        @include('admin.partials.settings-panel', [
            'title' => 'О модели доступа',
            'bodyView' => 'admin.api-keys.partials.info-panel',
        ])
    </div>

    <section class="panel">
        <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
            <h2 class="panel-section-title" style="margin:0;">Выпущенные интеграционные ключи</h2>
            <span class="status-pill">{{ $tokens->count() }}</span>
        </div>

        @if($tokens->isEmpty())
            <p class="muted">Ключи ещё не созданы.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Ключ</th>
                    <th>Владелец</th>
                    <th>Поверхности</th>
                    <th>Права</th>
                    <th>Создан</th>
                    <th>Expires</th>
                    <th>Last used</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tokens as $token)
                    <tr>
                        <td class="mono">{{ $token['id'] }}</td>
                        <td>{{ $token['name'] }}</td>
                        <td>{{ $token['owner_label'] }}</td>
                        <td>
                            <div class="token-row-scopes">
                                @foreach($token['surfaces'] as $surface)
                                    <span class="scope-chip">{{ $surface }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <div class="token-row-scopes">
                                @if($token['is_full_access'])
                                    <span class="scope-chip mono">*</span>
                                @else
                                    @foreach($token['abilities'] as $ability)
                                        <span class="scope-chip mono">{{ $ability }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                        <td class="mono">{{ optional($token['created_at'])->format('Y-m-d H:i') }}</td>
                        <td class="mono">{{ optional($token['expires_at'])->format('Y-m-d H:i') ?: '—' }}</td>
                        <td class="mono">{{ optional($token['last_used_at'])->format('Y-m-d H:i') ?: '—' }}</td>
                        <td><span class="status-pill">{{ $token['status'] }}</span></td>
                        <td>
                            @include('admin.partials.row-action-menu', [
                                'items' => [[
                                    'type' => 'form',
                                    'label' => 'Отозвать',
                                    'action' => route('admin.api-keys.destroy', $token['id']),
                                    'method' => 'DELETE',
                                    'confirm' => 'Отозвать этот ключ?',
                                    'danger' => true,
                                ]],
                                'menuLabel' => 'Действия с API ключом',
                            ])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <script src="{{ route('admin.runtime.show', ['runtime' => 'api-keys.js']) }}"></script>
@endsection
