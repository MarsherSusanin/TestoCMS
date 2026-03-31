@extends('admin.layout')

@section('title', 'Шаблоны')

@section('content')
    @include('admin.partials.action-toolbar', [
        'title' => 'Шаблоны',
        'description' => 'Каталог шаблонов контента для страниц и постов.',
        'primaryAction' => [
            'type' => 'link',
            'label' => $entityType === 'page' ? 'Создать страницу' : 'Создать пост',
            'href' => $entityType === 'page' ? route('admin.pages.create') : route('admin.posts.create'),
        ],
    ])

    <section class="panel" style="margin-bottom:12px;">
        <div class="tabs" style="margin-bottom:8px;">
            @if($canReadPage)
                <a class="tab {{ $entityType === 'page' ? 'active' : '' }}" href="{{ route('admin.templates.index', ['entity_type' => 'page']) }}">Шаблоны страниц</a>
            @endif
            @if($canReadPost)
                <a class="tab {{ $entityType === 'post' ? 'active' : '' }}" href="{{ route('admin.templates.index', ['entity_type' => 'post']) }}">Шаблоны постов</a>
            @endif
        </div>

        @unless($templates->isEmpty())
            @include('admin.partials.list-toolbar', [
                'perPageId' => 'templates-per-page',
                'perPage' => $perPage,
                'perPageOptions' => $perPageOptions,
                'summary' => 'Всего шаблонов: '.$templates->total(),
                'hidden' => ['entity_type' => $entityType],
            ])
        @endunless
    </section>

    <section class="panel">
        @if($templates->isEmpty())
            @include('admin.partials.empty-state', [
                'icon' => 'Шб',
                'title' => $entityType === 'page' ? 'Пока нет шаблонов страниц' : 'Пока нет шаблонов постов',
                'description' => $entityType === 'page'
                    ? 'Сохраните удачную страницу как шаблон, чтобы быстро запускать новые лендинги и служебные страницы из готовой структуры.'
                    : 'Сохраните готовый пост как шаблон, чтобы повторно использовать SEO-поля, локали и структуру контента.',
                'hints' => [
                    [
                        'title' => 'Как появятся шаблоны.',
                        'body' => $entityType === 'page'
                            ? 'Откройте любую страницу в редакторе и нажмите «Сохранить как шаблон». После этого она появится в этом каталоге.'
                            : 'Откройте любой пост в редакторе и нажмите «Сохранить как шаблон». После этого заготовка появится в общем каталоге.'
                    ],
                    [
                        'title' => 'Общий доступ.',
                        'body' => 'Шаблоны доступны всем пользователям с правом записи для соответствующего типа контента.'
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'link',
                        'href' => $entityType === 'page' ? route('admin.pages.create') : route('admin.posts.create'),
                        'label' => $entityType === 'page' ? 'Создать страницу' : 'Создать пост',
                        'class' => 'btn-primary',
                    ],
                ],
            ])
        @else
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Обновлено</th>
                    <th>Автор</th>
                    <th style="width: 220px;"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($templates as $template)
                    <tr>
                        <td class="mono">#{{ $template->id }}</td>
                        <td>
                            <div style="font-weight:700;">{{ $template->name }}</div>
                            <div class="muted" style="font-size:12px;">{{ $template->entity_type }}</div>
                        </td>
                        <td>{{ $template->description ?: '—' }}</td>
                        <td>{{ optional($template->updated_at)->diffForHumans() }}</td>
                        <td>{{ $template->creator?->name ?? '—' }}</td>
                        <td>
                            @php
                                $primaryLabel = $template->entity_type === 'page' ? 'Создать страницу' : 'Создать пост';
                                $primaryHref = $template->entity_type === 'page'
                                    ? route('admin.pages.create', ['from_template' => $template->id])
                                    : route('admin.posts.create', ['from_template' => $template->id]);
                                $rowItems = [];
                                if ($canWriteCurrentType) {
                                    $rowItems[] = [
                                        'type' => 'button',
                                        'label' => 'Редактировать метаданные…',
                                        'attrs' => [
                                            'data-action-modal' => 'template-metadata-modal',
                                            'data-action-title' => 'Редактировать шаблон',
                                            'data-action-form-action' => route('admin.templates.update', $template),
                                            'data-action-form-method' => 'PUT',
                                            'data-action-payload' => json_encode([
                                                'name' => $template->name,
                                                'description' => $template->description,
                                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                        ],
                                    ];
                                    $rowItems[] = [
                                        'type' => 'form',
                                        'label' => 'Дублировать',
                                        'action' => route('admin.templates.duplicate', $template),
                                    ];
                                    $rowItems[] = [
                                        'type' => 'form',
                                        'label' => 'Удалить',
                                        'action' => route('admin.templates.destroy', $template),
                                        'method' => 'DELETE',
                                        'confirm' => 'Удалить шаблон?',
                                        'danger' => true,
                                    ];
                                }
                            @endphp
                            @include('admin.partials.row-action-menu', [
                                'primary' => [
                                    'type' => 'link',
                                    'label' => $primaryLabel,
                                    'href' => $primaryHref,
                                ],
                                'items' => $rowItems,
                            ])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $templates->links() }}</div>
        @endif
    </section>

    @include('admin.partials.action-modal-host', [
        'id' => 'template-metadata-modal',
        'title' => 'Редактировать шаблон',
        'description' => 'Измените имя и описание шаблона.',
        'bodyView' => 'admin.partials.action-forms.template-metadata',
        'bodyData' => ['idPrefix' => 'template-metadata'],
    ])
@endsection
