<form method="POST" action="{{ route('admin.api-keys.store') }}" data-api-key-form>
    @csrf

    <div class="field">
        <label for="api-key-label">Название ключа</label>
        <input id="api-key-label" type="text" name="label" value="{{ old('label') }}" maxlength="120" placeholder="Например: CRM integration" required>
    </div>

    <div class="field">
        <label for="api-key-owner">Владелец ключа</label>
        <select id="api-key-owner" name="owner_user_id" required>
            @foreach($ownerUsers as $ownerUser)
                <option value="{{ $ownerUser->id }}" @selected((int) old('owner_user_id', $defaultOwnerId) === (int) $ownerUser->id)>
                    {{ $ownerUser->name }} ({{ $ownerUser->email }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>API-поверхности</label>
        <div class="grid">
            @foreach($surfaceLabels as $surfaceKey => $surfaceLabel)
                <label class="checkbox">
                    <input
                        type="checkbox"
                        name="surfaces[]"
                        value="{{ $surfaceKey }}"
                        data-api-surface
                        @checked(in_array($surfaceKey, $oldSurfaces, true))
                    >
                    <span>{{ $surfaceLabel }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="field">
        <label class="checkbox">
            <input type="checkbox" name="full_access" value="1" data-api-full-access @checked($oldFullAccess)>
            <span>Full access (`*`)</span>
        </label>
        <small>Full access закрывает проверку scopes, но не обходит RBAC-права владельца.</small>
    </div>

    <div class="field" data-api-abilities-wrap>
        <label>Custom scopes</label>
        <div class="ability-grid">
            @foreach($abilityCatalog as $surfaceKey => $abilities)
                @foreach($abilities as $ability)
                    <div class="ability-item" data-api-ability-item data-surface="{{ $surfaceKey }}">
                        <label class="checkbox">
                            <input
                                type="checkbox"
                                name="abilities[]"
                                value="{{ $ability }}"
                                data-api-ability
                                data-surface="{{ $surfaceKey }}"
                                @checked(in_array($ability, $oldAbilities, true))
                            >
                            <span class="mono">{{ $ability }}</span>
                        </label>
                    </div>
                @endforeach
            @endforeach
        </div>
        <small>Выберите scopes из отмеченных API-поверхностей.</small>
    </div>

    <div class="field">
        <label for="api-key-expires-at">Срок действия (опционально)</label>
        <input id="api-key-expires-at" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}">
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Создать API ключ</button>
    </div>
</form>
