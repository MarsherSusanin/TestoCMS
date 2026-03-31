@extends('admin.layout')

@section('title', 'Страницы')

@section('content')
    @php
        $defaultLocale = strtolower((string) config('cms.default_locale', 'en'));
        $canCreatePages = auth()->user()?->can('create', \App\Models\Page::class) ?? false;
    @endphp
    @include('admin.partials.action-toolbar', [
        'title' => 'Страницы',
        'description' => 'Локализованные страницы с блочным контентом и SSR-рендерингом.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Создать страницу',
            'href' => route('admin.pages.create'),
            'class' => 'btn-primary',
        ],
        'secondaryActions' => [
            [
                'type' => 'button',
                'label' => 'Создать из шаблона',
                'attrs' => ['data-modal-open' => 'page-template-create-modal'],
            ],
        ],
    ])

    <section class="panel">
        @if($pages->isEmpty())
            @include('admin.partials.empty-state', [
                'icon' => 'Ст',
                'title' => 'Пока нет страниц',
                'description' => 'Начните со стартовой страницы сайта, лендинга или служебной страницы. Когда появятся записи, здесь же станут доступны массовые действия, пагинация и быстрый обзор локалей.',
                'hints' => [
                    [
                        'title' => 'Главная страница.',
                        'body' => 'Используйте slug <span class="mono">home</span>, чтобы корень сайта открывался по адресу <span class="mono">/'.$defaultLocale.'</span>.'
                    ],
                    [
                        'title' => 'Шаблоны.',
                        'body' => $templates->isEmpty()
                            ? 'Сначала создайте и сохраните хотя бы одну страницу как шаблон, после этого сможете запускать новые страницы из готовой структуры.'
                            : 'В каталоге уже доступно '.$templates->count().' шабл. Начать можно сразу из готовой заготовки.'
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'link',
                        'href' => route('admin.pages.create'),
                        'label' => 'Создать страницу',
                        'class' => 'btn-primary',
                    ],
                    [
                        'type' => 'button',
                        'label' => 'Создать из шаблона',
                        'extra' => 'data-modal-open="page-template-create-modal"',
                        'disabled' => $templates->isEmpty(),
                    ],
                ],
            ])
        @else
            <div class="list-toolbar">
                @include('admin.partials.list-toolbar', [
                    'perPageId' => 'pages-per-page',
                    'perPage' => $perPage,
                    'perPageOptions' => $perPageOptions,
                    'summary' => 'Всего страниц: '.$pages->total(),
                ])

                <form method="POST" action="{{ route('admin.pages.bulk') }}" class="bulk-form" data-bulk-form data-bulk-empty-message="Выберите хотя бы одну строку." data-bulk-delete-confirm="Удалить выбранные страницы?">
                    @csrf
                    @include('admin.partials.bulk-bar', [
                        'actions' => [
                            ['value' => 'unpublish', 'label' => 'Снять с публикации'],
                            ['value' => 'duplicate', 'label' => 'Дублировать'],
                            ['value' => 'delete', 'label' => 'Удалить'],
                        ],
                    ])

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th style="width:34px;"><input type="checkbox" data-check-all></th>
                                <th>ID</th>
                                <th>Статус</th>
                                <th>Тип</th>
                                <th>Локали</th>
                                <th>Обновлено</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($pages as $page)
                                @php
                                    $translations = $page->translations->keyBy(fn ($t) => strtolower($t->locale));
                                @endphp
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="{{ $page->id }}" data-row-check></td>
                                    <td class="mono">#{{ $page->id }}</td>
                                    <td>
                                        <span class="status-pill status-{{ $page->status }}">{{ $page->status }}</span>
                                    </td>
                                    <td>{{ $page->page_type }}</td>
                                    <td>
                                        @foreach($locales as $locale)
                                            @php $t = $translations[$locale] ?? null; @endphp
                                            @if($t)
                                                <div>
                                                    <strong>{{ strtoupper($locale) }}</strong>:
                                                    {{ $t->title }}
                                                    <span class="muted mono">/{{ $locale }}/{{ $t->slug }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>{{ optional($page->updated_at)->diffForHumans() }}</td>
                                    <td>
                                        @php
                                            $defaultTranslation = $translations[$defaultLocale] ?? null;
                                            $rowItems = [];
                                            $openUrl = $defaultTranslation && $page->status === 'published'
                                                ? url('/'.$defaultLocale.'/'.$defaultTranslation->slug)
                                                : null;
                                            if ($openUrl) {
                                                $rowItems[] = [
                                                    'type' => 'link',
                                                    'label' => 'Открыть',
                                                    'href' => $openUrl,
                                                    'target' => '_blank',
                                                    'rel' => 'noreferrer',
                                                ];
                                            }
                                            if (auth()->user()?->can('publish', $page)) {
                                                $rowItems[] = [
                                                    'type' => 'form',
                                                    'label' => $page->status === 'published' ? 'Снять с публикации' : 'Опубликовать',
                                                    'action' => $page->status === 'published'
                                                        ? route('admin.pages.unpublish', $page)
                                                        : route('admin.pages.publish', $page),
                                                ];
                                                $rowItems[] = [
                                                    'type' => 'button',
                                                    'label' => 'Запланировать…',
                                                    'attrs' => [
                                                        'data-action-modal' => 'page-schedule-modal',
                                                        'data-action-title' => 'Запланировать публикацию страницы',
                                                        'data-action-form-action' => route('admin.pages.schedule', $page),
                                                        'data-action-payload' => json_encode([
                                                            'action' => $page->status === 'published' ? 'unpublish' : 'publish',
                                                            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                        'data-action-text' => json_encode([
                                                            'entity' => '#'.$page->id.' · '.($defaultTranslation?->title ?: 'Страница'),
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                    ],
                                                ];
                                            }
                                            if (auth()->user()?->can('view', $page)) {
                                                $rowItems[] = [
                                                    'type' => 'button',
                                                    'label' => 'Preview link…',
                                                    'attrs' => [
                                                        'data-action-modal' => 'page-preview-link-modal',
                                                        'data-action-title' => 'Сгенерировать preview link',
                                                        'data-action-form-action' => route('admin.pages.preview-token', $page),
                                                        'data-action-payload' => json_encode([
                                                            'locale' => $defaultLocale,
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                        'data-action-text' => json_encode([
                                                            'entity' => '#'.$page->id.' · '.($defaultTranslation?->title ?: 'Страница'),
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                    ],
                                                ];
                                            }
                                            if ($canCreatePages) {
                                                $rowItems[] = [
                                                    'type' => 'form',
                                                    'label' => 'Дублировать',
                                                    'action' => route('admin.pages.duplicate', $page),
                                                ];
                                            }
                                            if (auth()->user()?->can('delete', $page)) {
                                                $rowItems[] = [
                                                    'type' => 'form',
                                                    'label' => 'Удалить',
                                                    'action' => route('admin.pages.destroy', $page),
                                                    'method' => 'DELETE',
                                                    'confirm' => 'Удалить страницу?',
                                                    'danger' => true,
                                                ];
                                            }
                                        @endphp
                                        @include('admin.partials.row-action-menu', [
                                            'primary' => [
                                                'type' => 'link',
                                                'label' => 'Редактировать',
                                                'href' => route('admin.pages.edit', $page),
                                            ],
                                            'items' => $rowItems,
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            <div class="pagination">{{ $pages->links() }}</div>
        @endif
    </section>

    <div class="template-modal" data-modal="page-template-create-modal" aria-hidden="true">
        <div class="template-modal-card">
            <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
                <h3 style="margin:0;">Создать страницу из шаблона</h3>
                <button type="button" class="btn btn-small" data-modal-close>Закрыть</button>
            </div>
            <form method="GET" action="{{ route('admin.pages.create') }}" class="grid" style="gap:10px;">
                <div class="field" style="margin:0;">
                    <label for="template-page-select">Шаблон</label>
                    <select id="template-page-select" name="from_template" required>
                        <option value="">Выберите шаблон…</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}">#{{ $template->id }} · {{ $template->name }}</option>
                        @endforeach
                    </select>
                    <small>Новая страница будет создана как черновик с уникальными slug.</small>
                </div>
                <div class="inline" style="justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" @disabled($templates->isEmpty())>Создать из шаблона</button>
                </div>
            </form>
        </div>
    </div>

    @include('admin.partials.action-modal-host', [
        'id' => 'page-schedule-modal',
        'title' => 'Запланировать публикацию страницы',
        'description' => 'Выберите действие и дату выполнения.',
        'bodyView' => 'admin.partials.action-forms.schedule',
        'bodyData' => ['idPrefix' => 'page-schedule'],
    ])

    @include('admin.partials.action-modal-host', [
        'id' => 'page-preview-link-modal',
        'title' => 'Сгенерировать preview link',
        'description' => 'Preview-ссылка действует 24 часа.',
        'open' => session('action_modal') === 'page-preview-link-modal',
        'bodyView' => 'admin.partials.action-forms.preview-link',
        'bodyData' => [
            'idPrefix' => 'page-preview',
            'modalId' => 'page-preview-link-modal',
            'locales' => $locales,
            'defaultLocale' => $defaultLocale,
            'previewLink' => session('action_modal') === 'page-preview-link-modal' ? session('preview_link') : null,
        ],
    ])
@endsection
