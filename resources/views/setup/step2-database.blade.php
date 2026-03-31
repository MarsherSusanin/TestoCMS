@extends('setup.layout', ['title' => 'База данных — Установка', 'currentStep' => 2])

@section('content')
    @php
        $defaultConnection = old('db_connection', $auto['has_mysql'] ? 'mysql' : ($auto['has_pgsql'] ? 'pgsql' : 'mysql'));
        $defaultDbConfig = (array) config('database.connections.'.$defaultConnection, []);
        $defaultHost = old('db_host', (string) (
            $defaultConnection === 'pgsql'
                ? (($defaultDbConfig['host'] ?? null) && ! in_array((string) $defaultDbConfig['host'], ['127.0.0.1', 'localhost'], true)
                    ? $defaultDbConfig['host']
                    : 'db')
                : (($defaultDbConfig['host'] ?? null) ?: 'localhost')
        ));
        $defaultPort = old('db_port', $defaultConnection === 'pgsql' ? '5432' : '3306');
        $defaultDatabase = old('db_database', (string) (($defaultDbConfig['database'] ?? null) ?: ($defaultConnection === 'pgsql' ? 'testocms' : '')));
        $defaultUsername = old('db_username', (string) (($defaultDbConfig['username'] ?? null) ?: ($defaultConnection === 'pgsql' ? 'testocms' : '')));
    @endphp

    <h2>Подключение к базе данных</h2>
    <p style="color:#6b7280; margin-bottom:16px;">Укажите параметры подключения к БД. Базу нужно создать заранее.</p>

    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('setup.step2.save') }}" id="db-form">
        @csrf

        <div class="form-group">
            <label for="db_connection">Тип базы данных</label>
            <select name="db_connection" id="db_connection">
                @if($auto['has_mysql'])
                    <option value="mysql" {{ $defaultConnection === 'mysql' ? 'selected' : '' }}>MySQL / MariaDB</option>
                @endif
                @if($auto['has_pgsql'])
                    <option value="pgsql" {{ $defaultConnection === 'pgsql' ? 'selected' : '' }}>PostgreSQL</option>
                @endif
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="db_host">Хост</label>
                <input type="text" name="db_host" id="db_host" value="{{ $defaultHost }}" required>
            </div>
            <div class="form-group">
                <label for="db_port">Порт</label>
                <input type="number" name="db_port" id="db_port" value="{{ $defaultPort }}" required>
            </div>
        </div>

        <div class="form-group">
            <label for="db_database">Имя базы данных</label>
            <input type="text" name="db_database" id="db_database" value="{{ $defaultDatabase }}" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="db_username">Пользователь</label>
                <input type="text" name="db_username" id="db_username" value="{{ $defaultUsername }}" required>
            </div>
            <div class="form-group">
                <label for="db_password">Пароль</label>
                <input type="password" name="db_password" id="db_password" value="{{ old('db_password', '') }}">
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <button type="button" class="btn btn-secondary" id="test-db-btn" onclick="testConnection()">
                Проверить подключение
            </button>
            <span id="test-result" style="margin-left:8px; font-size:14px;"></span>
        </div>

        <div class="actions">
            <a href="{{ route('setup.step1') }}" class="btn btn-secondary">← Назад</a>
            <button type="submit" class="btn btn-primary">Далее →</button>
        </div>
    </form>

    <script>
        document.getElementById('db_connection').addEventListener('change', function() {
            document.getElementById('db_port').value = this.value === 'pgsql' ? '5432' : '3306';
        });

        function testConnection() {
            var btn = document.getElementById('test-db-btn');
            var result = document.getElementById('test-result');
            btn.disabled = true;
            result.innerHTML = '<span class="spinner"></span> Проверка...';

            var form = document.getElementById('db-form');
            var formData = new FormData(form);
            formData.append('_method', 'POST');

            fetch('{{ route("setup.test-db") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    db_connection: formData.get('db_connection'),
                    db_host: formData.get('db_host'),
                    db_port: formData.get('db_port'),
                    db_database: formData.get('db_database'),
                    db_username: formData.get('db_username'),
                    db_password: formData.get('db_password'),
                }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (data.ok) {
                    result.innerHTML = '<span style="color:#059669;">✓ Подключение установлено</span>';
                } else {
                    result.innerHTML = '<span style="color:#dc2626;">✗ ' + (data.error || 'Ошибка') + '</span>';
                }
            })
            .catch(function(e) {
                btn.disabled = false;
                result.innerHTML = '<span style="color:#dc2626;">✗ Ошибка сети</span>';
            });
        }
    </script>
@endsection
