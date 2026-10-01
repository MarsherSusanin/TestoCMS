@extends('admin.layout')
@section('title', 'Просмотр контента')
@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $entityType === 'page' ? 'Просмотр страницы' : 'Просмотр поста' }} #{{ $entity->id }}</h1>
            <p>Только чтение. Для изменений нужны права редактирования; для опубликованного контента — также право публикации.</p>
        </div>
        <a class="btn" href="{{ route('admin.'.$entityType.'s.index') }}">Назад к списку</a>
    </div>
    <div class="panel">
        <p>Статус: <strong>{{ $entity->status }}</strong></p>
        <form method="POST" action="{{ route('admin.'.$entityType.'s.preview-token', $entity) }}">
            @csrf
            <label for="readonly-preview-locale">Локаль предпросмотра</label>
            <select id="readonly-preview-locale" name="locale">
                @foreach($entity->translations as $translation)
                    <option value="{{ $translation->locale }}">{{ strtoupper($translation->locale) }}</option>
                @endforeach
            </select>
            <button class="btn" type="submit">Сгенерировать ссылку предпросмотра (24ч)</button>
        </form>
    </div>
    @foreach($entity->translations as $translation)
        <section class="panel" data-readonly-translation="{{ $translation->locale }}">
            <h2>{{ strtoupper($translation->locale) }} — {{ $translation->title }}</h2>
            <p>Slug: <span class="mono">{{ $translation->slug }}</span></p>
            <p>SEO title: {{ $translation->meta_title ?: '—' }}</p>
            <p>SEO description: {{ $translation->meta_description ?: '—' }}</p>
            <label>Содержимое</label>
            <textarea readonly rows="12" style="width:100%;">{{ $entityType === 'page' ? json_encode($translation->content_blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (($translation->content_format ?? 'html') === 'markdown' ? $translation->content_markdown : $translation->content_html) }}</textarea>
        </section>
    @endforeach
@endsection
