@extends('admin.layout')

@php
    $defaultLocale = strtolower((string) config('cms.default_locale', 'ru'));
    $templateSourceName = isset($templateSource) && $templateSource ? (string) ($templateSource->name ?? '') : '';
    $localeErrorMap = [];
    $sidebarTabErrorMap = [
        'seo' => false,
    ];
    if (isset($errors) && $errors->any()) {
        foreach ($errors->getMessages() as $key => $messages) {
            if (preg_match('/^translations\.([a-z0-9_-]+)(?:\.|$)/i', (string) $key, $m) === 1) {
                $localeKey = strtolower((string) ($m[1] ?? ''));
                if ($localeKey === '') {
                    continue;
                }
                $localeErrorMap[$localeKey] ??= [];
                foreach ((array) $messages as $message) {
                    if (! in_array((string) $message, $localeErrorMap[$localeKey], true)) {
                        $localeErrorMap[$localeKey][] = (string) $message;
                    }
                }
            }

            if (preg_match('/^translations\.[a-z0-9_-]+\.(excerpt|meta_title|meta_description|canonical_url|custom_head_html)$/i', (string) $key) === 1) {
                $sidebarTabErrorMap['seo'] = true;
            }
        }
    }
@endphp

@section('title', $isEdit ? 'Редактирование поста' : 'Создание поста')

@push('head')
    <style>
        .locale-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .locale-tab {
            border: 1px solid #d0d5dd;
            background: #fff;
            color: var(--text);
            border-radius: 999px;
            padding: 8px 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .locale-tab.active {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }
        .locale-tab.has-error {
            border-color: #fca5a5;
            color: #b42318;
            background: #fff5f5;
        }
        .locale-tab.has-error.active {
            background: #7f1d1d;
            border-color: #7f1d1d;
            color: #fff;
        }
        .locale-tip {
            margin: -4px 0 12px;
            color: #667085;
            font-size: 12px;
        }
        .locale-error-box {
            border: 1px solid #fecaca;
            background: #fff5f5;
            color: #7f1d1d;
            border-radius: 12px;
            padding: 10px 12px;
            margin: 0 0 12px;
        }
        .locale-error-box strong { display: block; margin-bottom: 6px; }
        .locale-error-box ul { margin: 0 0 0 18px; }
        .locale-error-box li + li { margin-top: 4px; }
        .locale-pane { display: none; }
        .locale-pane.active { display: block; }

        .page-header {
            margin-bottom: 12px;
            align-items: stretch;
        }
        .page-header h1 {
            font-size: 26px;
            line-height: 1.05;
        }
        .page-header p {
            font-size: 13px;
            line-height: 1.35;
            margin-top: 4px;
        }
        .page-header .actions {
            row-gap: 6px;
            align-content: flex-start;
            justify-content: flex-end;
        }
        .page-header .actions .btn,
        .page-header .actions button {
            min-height: 34px;
            padding: 7px 10px;
        }

        .content-studio {
            border: 1px solid #dbe3ef;
            border-radius: 14px;
            background: #f8fafc;
            overflow: hidden;
        }
        .composer-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 12px;
            align-items: start;
        }
        .composer-main {
            min-width: 0;
        }
        .composer-main-panel {
            padding: 14px;
        }
        .composer-sidebar {
            min-width: 0;
            position: sticky;
            top: 16px;
            max-height: calc(100vh - 32px);
            overflow: hidden;
        }
        .composer-sidebar-card {
            display: flex;
            flex-direction: column;
            min-height: 0;
            max-height: calc(100vh - 32px);
            padding: 0;
            overflow: hidden;
        }
        .composer-sidebar-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(180deg, #fff, #f8fafc);
        }
        .composer-sidebar-toggle {
            border: 1px solid #d0d5dd;
            background: #fff;
            border-radius: 9px;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 700;
            color: #334155;
            flex: 0 0 auto;
        }
        .composer-sidebar-toggle:hover { background: #f8fafc; }
        .composer-sidebar-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            min-width: 0;
        }
        .composer-sidebar-tab {
            border: 1px solid #d0d5dd;
            background: #fff;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            color: #334155;
            white-space: nowrap;
        }
        .composer-sidebar-tab.active {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }
        .composer-sidebar-tab.has-error {
            border-color: #fecaca;
            background: #fff5f5;
            color: #b42318;
        }
        .composer-sidebar-tab.has-error.active {
            background: #7f1d1d;
            border-color: #7f1d1d;
            color: #fff;
        }
        .composer-sidebar-body {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1 1 auto;
        }
        .composer-sidebar-panels {
            min-height: 0;
            overflow: auto;
            padding: 10px;
            display: grid;
            gap: 10px;
            align-content: start;
        }
        .composer-sidebar-section {
            display: none;
            gap: 10px;
            align-content: start;
        }
        .composer-sidebar-section.active {
            display: grid;
        }
        .composer-sidebar-box {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }
        .composer-sidebar-box h3 {
            margin: 0 0 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #475467;
        }
        .composer-sidebar .field {
            margin-bottom: 10px;
        }
        .composer-sidebar .field:last-child {
            margin-bottom: 0;
        }
        .composer-sidebar .panel.sidebar-mounted-panel {
            margin: 0;
            padding: 12px;
            box-shadow: none;
        }
        .composer-sidebar .panel.sidebar-mounted-panel h3 {
            margin: 0 0 10px;
            font-size: 13px;
        }
        .composer-sidebar .panel.sidebar-mounted-panel .helper-inline {
            row-gap: 6px;
        }
        .composer-sidebar .panel.sidebar-mounted-panel textarea {
            min-height: 96px;
        }
        .composer-sidebar .panel.sidebar-mounted-panel .seo-preview {
            padding: 10px;
        }
        .composer-sticky-savebar {
            border-top: 1px solid #e5e7eb;
            background: #fff;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .composer-sticky-savebar .btn {
            flex: 1 1 auto;
            min-height: 36px;
        }
        .composer-sticky-savebar .status-pill {
            margin-left: auto;
        }
        .composer-shell.is-sidebar-collapsed {
            grid-template-columns: minmax(0, 1fr) 56px;
        }
        .composer-shell.is-sidebar-collapsed .composer-sidebar-head {
            flex-direction: column;
            align-items: stretch;
            padding: 8px 6px;
        }
        .composer-shell.is-sidebar-collapsed .composer-sidebar-tabs {
            flex-direction: column;
            flex-wrap: nowrap;
            width: 100%;
        }
        .composer-shell.is-sidebar-collapsed .composer-sidebar-tab {
            border-radius: 10px;
            padding: 8px 4px;
            text-align: center;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            min-height: 64px;
            font-size: 11px;
        }
        .composer-shell.is-sidebar-collapsed .composer-sidebar-panels,
        .composer-shell.is-sidebar-collapsed .composer-sticky-savebar {
            display: none;
        }
        .composer-shell.is-sidebar-collapsed .composer-sidebar-card {
            max-height: none;
        }
        .metadata-strip {
            border: 1px solid #dbe3ef;
            background: #fff;
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .metadata-strip-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: 10px;
        }
        .metadata-strip .field {
            margin-bottom: 0;
        }
        .metadata-strip small {
            font-size: 12px;
            line-height: 1.35;
        }
        .editor-secondary-collapsible {
            margin-top: 10px;
        }
        .editor-secondary-collapsible summary {
            cursor: pointer;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .editor-secondary-collapsible summary::-webkit-details-marker { display: none; }
        .editor-secondary-collapsible > summary h3 {
            margin: 0;
        }

        .content-studio-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .studio-title {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .segmented {
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            border-radius: 12px;
            border: 1px solid #dbe3ef;
            background: #fff;
            flex-wrap: wrap;
        }
        .segmented button {
            border: 0;
            background: transparent;
            border-radius: 9px;
            padding: 6px 10px;
            cursor: pointer;
            font-weight: 600;
            color: #475467;
        }
        .segmented button.active {
            background: #0f172a;
            color: #fff;
        }
        .is-hidden {
            display: none !important;
        }

        .editor-toolbar {
            display: grid;
            gap: 8px;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
        }
        .tool-cluster-scroll {
            display: grid;
            gap: 6px;
            min-width: 0;
        }
        .tool-cluster {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            min-width: 0;
        }
        .editor-toolbar .tool-btn,
        .editor-toolbar .tool-select {
            border: 1px solid #d0d5dd;
            background: #fff;
            color: #111827;
            border-radius: 9px;
            min-height: 32px;
            padding: 5px 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .editor-toolbar .tool-select {
            min-width: min(280px, 100%);
        }
        .editor-toolbar .tool-btn:hover { background: #f8fafc; }
        .editor-toolbar .tool-btn.active { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
        .editor-toolbar .tool-divider {
            width: 1px;
            background: #e5e7eb;
            margin: 0 2px;
            align-self: stretch;
        }

        .editor-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
            gap: 0;
        }
        .editor-pane-shell {
            min-width: 0;
            border-right: 1px solid #e5e7eb;
            background: #fff;
        }
        .editor-preview-shell {
            min-width: 0;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
        }
        .editor-canvas {
            min-height: 360px;
            max-height: 720px;
            overflow: auto;
            padding: 16px;
            outline: none;
            line-height: 1.6;
            font-size: 15px;
        }
        .editor-canvas:empty::before {
            content: attr(data-placeholder);
            color: #98a2b3;
        }
        .editor-canvas p { margin: 0 0 12px; }
        .editor-canvas h1, .editor-canvas h2, .editor-canvas h3, .editor-canvas h4 { line-height: 1.15; margin: 1.1em 0 .5em; }
        .editor-canvas blockquote {
            margin: 0 0 12px;
            padding: 10px 12px;
            border-left: 4px solid #0ea5e9;
            background: #f0f9ff;
            border-radius: 8px;
        }
        .editor-canvas pre {
            background: #111827;
            color: #e5e7eb;
            padding: 12px;
            border-radius: 10px;
            overflow: auto;
        }
        .editor-canvas code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            background: #f1f5f9;
            border-radius: 6px;
            padding: 2px 5px;
        }
        .editor-source {
            display: none;
            width: 100%;
            border: 0;
            border-top: 1px solid #e5e7eb;
            border-radius: 0;
            min-height: 360px;
            max-height: 720px;
            resize: vertical;
            padding: 14px;
            font: 13px/1.5 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            background: #0b1220;
            color: #e5e7eb;
        }
        .markdown-source {
            display: block;
            border-top: 0;
            min-height: 420px;
            resize: vertical;
        }
        .markdown-layout .editor-pane-shell {
            background: #0b1220;
        }
        .markdown-import-box {
            display: grid;
            gap: 10px;
            padding: 12px;
            border-bottom: 1px solid rgba(226, 232, 240, .12);
            background:
                linear-gradient(180deg, rgba(15, 23, 42, .95), rgba(11, 18, 32, .98)),
                radial-gradient(circle at top right, rgba(56, 189, 248, .14), transparent 45%);
        }
        .markdown-import-box.is-dragover {
            background:
                linear-gradient(180deg, rgba(15, 23, 42, .92), rgba(8, 15, 28, .98)),
                radial-gradient(circle at top right, rgba(14, 165, 233, .22), transparent 55%);
            box-shadow: inset 0 0 0 1px rgba(56, 189, 248, .38);
        }
        .markdown-import-copy {
            display: grid;
            gap: 4px;
        }
        .markdown-import-copy strong {
            color: #f8fafc;
            font-size: 13px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .markdown-import-copy p {
            margin: 0;
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.45;
        }
        .markdown-import-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .markdown-import-status {
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.4;
        }
        .markdown-import-status.is-error {
            color: #fca5a5;
        }
        .markdown-import-status.is-success {
            color: #86efac;
        }

        .editor-preview-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
        }
        .preview-frame-wrap {
            padding: 14px;
            overflow: auto;
        }
        .preview-frame {
            margin: 0 auto;
            width: 100%;
            max-width: 100%;
            border-radius: 14px;
            border: 1px solid #dbe3ef;
            background: #fff;
            box-shadow: 0 8px 20px rgba(15,23,42,.05);
            overflow: hidden;
            transition: width .18s ease;
        }
        .preview-frame.device-mobile { width: 390px; max-width: 100%; }
        .preview-frame.device-tablet { width: 768px; max-width: 100%; }
        .preview-frame.device-desktop { width: 100%; }

        .preview-doc {
            padding: 18px;
            color: #111827;
            line-height: 1.65;
            font: 15px/1.6 ui-sans-serif, system-ui, sans-serif;
        }
        .preview-doc h1, .preview-doc h2, .preview-doc h3, .preview-doc h4 {
            line-height: 1.1;
            margin: 1.1em 0 .45em;
            letter-spacing: -.02em;
        }
        .preview-doc h1 { font-size: 28px; }
        .preview-doc h2 { font-size: 24px; }
        .preview-doc h3 { font-size: 20px; }
        .preview-doc p, .preview-doc ul, .preview-doc ol, .preview-doc table, .preview-doc blockquote, .preview-doc pre {
            margin: 0 0 12px;
        }
        .preview-doc img { max-width: 100%; height: auto; border-radius: 10px; display: block; }
        .preview-doc blockquote {
            padding: 10px 12px;
            border-left: 4px solid #d9472b;
            background: #fff7ed;
            border-radius: 8px;
        }
        .preview-doc table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            display: block;
        }
        .preview-doc table td, .preview-doc table th {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 10px;
            white-space: nowrap;
        }
        .preview-doc .cms-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 10px;
            padding: 9px 12px;
            color: #fff;
            text-decoration: none;
            background: linear-gradient(135deg, #d9472b, #ef7f1a);
            font-weight: 700;
        }

        .editor-meta-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .helper-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .helper-inline .btn {
            margin: 0;
        }
        .seo-preview {
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }
        .seo-preview-title {
            color: #1a0dab;
            font-size: 18px;
            line-height: 1.3;
            margin: 0 0 4px;
            word-break: break-word;
        }
        .seo-preview-url {
            color: #0f766e;
            font-size: 13px;
            margin: 0 0 4px;
            word-break: break-all;
        }
        .seo-preview-desc {
            color: #4b5563;
            font-size: 13px;
            margin: 0;
        }

        .char-counter {
            color: #667085;
            font-size: 12px;
            margin-top: 4px;
        }
        .autosave-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 10px;
            border: 1px solid #d0d5dd;
            background: #fff;
            color: #475467;
            font-size: 12px;
            font-weight: 600;
        }
        .autosave-chip.saving {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .autosave-chip.restored {
            border-color: #86efac;
            background: #ecfdf3;
            color: #166534;
        }
        .autosave-summary {
            display: inline-flex;
            align-items: center;
            border: 1px solid #dbe3ef;
            background: #fff;
            color: #475467;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            white-space: nowrap;
            max-width: 42ch;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .autosave-action-btn {
            border: 1px solid #d0d5dd;
            background: #fff;
            color: #111827;
            border-radius: 10px;
            padding: 8px 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }
        .autosave-action-btn:hover { background: #f8fafc; }
        .autosave-action-btn.danger {
            border-color: #fecaca;
            color: #b42318;
            background: #fff5f5;
        }
        .inline-editor-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 85;
            padding: 14px;
        }
        .inline-editor-modal-overlay.open { display: flex; }
        .inline-editor-modal {
            width: min(560px, 100%);
            border-radius: 14px;
            border: 1px solid #dbe3ef;
            background: #fff;
            box-shadow: 0 18px 50px rgba(15,23,42,.18);
            overflow: hidden;
        }
        .inline-editor-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(180deg, #fff, #f8fafc);
        }
        .inline-editor-modal-header h3 { margin: 0; font-size: 17px; }
        .inline-editor-modal-header p { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .inline-editor-modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid #d0d5dd;
            background: #fff;
            cursor: pointer;
            font-weight: 700;
        }
        .inline-editor-modal-body { padding: 14px; }
        .inline-editor-modal-footer {
            padding: 12px 14px;
            border-top: 1px solid #e5e7eb;
            background: #fff;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .inline-editor-modal-footer .actions { margin-left: auto; }

        @media (max-width: 1280px) {
            .composer-shell {
                grid-template-columns: minmax(0, 1fr) 340px;
            }
            .editor-layout {
                grid-template-columns: 1fr;
            }
            .editor-pane-shell { border-right: 0; border-bottom: 1px solid #e5e7eb; }
        }
        @media (max-width: 1024px) {
            .composer-shell,
            .composer-shell.is-sidebar-collapsed {
                grid-template-columns: 1fr;
            }
            .composer-sidebar {
                position: static;
                top: auto;
                max-height: none;
            }
            .composer-sidebar-card {
                max-height: none;
            }
            .composer-shell.is-sidebar-collapsed .composer-sidebar-panels { display: grid; }
            .composer-shell.is-sidebar-collapsed .composer-sticky-savebar { display: flex; }
            .composer-shell.is-sidebar-collapsed .composer-sidebar-head {
                flex-direction: row;
                padding: 10px;
            }
            .composer-shell.is-sidebar-collapsed .composer-sidebar-tabs {
                flex-direction: row;
                flex-wrap: wrap;
            }
            .composer-shell.is-sidebar-collapsed .composer-sidebar-tab {
                writing-mode: initial;
                text-orientation: initial;
                min-height: 0;
                border-radius: 999px;
                padding: 6px 10px;
            }
            .tool-cluster-scroll {
                display: flex;
                gap: 8px;
                overflow: auto;
                padding-bottom: 2px;
            }
            .tool-cluster {
                flex-wrap: nowrap;
                padding-right: 4px;
            }
            .tool-cluster .tool-select {
                min-width: 220px;
            }
        }
        @media (max-width: 900px) {
            .metadata-strip-grid,
            .editor-meta-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $isEdit ? 'Редактирование поста' : 'Создание поста' }}</h1>
            <p>{{ $isEdit ? 'Пост #'.$post->id : 'Создание локализованного поста.' }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn" data-open-create-template-modal>Создать из шаблона</button>
            <button type="button" class="btn" data-open-save-template-modal>Сохранить как шаблон</button>
            <button type="button" class="btn btn-ghost" data-composer-sidebar-toggle aria-expanded="true" title="Свернуть/развернуть инспектор">Инспектор</button>
            <a href="{{ route('admin.posts.index') }}" class="btn">Назад к постам</a>
            @if($isEdit)
                @php $defaultT = $translationsByLocale[$defaultLocale] ?? null; @endphp
                @if($defaultT && $post->status === 'published')
                    <a class="btn" href="{{ url('/'.$defaultLocale.'/'.config('cms.post_url_prefix', 'blog').'/'.$defaultT->slug) }}" target="_blank" rel="noreferrer">Открыть пост</a>
                @endif
            @endif
            @if($templateSourceName !== '')
                <span class="autosave-summary" title="Шаблон-источник">Шаблон: {{ $templateSourceName }}</span>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('admin.posts.update', $post) : route('admin.posts.store') }}" id="post-form">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <input type="file" accept=".md,.markdown,.txt,text/markdown,text/plain" data-markdown-import-input hidden>

        <div class="composer-shell" data-composer-shell="post">
            <section class="panel composer-main composer-main-panel" data-composer-main>
                <h2 style="margin-top:0;">Контент-студия</h2>
                <p class="muted" style="margin-top:0;">Визуальный редактор с live preview. HTML всё равно санитизируется на сохранении.</p>

                <div class="locale-tabs" data-locale-tabs="post">
                    @foreach($locales as $locale)
                        <button
                            type="button"
                            class="locale-tab {{ ($locale === $defaultLocale || ($defaultLocale === '' && $loop->first)) ? 'active' : '' }} {{ !empty($localeErrorMap[strtolower($locale)]) ? 'has-error' : '' }}"
                            data-locale-tab="{{ $locale }}"
                        >
                            {{ strtoupper($locale) }}
                            @if($locale === $defaultLocale)
                                · по умолчанию
                            @endif
                            @if(!empty($localeErrorMap[strtolower($locale)]))
                                · !
                            @endif
                        </button>
                    @endforeach
                </div>
                <p class="locale-tip">Локаль считается используемой, если в ней заполнен контент, SEO-поля или custom code в <span class="mono">&lt;head&gt;</span>. Для используемой локали обязательны и <strong>Заголовок</strong>, и <strong>Slug</strong>.</p>

                @foreach($locales as $locale)
                    @php
                        $t = $translationsByLocale[$locale] ?? null;
                        $oldTranslation = old('translations.'.$locale, []);
                        $title = $oldTranslation['title'] ?? $t?->title ?? '';
                        $slug = $oldTranslation['slug'] ?? $t?->slug ?? '';
                        $contentFormat = $oldTranslation['content_format'] ?? $t?->content_format ?? 'html';
                        $contentHtml = $oldTranslation['content_html'] ?? $t?->content_html ?? '';
                        $contentMarkdown = $oldTranslation['content_markdown'] ?? $t?->content_markdown ?? '';
                        $excerpt = $oldTranslation['excerpt'] ?? $t?->excerpt ?? '';
                        $metaTitle = $oldTranslation['meta_title'] ?? $t?->meta_title ?? '';
                        $metaDescription = $oldTranslation['meta_description'] ?? $t?->meta_description ?? '';
                        $canonicalUrl = $oldTranslation['canonical_url'] ?? $t?->canonical_url ?? '';
                        $customHeadHtml = $oldTranslation['custom_head_html'] ?? $t?->custom_head_html ?? '';
                    @endphp
                    <section
                        class="locale-pane {{ ($locale === $defaultLocale || ($defaultLocale === '' && $loop->first)) ? 'active' : '' }}"
                        data-locale-pane="{{ $locale }}"
                    >
                        @if(!empty($localeErrorMap[strtolower($locale)]))
                            <div class="locale-error-box">
                                <strong>Ошибки в локали {{ strtoupper($locale) }}</strong>
                                <ul>
                                    @foreach($localeErrorMap[strtolower($locale)] as $localeError)
                                        <li>{{ $localeError }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="metadata-strip" data-post-metadata-strip="{{ $locale }}">
                            <div class="metadata-strip-grid">
                                <div class="field">
                                    <label for="post-{{ $locale }}-title">Заголовок ({{ strtoupper($locale) }})</label>
                                    <input
                                        id="post-{{ $locale }}-title"
                                        type="text"
                                        name="translations[{{ $locale }}][title]"
                                        value="{{ $title }}"
                                        data-post-title="{{ $locale }}"
                                    >
                                    <small>Заголовок для контента и, если meta title пустой, для SEO.</small>
                                </div>
                                <div class="field">
                                    <label for="post-{{ $locale }}-slug">Slug</label>
                                    <div class="helper-inline">
                                        <input
                                            id="post-{{ $locale }}-slug"
                                            type="text"
                                            name="translations[{{ $locale }}][slug]"
                                            value="{{ $slug }}"
                                            data-post-slug="{{ $locale }}"
                                        >
                                        <button type="button" class="btn btn-small" data-slug-generate="{{ $locale }}">Сгенерировать</button>
                                    </div>
                                    <small>URL: <span class="mono">/{{ $locale }}/{{ config('cms.post_url_prefix', 'blog') }}/&lt;slug&gt;</span></small>
                                </div>
                            </div>
                        </div>

                        <div class="content-studio" data-post-editor="{{ $locale }}">
                            <div class="content-studio-header">
                                <div>
                                    <p class="studio-title">Редактор контента · {{ strtoupper($locale) }}</p>
                                    <p class="muted" style="margin:4px 0 0; font-size:12px;">HTML-режим сохраняет визуальный редактор. Markdown-режим хранит исходник и рендерит HTML на сервере.</p>
                                </div>
                                <div class="helper-inline">
                                    <input type="hidden" name="translations[{{ $locale }}][content_format]" value="{{ $contentFormat }}" data-content-format="{{ $locale }}">
                                    <div class="segmented" data-content-format-toggle="{{ $locale }}">
                                        <button type="button" class="{{ $contentFormat !== 'markdown' ? 'active' : '' }}" data-content-format-option="html">Visual HTML</button>
                                        <button type="button" class="{{ $contentFormat === 'markdown' ? 'active' : '' }}" data-content-format-option="markdown">Markdown</button>
                                    </div>
                                    <div class="segmented {{ $contentFormat === 'markdown' ? 'is-hidden' : '' }}" data-editor-view-toggle>
                                        <button type="button" class="active" data-editor-view="split">Разделено</button>
                                        <button type="button" data-editor-view="edit">Редактор</button>
                                        <button type="button" data-editor-view="preview">Превью</button>
                                        <button type="button" data-editor-view="source">HTML</button>
                                    </div>
                                    <div class="segmented {{ $contentFormat === 'markdown' ? '' : 'is-hidden' }}" data-markdown-view-toggle>
                                        <button type="button" class="active" data-markdown-view="split">Split</button>
                                        <button type="button" data-markdown-view="edit">Markdown</button>
                                        <button type="button" data-markdown-view="preview">Превью</button>
                                    </div>
                                    <div class="segmented" data-editor-device-toggle>
                                        <button type="button" class="active" data-editor-device="desktop">Десктоп</button>
                                        <button type="button" data-editor-device="tablet">Планшет</button>
                                        <button type="button" data-editor-device="mobile">Моб.</button>
                                    </div>
                                </div>
                            </div>

                            <div class="editor-toolbar {{ $contentFormat === 'markdown' ? 'is-hidden' : '' }}" data-editor-toolbar>
                                <div class="tool-cluster-scroll">
                                    <div class="tool-cluster">
                                        <button type="button" class="tool-btn" data-cmd="bold"><strong>B</strong></button>
                                        <button type="button" class="tool-btn" data-cmd="italic"><em>I</em></button>
                                        <button type="button" class="tool-btn" data-cmd="underline"><u>U</u></button>
                                    </div>
                                    <div class="tool-cluster">
                                        <select class="tool-select" data-format-block>
                                            <option value="">Абзац / Заголовок</option>
                                            <option value="P">Абзац</option>
                                            <option value="H2">Заголовок 2</option>
                                            <option value="H3">Заголовок 3</option>
                                            <option value="H4">Заголовок 4</option>
                                            <option value="BLOCKQUOTE">Цитата</option>
                                            <option value="PRE">Блок кода</option>
                                        </select>
                                        <button type="button" class="tool-btn" data-cmd="insertUnorderedList">• Список</button>
                                        <button type="button" class="tool-btn" data-cmd="insertOrderedList">1. Список</button>
                                        <button type="button" class="tool-btn" data-insert="hr">Разделитель</button>
                                    </div>
                                    <div class="tool-cluster">
                                        <button type="button" class="tool-btn" data-action="link">Ссылка</button>
                                        <button type="button" class="tool-btn" data-cmd="unlink">Убрать ссылку</button>
                                        <button type="button" class="tool-btn" data-action="image">Изображение</button>
                                    </div>
                                    <div class="tool-cluster">
                                        <button type="button" class="tool-btn" data-snippet="cta">CTA…</button>
                                        <button type="button" class="tool-btn" data-snippet="faq">FAQ</button>
                                        <button type="button" class="tool-btn" data-snippet="table">Таблица</button>
                                    </div>
                                </div>
                            </div>

                            <div class="editor-toolbar {{ $contentFormat === 'markdown' ? '' : 'is-hidden' }}" data-markdown-toolbar>
                                <div class="tool-cluster-scroll">
                                    <div class="tool-cluster">
                                        <button type="button" class="tool-btn" data-markdown-snippet="h2">## Заголовок</button>
                                        <button type="button" class="tool-btn" data-markdown-snippet="bold">**Жирный**</button>
                                        <button type="button" class="tool-btn" data-markdown-snippet="italic">_Курсив_</button>
                                    </div>
                                    <div class="tool-cluster">
                                        <button type="button" class="tool-btn" data-markdown-snippet="link">[Ссылка](https://)</button>
                                        <button type="button" class="tool-btn" data-markdown-snippet="image">![Alt](https://)</button>
                                        <button type="button" class="tool-btn" data-markdown-snippet="list">- Список</button>
                                    </div>
                                    <div class="tool-cluster">
                                        <button type="button" class="tool-btn" data-markdown-snippet="cta">CTA</button>
                                        <button type="button" class="tool-btn" data-markdown-snippet="faq">FAQ</button>
                                        <button type="button" class="tool-btn" data-markdown-snippet="table">Таблица</button>
                                        <button type="button" class="tool-btn" data-markdown-snippet="code">```code```</button>
                                    </div>
                                </div>
                            </div>

                            <div class="editor-layout {{ $contentFormat === 'markdown' ? 'is-hidden' : '' }}" data-editor-layout>
                                <div class="editor-pane-shell" data-pane="editor">
                                    <div
                                        class="editor-canvas"
                                        contenteditable="true"
                                        data-html-canvas
                                        data-placeholder="Начните писать текст, вставляйте форматирование и блоки…"
                                    ></div>
                                    <textarea
                                        class="editor-source"
                                        rows="14"
                                        name="translations[{{ $locale }}][content_html]"
                                        data-html-source
                                    >{{ $contentHtml }}</textarea>
                                </div>

                                <div class="editor-preview-shell" data-pane="preview">
                                    <div class="editor-preview-topbar">
                                        <span class="muted" style="font-size:13px;">Предпросмотр (без сохранения)</span>
                                        <span class="muted" style="font-size:12px;">Без серверной очистки до сохранения</span>
                                    </div>
                                    <div class="preview-frame-wrap">
                                        <div class="preview-frame device-desktop" data-preview-frame>
                                            <div class="preview-doc" data-html-preview></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="editor-layout markdown-layout {{ $contentFormat === 'markdown' ? '' : 'is-hidden' }}" data-markdown-layout>
                                <div class="editor-pane-shell" data-markdown-pane="editor">
                                    <div class="markdown-import-box" data-markdown-import-zone="{{ $locale }}">
                                        <div class="markdown-import-copy">
                                            <strong>Импорт Markdown</strong>
                                            <p>Перетащите сюда `.md`, `.markdown` или `.txt`, чтобы сразу заполнить текущую локаль и обновить preview.</p>
                                        </div>
                                        <div class="markdown-import-actions">
                                            <button type="button" class="btn btn-small" data-markdown-import-trigger="{{ $locale }}">Выбрать файл</button>
                                            <span class="markdown-import-status" data-markdown-import-status="{{ $locale }}">Файл ещё не выбран.</span>
                                        </div>
                                    </div>
                                    <textarea
                                        class="editor-source markdown-source"
                                        rows="16"
                                        name="translations[{{ $locale }}][content_markdown]"
                                        data-markdown-source
                                        placeholder="Пишите Markdown или импортируйте .md-файл с YAML front matter."
                                    >{{ $contentMarkdown }}</textarea>
                                </div>

                                <div class="editor-preview-shell" data-markdown-pane="preview">
                                    <div class="editor-preview-topbar">
                                        <span class="muted" style="font-size:13px;">Markdown preview</span>
                                        <span class="muted" style="font-size:12px;" data-markdown-preview-status>Рендер на сервере</span>
                                    </div>
                                    <div class="preview-frame-wrap">
                                        <div class="preview-frame device-desktop" data-markdown-preview-frame>
                                            <div class="preview-doc" data-markdown-preview></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <details class="panel editor-secondary-collapsible" data-post-locale-seo-panel="{{ $locale }}">
                            <summary>
                                <h3>Анонс и SEO ({{ strtoupper($locale) }})</h3>
                                <span class="muted" style="font-size:12px;">Развернуть</span>
                            </summary>
                            <div style="margin-top:12px;">
                            <div class="helper-inline" style="justify-content:space-between;">
                                <button type="button" class="btn btn-small" data-excerpt-generate="{{ $locale }}">Сгенерировать анонс из контента</button>
                            </div>

                            <div class="field" style="margin-top:12px;">
                                <label for="post-{{ $locale }}-excerpt">Анонс</label>
                                <textarea
                                    id="post-{{ $locale }}-excerpt"
                                    name="translations[{{ $locale }}][excerpt]"
                                    rows="3"
                                    data-post-excerpt="{{ $locale }}"
                                >{{ $excerpt }}</textarea>
                                <div class="char-counter" data-char-counter-for="post-{{ $locale }}-excerpt"></div>
                            </div>

                            <div class="editor-meta-grid">
                                <div class="field">
                                    <label for="post-{{ $locale }}-meta-title">Meta title</label>
                                    <input
                                        id="post-{{ $locale }}-meta-title"
                                        type="text"
                                        name="translations[{{ $locale }}][meta_title]"
                                        value="{{ $metaTitle }}"
                                        data-post-meta-title="{{ $locale }}"
                                    >
                                    <div class="char-counter" data-char-counter-for="post-{{ $locale }}-meta-title"></div>
                                </div>
                                <div class="field">
                                    <label for="post-{{ $locale }}-canonical">Canonical URL</label>
                                    <input
                                        id="post-{{ $locale }}-canonical"
                                        type="text"
                                        name="translations[{{ $locale }}][canonical_url]"
                                        value="{{ $canonicalUrl }}"
                                        data-post-canonical="{{ $locale }}"
                                    >
                                </div>
                            </div>

                            <div class="field">
                                <label for="post-{{ $locale }}-meta-desc">Meta description</label>
                                <textarea
                                    id="post-{{ $locale }}-meta-desc"
                                    name="translations[{{ $locale }}][meta_description]"
                                    rows="3"
                                    data-post-meta-description="{{ $locale }}"
                                >{{ $metaDescription }}</textarea>
                                <div class="char-counter" data-char-counter-for="post-{{ $locale }}-meta-desc"></div>
                            </div>

                            <div class="field">
                                <label for="post-{{ $locale }}-custom-head">Кастомный код в &lt;head&gt; (advanced)</label>
                                <textarea
                                    id="post-{{ $locale }}-custom-head"
                                    name="translations[{{ $locale }}][custom_head_html]"
                                    rows="6"
                                    class="mono"
                                >{{ $customHeadHtml }}</textarea>
                                <small>Вставляется в <span class="mono">&lt;head&gt;</span> этой страницы поста (meta/script/style/verification tags). Используйте только доверенный код.</small>
                            </div>

                            <div class="seo-preview" data-seo-preview="{{ $locale }}">
                                <p class="seo-preview-title" data-seo-preview-title>
                                    {{ $metaTitle !== '' ? $metaTitle : ($title !== '' ? $title : 'Заголовок поста') }}
                                </p>
                                <p class="seo-preview-url" data-seo-preview-url>
                                    {{ url('/'.$locale.'/'.config('cms.post_url_prefix', 'blog').'/'.($slug !== '' ? $slug : 'slug')) }}
                                </p>
                                <p class="seo-preview-desc" data-seo-preview-desc>
                                    {{ $metaDescription !== '' ? $metaDescription : ($excerpt !== '' ? $excerpt : 'Здесь появится превью meta description.') }}
                                </p>
                            </div>
                            </div>
                        </details>
                    </section>
                @endforeach
            </section>

            <aside class="composer-sidebar" data-composer-sidebar="post">
                <section class="panel composer-sidebar-card">
                    <div class="composer-sidebar-head">
                        <button type="button" class="composer-sidebar-toggle" data-composer-sidebar-toggle aria-expanded="true" title="Свернуть/развернуть инспектор">›</button>
                        <div class="composer-sidebar-tabs" role="tablist" aria-label="Инспектор поста">
                            <button type="button" class="composer-sidebar-tab active" data-composer-sidebar-tab="publish" role="tab" aria-selected="true">Публикация</button>
                            <button type="button" class="composer-sidebar-tab {{ !empty($sidebarTabErrorMap['seo']) ? 'has-error' : '' }}" data-composer-sidebar-tab="seo" role="tab" aria-selected="false">SEO</button>
                            <button type="button" class="composer-sidebar-tab" data-composer-sidebar-tab="media" role="tab" aria-selected="false">Медиа</button>
                            @if($isEdit)
                                <button type="button" class="composer-sidebar-tab" data-composer-sidebar-tab="actions" role="tab" aria-selected="false">Действия</button>
                                <button type="button" class="composer-sidebar-tab" data-composer-sidebar-tab="preview" role="tab" aria-selected="false">Preview/Schedule</button>
                            @endif
                        </div>
                    </div>
                    <div class="composer-sidebar-body">
                        <div class="composer-sidebar-panels">
                            <section class="composer-sidebar-section active" data-composer-sidebar-section="publish" role="tabpanel">
                                <div class="composer-sidebar-box">
                                    <h3>Публикация</h3>
                                    <div class="field">
                                        <label for="post-status">Статус</label>
                                        <select id="post-status" name="status">
                                            @foreach(config('cms.statuses', ['draft','published']) as $status)
                                                <option value="{{ $status }}" @selected(old('status', $post->status) === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="actions">
                                        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Сохранить изменения' : 'Создать пост' }}</button>
                                    </div>
                                </div>
                            </section>
                            <section class="composer-sidebar-section" data-composer-sidebar-section="seo" role="tabpanel">
                                <div class="composer-sidebar-box">
                                    <h3>SEO (активная локаль)</h3>
                                    <p class="muted" style="margin:0 0 10px; font-size:12px;">SEO-панель выбранной локали отображается здесь.</p>
                                    <div data-post-seo-sidebar-host></div>
                                </div>
                            </section>
                            <section class="composer-sidebar-section" data-composer-sidebar-section="media" role="tabpanel">
                                <div class="composer-sidebar-box">
                                    <h3>Медиа и категории</h3>
                                    @php
                                        $featuredAssetValue = old('featured_asset_id', $post->featured_asset_id);
                                    @endphp
                                    <div class="field">
                                        @include('admin.partials.asset-selector', [
                                            'name' => 'featured_asset_id',
                                            'id' => 'featured_asset_id',
                                            'label' => 'Главное изображение (файл)',
                                            'assets' => $assets,
                                            'selectedValue' => $featuredAssetValue,
                                            'selectedAsset' => $assets->firstWhere('id', (int) $featuredAssetValue),
                                            'accept' => 'image',
                                            'pickerTitle' => 'Выбор главного изображения',
                                            'pickerSubtitle' => 'Выберите изображение из Files для карточки поста.',
                                            'pickerUploadTitle' => 'Загрузка главного изображения',
                                            'pickerUploadSubtitle' => 'Загрузите изображение и сразу назначьте его посту.',
                                            'emptyLabel' => 'Главное изображение не выбрано',
                                        ])
                                    </div>
                                    <div class="field">
                                        <label for="category_ids">Категории</label>
                                        <select id="category_ids" name="category_ids[]" multiple size="8">
                                            @foreach($categories as $category)
                                                @php
                                                    $ct = $category->translations->firstWhere('locale', $defaultLocale) ?? $category->translations->first();
                                                    $selected = collect(old('category_ids', $selectedCategoryIds))->map(fn($id) => (string)$id)->all();
                                                @endphp
                                                <option value="{{ $category->id }}" @selected(in_array((string) $category->id, $selected, true))>
                                                    #{{ $category->id }} {{ $ct?->title ?? 'Untitled' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small>Удерживайте Cmd/Ctrl для выбора нескольких категорий.</small>
                                    </div>
                                </div>
                            </section>
                            @if($isEdit)
                                <section class="composer-sidebar-section" data-composer-sidebar-section="actions" role="tabpanel">
                                    <div class="composer-sidebar-box">
                                        <h3>Публикационные действия</h3>
                                        <div class="actions">
                                            <button type="submit" form="post-publish-now-form" class="btn btn-primary">Опубликовать сейчас</button>
                                            <button type="submit" form="post-unpublish-form" class="btn">Вернуть в черновик</button>
                                        </div>
                                    </div>
                                    <div class="composer-sidebar-box">
                                        <h3 style="color:#b42318;">Опасная зона</h3>
                                        <button type="submit" form="post-delete-form" class="btn btn-danger">Удалить пост</button>
                                    </div>
                                </section>
                                <section class="composer-sidebar-section" data-composer-sidebar-section="preview" role="tabpanel">
                                    <div class="composer-sidebar-box">
                                        <h3>Предпросмотр</h3>
                                        <div class="field">
                                            <label for="post-preview-locale">Локаль предпросмотра</label>
                                            <select id="post-preview-locale" name="locale" form="post-preview-token-form">
                                                @foreach($locales as $locale)
                                                    <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" form="post-preview-token-form" class="btn">Сгенерировать ссылку предпросмотра (24ч)</button>
                                    </div>
                                    <div class="composer-sidebar-box">
                                        <h3>Последние токены предпросмотра</h3>
                                        @forelse($previewTokens as $token)
                                            <div style="margin-bottom:10px; border-bottom:1px solid #eaecf0; padding-bottom:10px;">
                                                <div class="mono" style="font-size:12px; word-break:break-all;">{{ route('preview.show', ['token' => $token->token]) }}?locale={{ $defaultLocale }}</div>
                                                <div class="muted" style="font-size:12px;">Истекает {{ optional($token->expires_at)->toDayDateTimeString() }}</div>
                                            </div>
                                        @empty
                                            <p class="muted">Токенов предпросмотра пока нет.</p>
                                        @endforelse
                                    </div>
                                    <div class="composer-sidebar-box">
                                        <h3>Расписание</h3>
                                        <div class="field">
                                            <label for="post-schedule-action">Запланировать действие</label>
                                            <select id="post-schedule-action" name="action" form="post-schedule-form">
                                                <option value="publish">publish</option>
                                                <option value="unpublish">unpublish</option>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="post-schedule-due">Выполнить в</label>
                                            <input id="post-schedule-due" type="datetime-local" name="due_at" required form="post-schedule-form">
                                        </div>
                                        <button type="submit" form="post-schedule-form" class="btn">Создать расписание</button>
                                    </div>
                                    <div class="composer-sidebar-box">
                                        <h3>Текущие задания</h3>
                                        @forelse($schedules as $schedule)
                                            <div style="margin-bottom:10px; border-bottom:1px solid #eaecf0; padding-bottom:10px;">
                                                <div><strong>{{ $schedule->action }}</strong> · {{ optional($schedule->due_at)->toDayDateTimeString() }}</div>
                                                <div class="muted" style="font-size:12px;">{{ $schedule->executed_at ? 'Выполнено '.$schedule->executed_at->toDayDateTimeString() : 'Ожидает выполнения' }}</div>
                                            </div>
                                        @empty
                                            <p class="muted">Записей расписания пока нет.</p>
                                        @endforelse
                                    </div>
                                </section>
                            @endif
                        </div>
                        <div class="composer-sticky-savebar" data-composer-sidebar-savebar>
                            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Сохранить' : 'Создать' }}</button>
                            <span class="status-pill status-{{ e(old('status', $post->status ?: 'draft')) }}" data-composer-status-badge>{{ old('status', $post->status ?: 'draft') }}</span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </form>

    <div class="template-modal" data-create-template-modal style="position:fixed; inset:0; z-index:95; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(15,23,42,.48);">
        <div class="panel" style="width:min(560px,100%); margin:0;">
            <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
                <h3 style="margin:0;">Создать пост из шаблона</h3>
                <button type="button" class="btn btn-small" data-close-create-template-modal>Закрыть</button>
            </div>
            <form method="GET" action="{{ route('admin.posts.create') }}" class="grid" style="gap:10px;">
                <div class="field" style="margin:0;">
                    <label for="post-template-create-select">Шаблон</label>
                    <select id="post-template-create-select" name="from_template" required>
                        <option value="">Выберите шаблон…</option>
                        @foreach(($templates ?? collect()) as $templateItem)
                            <option value="{{ $templateItem->id }}">#{{ $templateItem->id }} · {{ $templateItem->name }}</option>
                        @endforeach
                    </select>
                    <small>Новый пост будет создан как черновик с уникальными slug.</small>
                </div>
                <div class="inline" style="justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" @disabled(isset($templates) && $templates->isEmpty())>Создать из шаблона</button>
                </div>
            </form>
        </div>
    </div>

    <div class="template-modal" data-save-template-modal style="position:fixed; inset:0; z-index:95; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(15,23,42,.48);">
        <div class="panel" style="width:min(620px,100%); margin:0;">
            <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
                <h3 style="margin:0;">Сохранить пост как шаблон</h3>
                <button type="button" class="btn btn-small" data-close-save-template-modal>Закрыть</button>
            </div>
            <form method="POST" action="{{ route('admin.templates.store') }}" class="grid" style="gap:10px;" data-save-template-form>
                @csrf
                <input type="hidden" name="entity_type" value="post">
                <input type="hidden" name="payload_json" data-template-payload-json>
                <div class="field" style="margin:0;">
                    <label for="post-template-name">Название шаблона</label>
                    <input id="post-template-name" type="text" name="name" maxlength="190" required>
                </div>
                <div class="field" style="margin:0;">
                    <label for="post-template-description">Описание</label>
                    <textarea id="post-template-description" name="description" rows="3" maxlength="2000"></textarea>
                </div>
                <div class="inline" style="justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
                </div>
            </form>
        </div>
    </div>

    @if($isEdit)
        <div style="display:none" aria-hidden="true">
            <form id="post-publish-now-form" method="POST" action="{{ route('admin.posts.publish', $post) }}">
                @csrf
            </form>
            <form id="post-unpublish-form" method="POST" action="{{ route('admin.posts.unpublish', $post) }}">
                @csrf
            </form>
            <form id="post-preview-token-form" method="POST" action="{{ route('admin.posts.preview-token', $post) }}">
                @csrf
            </form>
            <form id="post-schedule-form" method="POST" action="{{ route('admin.posts.schedule', $post) }}">
                @csrf
            </form>
            <form id="post-delete-form" method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-confirm="Удалить этот пост?">
                @csrf
                @method('DELETE')
            </form>
        </div>
    @endif

    @include('admin.partials.media-picker', ['mediaPickerAssets' => $assets])

    <div class="inline-editor-modal-overlay" id="post-cta-modal" aria-hidden="true">
        <div class="inline-editor-modal" role="dialog" aria-modal="true" aria-labelledby="post-cta-modal-title">
            <div class="inline-editor-modal-header">
                <div>
                    <h3 id="post-cta-modal-title">CTA-кнопка</h3>
                    <p data-cta-modal-subtitle>Вставка CTA-кнопки в текст поста</p>
                </div>
                <button type="button" class="inline-editor-modal-close" data-cta-modal-close aria-label="Закрыть">✕</button>
            </div>
            <div class="inline-editor-modal-body">
                <div class="field">
                    <label for="post-cta-modal-label">Текст кнопки</label>
                    <input type="text" id="post-cta-modal-label" value="Кнопка CTA" data-cta-modal-label maxlength="255">
                </div>
                <div class="field">
                    <label for="post-cta-modal-url">URL назначения</label>
                    <input type="text" id="post-cta-modal-url" value="/ru/blog" data-cta-modal-url maxlength="2048" placeholder="/ru/blog или https://...">
                    <small>Поддерживаются относительные ссылки (`/ru/...`) и `https://`, `mailto:`, `tel:`.</small>
                </div>
                <div class="grid cols-2">
                    <label class="checkbox"><input type="checkbox" data-cta-modal-target> Открыть в новой вкладке</label>
                    <label class="checkbox"><input type="checkbox" data-cta-modal-nofollow> Добавить rel=nofollow</label>
                </div>
            </div>
            <div class="inline-editor-modal-footer">
                <span class="muted" data-cta-modal-status>Новый CTA</span>
                <div class="actions">
                    <button type="button" class="btn" data-cta-modal-cancel>Отмена</button>
                    <button type="button" class="btn btn-primary" data-cta-modal-submit>Вставить CTA</button>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="testocms-post-editor-boot">{!! json_encode([
        'supportedLocales' => array_values($locales),
        'templateSourceName' => $templateSourceName,
        'markdownPreviewUrl' => route('admin.posts.markdown.preview'),
        'markdownImportUrl' => route('admin.posts.markdown.import'),
        'postUrlPrefix' => (string) config('cms.post_url_prefix', 'blog'),
        'isEditMode' => (bool) $post,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'editor-shared.js']) }}"></script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'post-form.js']) }}"></script>
@endsection
