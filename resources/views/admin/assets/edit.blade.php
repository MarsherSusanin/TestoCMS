@extends('admin.layout')

@section('title', 'Редактирование файла')

@section('content')
    <div class="page-header">
        <div>
            <h1>Файл #{{ $asset->id }}</h1>
            <p class="mono">{{ $asset->storage_path }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.assets.index') }}" class="btn">Назад к файлам</a>
            @if($asset->public_url)
                <a href="{{ $asset->public_url }}" target="_blank" rel="noreferrer" class="btn">Открыть файл</a>
            @endif
        </div>
    </div>

    <div class="split">
        <section class="panel">
            <h2 style="margin-top:0;">Метаданные</h2>
            @if($asset->type === 'image' && $asset->public_url)
                <div style="margin-bottom:12px;">
                    <img src="{{ $asset->public_url }}" alt="" style="max-width:100%; border:1px solid #e5e7eb; border-radius:10px;">
                </div>
            @endif

            <form method="POST" action="{{ route('admin.assets.update', $asset) }}">
                @csrf
                @method('PUT')
                <div class="grid cols-2">
                    <div class="field">
                        <label>Название</label>
                        <input type="text" name="title" value="{{ old('title', $asset->title) }}">
                    </div>
                    <div class="field">
                        <label>Alt-текст</label>
                        <input type="text" name="alt" value="{{ old('alt', $asset->alt) }}">
                    </div>
                </div>
                <div class="field">
                    <label>Подпись</label>
                    <textarea name="caption" rows="4">{{ old('caption', $asset->caption) }}</textarea>
                </div>
                <div class="field">
                    <label>Источник / автор</label>
                    <input type="text" name="credits" value="{{ old('credits', $asset->credits) }}">
                </div>
                <button type="submit" class="btn btn-primary">Сохранить метаданные</button>
            </form>
        </section>

        <div>
            <section class="panel">
                <h2 style="margin-top:0;">Технические данные</h2>
                <table>
                    <tbody>
                    <tr><td>ID</td><td class="mono">{{ $asset->id }}</td></tr>
                    <tr><td>Тип</td><td>{{ $asset->type }}</td></tr>
                    <tr><td>Диск</td><td class="mono">{{ $asset->disk }}</td></tr>
                    <tr><td>MIME</td><td class="mono">{{ $asset->mime_type }}</td></tr>
                    <tr><td>Размер</td><td>{{ number_format((int) $asset->size) }} B</td></tr>
                    <tr><td>Размеры</td><td>{{ $asset->width && $asset->height ? $asset->width.'×'.$asset->height : '—' }}</td></tr>
                    <tr><td>Создан</td><td>{{ optional($asset->created_at)->toDayDateTimeString() }}</td></tr>
                    <tr><td>Обновлён</td><td>{{ optional($asset->updated_at)->toDayDateTimeString() }}</td></tr>
                    </tbody>
                </table>
            </section>

            <section class="panel">
                <h2 style="margin-top:0; color:#b42318;">Опасная зона</h2>
                <form method="POST" action="{{ route('admin.assets.destroy', $asset) }}" data-confirm="Удалить этот файл и запись?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Удалить файл</button>
                </form>
            </section>
        </div>
    </div>
@endsection
