@extends('admin.layout')

@section('title', 'Файлы')

@section('content')
    @include('admin.partials.action-toolbar', [
        'title' => 'Файлы',
        'description' => 'Загрузка файлов в локальное хранилище и управление метаданными медиа.',
    ])

    <section class="panel" id="asset-upload-panel">
        <h2 style="margin-top:0;">Загрузка файла</h2>
        <form method="POST" action="{{ route('admin.assets.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid cols-2">
                <div class="field">
                    <label for="asset-file">Файл</label>
                    <input id="asset-file" type="file" name="file">
                    <small>До 50 МБ. Если файл не выбран, укажите путь вручную ниже.</small>
                </div>
                <div class="field">
                    <label for="asset-type">Тип (необязательно)</label>
                    <select id="asset-type" name="type">
                        <option value="">Определить автоматически</option>
                        <option value="image">image</option>
                        <option value="video">video</option>
                        <option value="document">document</option>
                    </select>
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="asset-disk">Диск</label>
                    <input id="asset-disk" type="text" name="disk" value="{{ old('disk', 'public') }}">
                </div>
                <div class="field">
                    <label for="asset-storage-path">Путь в хранилище (вручную)</label>
                    <input id="asset-storage-path" type="text" name="storage_path" value="{{ old('storage_path') }}">
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="asset-title">Название</label>
                    <input id="asset-title" type="text" name="title" value="{{ old('title') }}">
                </div>
                <div class="field">
                    <label for="asset-alt">Alt-текст</label>
                    <input id="asset-alt" type="text" name="alt" value="{{ old('alt') }}">
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="asset-public-url">Публичный URL (вручную)</label>
                    <input id="asset-public-url" type="text" name="public_url" value="{{ old('public_url') }}">
                </div>
                <div class="field">
                    <label for="asset-mime">MIME-тип (вручную)</label>
                    <input id="asset-mime" type="text" name="mime_type" value="{{ old('mime_type') }}">
                </div>
            </div>

            <div class="field">
                <label for="asset-caption">Подпись</label>
                <textarea id="asset-caption" name="caption" rows="3">{{ old('caption') }}</textarea>
            </div>

            <div class="field">
                <label for="asset-credits">Источник / автор</label>
                <input id="asset-credits" type="text" name="credits" value="{{ old('credits') }}">
            </div>

            <button type="submit" class="btn btn-primary">Загрузить / сохранить файл</button>
        </form>
    </section>

    <section class="panel">
        <h2 style="margin-top:0;">Библиотека файлов</h2>
        @if($assets->isEmpty())
            @include('admin.partials.empty-state', [
                'icon' => 'Фл',
                'title' => 'Библиотека пока пуста',
                'description' => 'Загрузите первое изображение, документ или видео, чтобы использовать их в постах, страницах и шаблонах.',
                'hints' => [
                    [
                        'title' => 'Локальное хранилище.',
                        'body' => 'Файлы сохраняются в CMS и затем становятся доступны через media picker во всех редакторах.'
                    ],
                    [
                        'title' => 'Метаданные.',
                        'body' => 'Сразу задавайте title, alt-текст и подпись, чтобы потом не возвращаться к SEO-правкам в контенте.'
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'link',
                        'href' => '#asset-upload-panel',
                        'label' => 'Загрузить первый файл',
                        'class' => 'btn-primary',
                    ],
                ],
            ])
        @else
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Превью</th>
                    <th>Тип</th>
                    <th>Путь / URL</th>
                    <th>Метаданные</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($assets as $asset)
                    <tr>
                        <td class="mono">#{{ $asset->id }}</td>
                        <td style="width:120px;">
                            @if($asset->type === 'image' && $asset->public_url)
                                <img src="{{ $asset->public_url }}" alt="" style="max-width:100px; max-height:60px; border-radius:8px; border:1px solid #e5e7eb;">
                            @else
                                <span class="tab">{{ $asset->type }}</span>
                            @endif
                        </td>
                        <td>{{ $asset->type }}</td>
                        <td>
                            <div class="mono" style="font-size:12px; word-break:break-all;">{{ $asset->storage_path }}</div>
                            @if($asset->public_url)
                                <div><a href="{{ $asset->public_url }}" target="_blank" rel="noreferrer">Открыть</a></div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $asset->title ?: '—' }}</div>
                            <div class="muted" style="font-size:12px;">{{ $asset->mime_type }} · {{ number_format((int) $asset->size) }} B</div>
                        </td>
                        <td>
                            @php
                                $rowItems = [];
                                if ($asset->public_url) {
                                    $rowItems[] = [
                                        'type' => 'link',
                                        'label' => 'Открыть',
                                        'href' => $asset->public_url,
                                        'target' => '_blank',
                                        'rel' => 'noreferrer',
                                    ];
                                    $rowItems[] = [
                                        'type' => 'button',
                                        'label' => 'Скопировать URL',
                                        'attrs' => ['data-copy-text' => $asset->public_url],
                                    ];
                                }
                                if (auth()->user()?->can('delete', $asset)) {
                                    $rowItems[] = [
                                        'type' => 'form',
                                        'label' => 'Удалить',
                                        'action' => route('admin.assets.destroy', $asset),
                                        'method' => 'DELETE',
                                        'confirm' => 'Удалить этот файл и запись?',
                                        'danger' => true,
                                    ];
                                }
                            @endphp
                            @include('admin.partials.row-action-menu', [
                                'primary' => [
                                    'type' => 'link',
                                    'label' => 'Редактировать',
                                    'href' => route('admin.assets.edit', $asset),
                                ],
                                'items' => $rowItems,
                            ])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $assets->links() }}</div>
        @endif
    </section>
@endsection
