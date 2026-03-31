<section class="panel">
    <div class="inline api-token-header">
        <h2 class="panel-heading">Выпущенные интеграционные ключи</h2>
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
                        <form method="POST" action="{{ route('admin.api-keys.destroy', $token['id']) }}" data-confirm="Отозвать этот ключ?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-small btn-danger">Отозвать</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>
