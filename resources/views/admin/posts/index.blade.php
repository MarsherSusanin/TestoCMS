@extends('admin.layout')

@section('title', 'Посты')

@section('content')
    @php
        $defaultLocale = strtolower((string) config('cms.default_locale', 'en'));
        $canCreatePosts = auth()->user()?->can('create', \App\Models\Post::class) ?? false;
    @endphp
    @include('admin.partials.action-toolbar', [
        'title' => 'Посты',
        'description' => 'Посты блога с локализованным контентом и категориями.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Создать пост',
            'href' => route('admin.posts.create'),
            'class' => 'btn-primary',
        ],
        'secondaryActions' => [
            [
                'type' => 'button',
                'label' => 'Создать из шаблона',
                'attrs' => ['data-modal-open' => 'post-template-create-modal'],
            ],
        ],
    ])

    <section class="panel">
        @if($posts->isEmpty())
            @include('admin.partials.empty-state', [
                'icon' => 'Пс',
                'title' => 'Пока нет постов',
                'description' => 'Создайте первый материал для блога, импортируйте Markdown или начните с готового шаблона. Когда посты появятся, здесь же будут доступны массовые действия, пагинация и быстрый обзор категорий.',
                'hints' => [
                    [
                        'title' => 'Markdown и визуальный редактор.',
                        'body' => 'Пост можно собрать в визуальном HTML-редакторе или переключить локаль в режим Markdown с импортом <span class="mono">.md</span>.'
                    ],
                    [
                        'title' => 'Шаблоны.',
                        'body' => $templates->isEmpty()
                            ? 'Сохраните любой готовый пост как шаблон, чтобы потом быстро запускать типовые публикации.'
                            : 'В каталоге уже доступно '.$templates->count().' шабл. Можно стартовать с заготовки сразу.'
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'link',
                        'href' => route('admin.posts.create'),
                        'label' => 'Создать пост',
                        'class' => 'btn-primary',
                    ],
                    [
                        'type' => 'button',
                        'label' => 'Создать из шаблона',
                        'extra' => 'data-modal-open="post-template-create-modal"',
                        'disabled' => $templates->isEmpty(),
                    ],
                ],
            ])
        @else
            <div class="list-toolbar">
                @include('admin.partials.list-toolbar', [
                    'perPageId' => 'posts-per-page',
                    'perPage' => $perPage,
                    'perPageOptions' => $perPageOptions,
                    'summary' => 'Всего постов: '.$posts->total(),
                ])

                <form method="POST" action="{{ route('admin.posts.bulk') }}" class="bulk-form" data-bulk-form data-bulk-empty-message="Выберите хотя бы одну строку." data-bulk-delete-confirm="Удалить выбранные посты?">
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
                                <th>Заголовки</th>
                                <th>Категории</th>
                                <th>Опубликован</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($posts as $post)
                                @php $translations = $post->translations->keyBy(fn ($t) => strtolower($t->locale)); @endphp
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="{{ $post->id }}" data-row-check></td>
                                    <td class="mono">#{{ $post->id }}</td>
                                    <td><span class="status-pill status-{{ $post->status }}">{{ $post->status }}</span></td>
                                    <td>
                                        @foreach($locales as $locale)
                                            @php $t = $translations[$locale] ?? null; @endphp
                                            @if($t)
                                                <div>
                                                    <strong>{{ strtoupper($locale) }}</strong>: {{ $t->title }}
                                                    <span class="muted mono">/{{ $locale }}/{{ config('cms.post_url_prefix', 'blog') }}/{{ $t->slug }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>
                                        @forelse($post->categories as $category)
                                            @php $ct = $category->translations->first(); @endphp
                                            <span class="tab">{{ $ct?->title ?? ('#'.$category->id) }}</span>
                                        @empty
                                            <span class="muted">Без категорий</span>
                                        @endforelse
                                    </td>
                                    <td>{{ $post->published_at?->toDayDateTimeString() ?? '—' }}</td>
                                    <td>
                                        @php
                                            $defaultT = $translations[$defaultLocale] ?? null;
                                            $rowItems = [];
                                            $openUrl = $defaultT && $post->status === 'published'
                                                ? url('/'.$defaultLocale.'/'.config('cms.post_url_prefix', 'blog').'/'.$defaultT->slug)
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
                                            if (auth()->user()?->can('publish', $post)) {
                                                $rowItems[] = [
                                                    'type' => 'form',
                                                    'label' => $post->status === 'published' ? 'Снять с публикации' : 'Опубликовать',
                                                    'action' => $post->status === 'published'
                                                        ? route('admin.posts.unpublish', $post)
                                                        : route('admin.posts.publish', $post),
                                                ];
                                                $rowItems[] = [
                                                    'type' => 'button',
                                                    'label' => 'Запланировать…',
                                                    'attrs' => [
                                                        'data-action-modal' => 'post-schedule-modal',
                                                        'data-action-title' => 'Запланировать публикацию поста',
                                                        'data-action-form-action' => route('admin.posts.schedule', $post),
                                                        'data-action-payload' => json_encode([
                                                            'action' => $post->status === 'published' ? 'unpublish' : 'publish',
                                                            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                        'data-action-text' => json_encode([
                                                            'entity' => '#'.$post->id.' · '.($defaultT?->title ?: 'Пост'),
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                    ],
                                                ];
                                            }
                                            if (auth()->user()?->can('view', $post)) {
                                                $rowItems[] = [
                                                    'type' => 'button',
                                                    'label' => 'Preview link…',
                                                    'attrs' => [
                                                        'data-action-modal' => 'post-preview-link-modal',
                                                        'data-action-title' => 'Сгенерировать preview link',
                                                        'data-action-form-action' => route('admin.posts.preview-token', $post),
                                                        'data-action-payload' => json_encode([
                                                            'locale' => $defaultLocale,
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                        'data-action-text' => json_encode([
                                                            'entity' => '#'.$post->id.' · '.($defaultT?->title ?: 'Пост'),
                                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                    ],
                                                ];
                                            }
                                            if ($canCreatePosts) {
                                                $rowItems[] = [
                                                    'type' => 'form',
                                                    'label' => 'Дублировать',
                                                    'action' => route('admin.posts.duplicate', $post),
                                                ];
                                            }
                                            if (auth()->user()?->can('delete', $post)) {
                                                $rowItems[] = [
                                                    'type' => 'form',
                                                    'label' => 'Удалить',
                                                    'action' => route('admin.posts.destroy', $post),
                                                    'method' => 'DELETE',
                                                    'confirm' => 'Удалить пост?',
                                                    'danger' => true,
                                                ];
                                            }
                                        @endphp
                                        @include('admin.partials.row-action-menu', [
                                            'primary' => [
                                                'type' => 'link',
                                                'label' => 'Редактировать',
                                                'href' => route('admin.posts.edit', $post),
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

            <div class="pagination">{{ $posts->links() }}</div>
        @endif
    </section>

    <div class="template-modal" data-modal="post-template-create-modal" aria-hidden="true">
        <div class="template-modal-card">
            <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
                <h3 style="margin:0;">Создать пост из шаблона</h3>
                <button type="button" class="btn btn-small" data-modal-close>Закрыть</button>
            </div>
            <form method="GET" action="{{ route('admin.posts.create') }}" class="grid" style="gap:10px;">
                <div class="field" style="margin:0;">
                    <label for="template-post-select">Шаблон</label>
                    <select id="template-post-select" name="from_template" required>
                        <option value="">Выберите шаблон…</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}">#{{ $template->id }} · {{ $template->name }}</option>
                        @endforeach
                    </select>
                    <small>Новый пост будет создан как черновик с уникальными slug.</small>
                </div>
                <div class="inline" style="justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" @disabled($templates->isEmpty())>Создать из шаблона</button>
                </div>
            </form>
        </div>
    </div>

    @include('admin.partials.action-modal-host', [
        'id' => 'post-schedule-modal',
        'title' => 'Запланировать публикацию поста',
        'description' => 'Выберите действие и дату выполнения.',
        'bodyView' => 'admin.partials.action-forms.schedule',
        'bodyData' => ['idPrefix' => 'post-schedule'],
    ])

    @include('admin.partials.action-modal-host', [
        'id' => 'post-preview-link-modal',
        'title' => 'Сгенерировать preview link',
        'description' => 'Preview-ссылка действует 24 часа.',
        'open' => session('action_modal') === 'post-preview-link-modal',
        'bodyView' => 'admin.partials.action-forms.preview-link',
        'bodyData' => [
            'idPrefix' => 'post-preview',
            'modalId' => 'post-preview-link-modal',
            'locales' => $locales,
            'defaultLocale' => $defaultLocale,
            'previewLink' => session('action_modal') === 'post-preview-link-modal' ? session('preview_link') : null,
        ],
    ])
@endsection
