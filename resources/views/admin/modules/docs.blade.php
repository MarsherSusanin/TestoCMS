@extends('admin.layout')

@section('title', 'Документация модулей')

@push('head')
<style>
    .docs-shell {
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(0, 1fr) 320px;
    }
    .docs-content .markdown-doc {
        color: var(--text);
        line-height: 1.7;
        font-size: .98rem;
    }
    .docs-content .markdown-doc > *:first-child {
        margin-top: 0;
    }
    .docs-content .markdown-doc > *:last-child {
        margin-bottom: 0;
    }
    .docs-content .markdown-doc h1,
    .docs-content .markdown-doc h2,
    .docs-content .markdown-doc h3,
    .docs-content .markdown-doc h4,
    .docs-content .markdown-doc h5,
    .docs-content .markdown-doc h6 {
        margin: 1.6rem 0 .7rem;
        line-height: 1.2;
        color: var(--text);
    }
    .docs-content .markdown-doc h1 {
        font-size: 1.9rem;
        letter-spacing: -.03em;
    }
    .docs-content .markdown-doc h2 {
        font-size: 1.45rem;
        padding-top: .3rem;
        border-top: 1px solid var(--border);
    }
    .docs-content .markdown-doc h3 {
        font-size: 1.15rem;
    }
    .docs-content .markdown-doc p,
    .docs-content .markdown-doc ul,
    .docs-content .markdown-doc ol,
    .docs-content .markdown-doc pre,
    .docs-content .markdown-doc table,
    .docs-content .markdown-doc blockquote {
        margin: .9rem 0;
    }
    .docs-content .markdown-doc ul,
    .docs-content .markdown-doc ol {
        padding-left: 1.3rem;
    }
    .docs-content .markdown-doc li + li {
        margin-top: .3rem;
    }
    .docs-content .markdown-doc a {
        color: var(--brand);
        text-decoration: none;
    }
    .docs-content .markdown-doc a:hover {
        text-decoration: underline;
    }
    .docs-content .markdown-doc hr {
        border: 0;
        border-top: 1px solid var(--border);
        margin: 1.3rem 0;
    }
    .docs-content .markdown-doc blockquote {
        border-left: 4px solid var(--brand);
        padding: .2rem 0 .2rem .95rem;
        color: var(--muted);
        background: color-mix(in srgb, var(--brand) 7%, white);
        border-radius: 0 14px 14px 0;
    }
    .docs-content .markdown-doc code {
        font-family: "JetBrains Mono", "SFMono-Regular", Consolas, monospace;
        font-size: .92em;
        padding: .1rem .34rem;
        border-radius: 8px;
        background: color-mix(in srgb, var(--brand) 8%, white);
    }
    .docs-content .markdown-doc pre {
        overflow-x: auto;
        padding: 1rem;
        border-radius: 18px;
        border: 1px solid var(--border);
        background: #0f172a;
        color: #e2e8f0;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
    }
    .docs-content .markdown-doc pre code {
        background: transparent;
        padding: 0;
        border-radius: 0;
        color: inherit;
        font-size: .93rem;
    }
    .docs-content .markdown-doc table {
        width: 100%;
        border-collapse: collapse;
        overflow: hidden;
        border-radius: 18px;
        border: 1px solid var(--border);
    }
    .docs-content .markdown-doc th,
    .docs-content .markdown-doc td {
        padding: .8rem .95rem;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
        text-align: left;
    }
    .docs-content .markdown-doc thead th {
        background: color-mix(in srgb, var(--brand) 8%, white);
        font-size: .85rem;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .docs-content .markdown-doc tbody tr:last-child td {
        border-bottom: 0;
    }
    .docs-aside .mono {
        word-break: break-all;
    }
    @media (max-width: 1200px) {
        .docs-shell { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Документация модулей</h1>
            <p>Как создать, упаковать и подключить кастомный модуль в TestoCMS.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('admin.modules.index') }}">Назад к модулям</a>
        </div>
    </div>

    <div class="docs-shell">
        <section class="panel docs-content">
            @if($docsMarkdown === '')
                <div class="flash error">
                    Файл документации не найден: <span class="mono">{{ $docsPath }}</span>
                </div>
            @else
                <article class="markdown-doc">{!! $docsHtml !!}</article>
            @endif
        </section>

        <aside class="panel docs-aside">
            <h3 style="margin-top:0;">Быстрые действия</h3>
            <div class="grid">
                <a class="btn" href="{{ route('admin.modules.index') }}">Открыть менеджер модулей</a>
                <a class="btn" href="{{ url('/openapi.yaml') }}" target="_blank" rel="noreferrer">OpenAPI</a>
            </div>
            <hr style="border:none; border-top:1px solid var(--border); margin:14px 0;">
            <div class="muted">Путь документа:</div>
            <div class="mono" style="margin-top:6px;">{{ $docsPath }}</div>
            <p class="muted" style="margin-top:14px;">
                Если меняете этот markdown, обновите страницу.
            </p>
        </aside>
    </div>
@endsection
