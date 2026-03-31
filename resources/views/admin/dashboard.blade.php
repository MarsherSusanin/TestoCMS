@extends('admin.layout')

@section('title', 'Панель')

@section('content')
    <div class="page-header">
        <div>
            <h1>Панель управления</h1>
            <p>Админ-интерфейс для контента, медиа, публикаций и эксплуатационного контроля.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">Новая страница</a>
            <a href="{{ route('admin.posts.create') }}" class="btn btn-primary">Новый пост</a>
            <a href="{{ route('admin.categories.create') }}" class="btn">Новая категория</a>
        </div>
    </div>

    <section class="cards">
        <div class="card">
            <div class="label">Посты</div>
            <div class="value">{{ $stats['posts'] }}</div>
        </div>
        <div class="card">
            <div class="label">Страницы</div>
            <div class="value">{{ $stats['pages'] }}</div>
        </div>
        <div class="card">
            <div class="label">Категории</div>
            <div class="value">{{ $stats['categories'] }}</div>
        </div>
        <div class="card">
            <div class="label">Файлы</div>
            <div class="value">{{ $stats['assets'] }}</div>
        </div>
    </section>

    <div class="split" style="margin-top:14px;">
        <section class="panel">
            <h2 style="margin-top:0;">Быстрый старт</h2>
            <ol style="margin:0 0 0 18px; padding:0; line-height:1.7;">
                <li>Создайте страницу со slug <span class="mono">home</span> для каждой локали, чтобы управлять <span class="mono">/en</span> и <span class="mono">/ru</span>.</li>
                <li>Создайте категории и назначьте их постам.</li>
                <li>Загрузите изображения в разделе «Файлы» и используйте их публичные URL в контенте.</li>
                <li>Публикуйте или планируйте публикацию с экрана редактирования.</li>
                <li>Создавайте ссылки предпросмотра для черновиков и запланированного контента.</li>
            </ol>
        </section>

        <section class="panel">
            <h2 style="margin-top:0;">Admin API</h2>
            <div class="mono" style="font-size:13px; line-height:1.7; color:#111827;">
                <div>GET /api/admin/v1/posts</div>
                <div>GET /api/admin/v1/pages</div>
                <div>GET /api/admin/v1/categories</div>
                <div>GET /api/admin/v1/assets</div>
                <div>POST /api/admin/v1/llm/generate-post</div>
            </div>
        </section>
    </div>
@endsection
