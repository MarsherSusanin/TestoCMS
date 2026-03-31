<form method="POST" action="{{ route('admin.updates.settings') }}">
    @csrf

    <div class="grid cols-2">
        <div class="field">
            <label for="updates-channel">Канал</label>
            <input id="updates-channel" type="text" name="channel" value="{{ old('channel', $settings['channel'] ?? 'stable') }}" placeholder="stable">
        </div>
        <div class="field">
            <label for="updates-mode">Режим</label>
            <select id="updates-mode" name="mode">
                @foreach(['auto', 'filesystem-updater', 'deploy-hook'] as $mode)
                    <option value="{{ $mode }}" @selected(old('mode', $settings['mode'] ?? 'auto') === $mode)>{{ $mode }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="field">
        <label for="updates-server-url">Update server URL</label>
        <input id="updates-server-url" type="text" name="server_url" value="{{ old('server_url', $settings['server_url'] ?? '') }}" placeholder="https://updates.example.com">
    </div>

    <div class="field">
        <label for="updates-public-key">Pinned public key (Ed25519, base64)</label>
        <textarea id="updates-public-key" name="public_key" rows="2" style="min-height:72px;">{{ old('public_key', $settings['public_key'] ?? '') }}</textarea>
    </div>

    <div class="field">
        <label for="updates-hook-url">Deploy hook URL (immutable mode)</label>
        <input id="updates-hook-url" type="text" name="deploy_hook_url" value="{{ old('deploy_hook_url', $settings['deploy_hook_url'] ?? '') }}" placeholder="https://ci.example.com/hooks/testocms-deploy">
    </div>

    <div class="field">
        <label for="updates-hook-token">Deploy hook token</label>
        <input id="updates-hook-token" type="text" name="deploy_hook_token" value="{{ old('deploy_hook_token', $settings['deploy_hook_token'] ?? '') }}" placeholder="token">
    </div>

    <div class="grid cols-2">
        <div class="field">
            <label for="updates-backup-retention">Хранить backup (шт.)</label>
            <input id="updates-backup-retention" type="number" min="1" max="30" name="backup_retention" value="{{ old('backup_retention', $settings['backup_retention'] ?? 5) }}">
        </div>
        <div class="field">
            <label for="updates-http-timeout">HTTP timeout (сек.)</label>
            <input id="updates-http-timeout" type="number" min="3" max="120" name="http_timeout" value="{{ old('http_timeout', $settings['http_timeout'] ?? 15) }}">
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
</form>
