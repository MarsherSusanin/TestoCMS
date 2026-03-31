@extends('admin.layout')

@section('title', 'Категории')

@section('content')
    @php
        $defaultLocale = strtolower((string) config('cms.default_locale', 'en'));
    @endphp
    @include('admin.partials.action-toolbar', [
        'title' => 'Категории',
        'description' => 'Локализованные страницы категорий и группировка постов блога.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Создать категорию',
            'href' => route('admin.categories.create'),
            'class' => 'btn-primary',
        ],
    ])

    <section class="panel">
        @if($categories->isEmpty())
            @include('admin.partials.empty-state', [
                'icon' => 'Кт',
                'title' => 'Пока нет категорий',
                'description' => 'Создайте категории, чтобы группировать посты, собирать архивы и управлять навигацией блога более аккуратно.',
                'hints' => [
                    [
                        'title' => 'Структура блога.',
                        'body' => 'Категории помогают строить разделы, фильтровать публикации и формировать SEO-страницы архивов.'
                    ],
                    [
                        'title' => 'Локали.',
                        'body' => 'Для каждой категории можно задать локализованные title и slug, чтобы адреса корректно работали в каждой языковой версии.'
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'link',
                        'href' => route('admin.categories.create'),
                        'label' => 'Создать категорию',
                        'class' => 'btn-primary',
                    ],
                ],
            ])
        @else
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Активность</th>
                    <th>Родитель</th>
                    <th>Локали</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($categories as $category)
                    @php $translations = $category->translations->keyBy(fn ($t) => strtolower($t->locale)); @endphp
                    <tr>
                        <td class="mono">#{{ $category->id }}</td>
                        <td>{!! $category->is_active ? '<span class="status-pill status-published">активна</span>' : '<span class="status-pill status-archived">неактивна</span>' !!}</td>
                        <td>{{ $category->parent_id ? ('#'.$category->parent_id) : '—' }}</td>
                        <td>
                            @foreach($locales as $locale)
                                @php $t = $translations[$locale] ?? null; @endphp
                                @if($t)
                                    <div>
                                        <strong>{{ strtoupper($locale) }}</strong>: {{ $t->title }}
                                        <span class="muted mono">/{{ $locale }}/{{ config('cms.category_url_prefix', 'category') }}/{{ $t->slug }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </td>
                        <td>
                            @php
                                $defaultTranslation = $translations[$defaultLocale] ?? null;
                                $rowItems = [];
                                if ($defaultTranslation && $category->is_active) {
                                    $rowItems[] = [
                                        'type' => 'link',
                                        'label' => 'Открыть',
                                        'href' => url('/'.$defaultLocale.'/'.config('cms.category_url_prefix', 'category').'/'.$defaultTranslation->slug),
                                        'target' => '_blank',
                                        'rel' => 'noreferrer',
                                    ];
                                }
                                if (auth()->user()?->can('delete', $category)) {
                                    $rowItems[] = [
                                        'type' => 'form',
                                        'label' => 'Удалить',
                                        'action' => route('admin.categories.destroy', $category),
                                        'method' => 'DELETE',
                                        'confirm' => 'Удалить категорию?',
                                        'danger' => true,
                                    ];
                                }
                            @endphp
                            @include('admin.partials.row-action-menu', [
                                'primary' => [
                                    'type' => 'link',
                                    'label' => 'Редактировать',
                                    'href' => route('admin.categories.edit', $category),
                                ],
                                'items' => $rowItems,
                            ])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $categories->links() }}</div>
        @endif
    </section>
@endsection
