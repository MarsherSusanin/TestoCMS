@extends('admin.layout')

@php
    $defaultLocale = strtolower((string) config('cms.default_locale', 'ru'));
    $allowedBlockTypes = config('cms.blocks.allowed_types', []);
    $templateSourceName = isset($templateSource) && $templateSource ? (string) ($templateSource->name ?? '') : '';
    $localeErrorMap = [];
    $sidebarTabErrorMap = [
        'seo' => false,
        'advanced' => false,
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

            if (preg_match('/^translations\.[a-z0-9_-]+\.(meta_title|meta_description|canonical_url|custom_head_html)$/i', (string) $key) === 1) {
                $sidebarTabErrorMap['seo'] = true;
            }
            if (preg_match('/^translations\.[a-z0-9_-]+\.(blocks_json|rich_html)$/i', (string) $key) === 1) {
                $sidebarTabErrorMap['advanced'] = true;
            }
        }
    }
@endphp

@section('title', $isEdit ? 'Редактирование страницы' : 'Создание страницы')

@push('head')
    <style>
        .locale-tabs {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin-bottom:12px;
        }
        .locale-tab {
            border:1px solid #d0d5dd;
            background:#fff;
            border-radius:999px;
            padding:8px 12px;
            font-weight:700;
            cursor:pointer;
        }
        .locale-tab.active { background:#0f172a; border-color:#0f172a; color:#fff; }
        .locale-tab.has-error { border-color:#fca5a5; color:#b42318; background:#fff5f5; }
        .locale-tab.has-error.active { background:#7f1d1d; border-color:#7f1d1d; color:#fff; }
        .locale-pane { display:none; }
        .locale-pane.active { display:block; }
        .locale-tip {
            margin: -4px 0 12px;
            color:#667085;
            font-size:12px;
        }
        .locale-error-box {
            border:1px solid #fecaca;
            background:#fff5f5;
            color:#7f1d1d;
            border-radius:12px;
            padding:10px 12px;
            margin:0 0 12px;
        }
        .locale-error-box strong { display:block; margin-bottom:6px; }
        .locale-error-box ul { margin:0 0 0 18px; }
        .locale-error-box li + li { margin-top:4px; }

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

        .composer-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 12px;
            align-items: start;
        }
        .composer-main { min-width: 0; }
        .composer-main-panel { padding: 14px; }
        .composer-sidebar {
            min-width: 0;
            position: sticky;
            top: 16px;
            max-height: calc(100vh - 32px);
            overflow: hidden;
        }
        .composer-sidebar-card {
            padding: 0;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 32px);
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
            color: #334155;
            font-weight: 700;
            flex: 0 0 auto;
        }
        .composer-sidebar-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
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
        .composer-sidebar-section { display: none; gap: 10px; align-content: start; }
        .composer-sidebar-section.active { display: grid; }
        .composer-sidebar-box {
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 12px;
            padding: 12px;
        }
        .composer-sidebar-box h3 {
            margin: 0 0 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #475467;
        }
        .composer-sidebar .field { margin-bottom: 10px; }
        .composer-sidebar .field:last-child { margin-bottom: 0; }
        .composer-sidebar .panel.sidebar-mounted-panel {
            margin: 0;
            padding: 12px;
            box-shadow: none;
        }
        .composer-sidebar .panel.sidebar-mounted-panel h3 {
            margin: 0 0 10px;
            font-size: 13px;
        }
        .composer-sidebar .panel.sidebar-mounted-panel .seo-preview {
            padding: 10px;
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
        }
        .composer-shell.is-sidebar-collapsed .composer-sidebar-tab {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            border-radius: 10px;
            min-height: 64px;
            padding: 8px 4px;
            text-align: center;
            font-size: 11px;
        }
        .composer-shell.is-sidebar-collapsed .composer-sidebar-panels,
        .composer-shell.is-sidebar-collapsed .composer-sticky-savebar {
            display: none;
        }
        .composer-sticky-savebar {
            border-top: 1px solid #e5e7eb;
            background: #fff;
            padding: 10px;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .composer-sticky-savebar .btn {
            flex: 1 1 auto;
            min-height: 36px;
        }
        .composer-sticky-savebar .status-pill {
            margin-left: auto;
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
        .metadata-strip .field { margin-bottom: 0; }
        .metadata-strip small { font-size: 12px; line-height: 1.35; }
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
        .editor-secondary-collapsible > summary h3 { margin: 0; }

        .builder-shell {
            border: 1px solid #dbe3ef;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }
        .builder-header {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            flex-wrap:wrap;
            padding:10px 12px;
            border-bottom:1px solid #e5e7eb;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .builder-header h3 {
            margin:0;
            font-size: 14px;
            letter-spacing:.02em;
        }
        .builder-actions {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            align-items:center;
        }
        .builder-actions .btn,
        .builder-actions select {
            margin:0;
        }
        .segmented {
            display:inline-flex;
            gap:4px;
            padding:4px;
            border:1px solid #dbe3ef;
            border-radius:12px;
            background:#fff;
            flex-wrap:wrap;
        }
        .segmented button {
            border:0;
            background:transparent;
            border-radius:9px;
            padding:6px 10px;
            cursor:pointer;
            font-weight:600;
            color:#475467;
        }
        .segmented button.active { background:#0f172a; color:#fff; }

        .builder-layout {
            display:grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(340px, .55fr);
            min-height: 540px;
        }
        .builder-canvas {
            min-width:0;
            border-right:1px solid #e5e7eb;
            background:#f8fafc;
        }
        .builder-shell.is-structured .builder-subtabs,
        .builder-shell.is-structured .builder-panel[data-builder-subpanel="add"],
        .builder-shell.is-structured .builder-panel[data-builder-subpanel="presets"] {
            display:none;
        }
        .builder-subtabs {
            display: flex;
            gap: 6px;
            padding: 10px 12px 0;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }
        .builder-subtab {
            border: 1px solid #d0d5dd;
            background: #fff;
            border-radius: 999px;
            padding: 6px 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 12px;
            color: #334155;
        }
        .builder-subtab.active {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }
        .builder-panel {
            padding:12px;
            border-bottom:1px solid #e5e7eb;
            background:#fff;
        }
        .builder-panel h4 {
            margin:0 0 8px;
            font-size:13px;
            text-transform:uppercase;
            letter-spacing:.08em;
            color:#475467;
        }
        .palette-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap:8px;
        }
        .palette-button {
            border:1px solid #d0d5dd;
            background:#fff;
            border-radius:10px;
            padding:8px 10px;
            cursor:pointer;
            font-weight:600;
            font-size:13px;
            text-align:left;
        }
        .palette-button:hover { background:#f8fafc; }
        .preset-grid {
            display:grid;
            gap:8px;
        }
        .preset-card {
            border:1px solid #dbe3ef;
            background:#f8fafc;
            border-radius:10px;
            padding:10px;
        }
        .preset-card p { margin:4px 0 8px; color:#667085; font-size:12px; }
        .preset-card .preset-tags {
            display:flex;
            gap:6px;
            flex-wrap:wrap;
            margin:0 0 8px;
        }
        .preset-card .preset-tags span {
            font-size:11px;
            border:1px solid #dbe3ef;
            border-radius:999px;
            padding:2px 7px;
            background:#fff;
            color:#475467;
        }

        .blocks-list {
            padding:12px;
            display:grid;
            gap:10px;
            align-content:start;
        }
        .builder-structured-summary {
            display:grid;
            gap:14px;
            padding:14px;
            border:1px solid #dbe3ef;
            border-radius:16px;
            background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .builder-structured-summary-head {
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
        }
        .builder-structured-summary-head strong {
            display:block;
            font-size:16px;
            margin-bottom:4px;
        }
        .builder-structured-summary-head p {
            margin:0;
            color:#667085;
            line-height:1.5;
        }
        .builder-structured-summary-stats {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        .builder-structured-summary-stat {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:7px 10px;
            border-radius:999px;
            border:1px solid #dbe4f0;
            background:#fff;
            font-size:12px;
            font-weight:700;
            color:#334155;
        }
        .builder-structured-summary-actions {
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .builder-structured-summary-note {
            color:#667085;
            font-size:13px;
            line-height:1.45;
        }
        [data-builder-subpanel] { display: none; }
        [data-builder-subpanel].active { display: block; }
        [data-builder-subpanel="blocks"].active { display: grid; }
        .block-card {
            border:1px solid #dbe3ef;
            border-radius:12px;
            background:#fff;
            overflow:hidden;
        }
        .block-card.dragging {
            opacity:.6;
            border-color:#60a5fa;
        }
        .block-card.drop-target {
            outline: 2px solid #60a5fa;
            outline-offset: 1px;
        }
        .block-card-header {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:8px;
            padding:10px 12px;
            border-bottom:1px solid #eef2f7;
            background:linear-gradient(180deg, #fff, #f8fafc);
        }
        .block-title-wrap {
            display:flex;
            align-items:center;
            gap:8px;
            min-width:0;
        }
        .drag-handle {
            cursor:grab;
            color:#98a2b3;
            font-weight:700;
            user-select:none;
        }
        .block-index {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:22px;
            height:22px;
            border-radius:999px;
            background:#eff6ff;
            color:#1d4ed8;
            font-size:12px;
            font-weight:700;
        }
        .block-type {
            font-weight:700;
            color:#0f172a;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .block-controls {
            display:flex;
            align-items:center;
            gap:6px;
            flex-wrap:wrap;
        }
        .icon-btn {
            border:1px solid #d0d5dd;
            background:#fff;
            border-radius:8px;
            min-width:30px;
            height:30px;
            padding:0 8px;
            cursor:pointer;
            color:#111827;
            font-weight:700;
        }
        .icon-btn:hover { background:#f8fafc; }
        .icon-btn.danger { border-color:#fecaca; color:#b91c1c; background:#fff1f2; }

        .block-card-body {
            padding:12px;
            display:grid;
            gap:10px;
        }
        .block-card.collapsed .block-card-body { display:none; }

        .block-fields-grid {
            display:grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap:10px;
        }
        .block-subtle {
            color:#667085;
            font-size:12px;
        }
        .mini-toolbar {
            display:flex;
            gap:6px;
            flex-wrap:wrap;
            margin-bottom:6px;
        }
        .mini-toolbar .icon-btn {
            min-width:auto;
            padding:0 8px;
            height:28px;
            font-size:12px;
        }

        .builder-preview-shell {
            min-width:0;
            background:#f8fafc;
            display:flex;
            flex-direction:column;
        }
        .builder-preview-top {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            padding:10px 12px;
            border-bottom:1px solid #e5e7eb;
            background:#fff;
        }
        .builder-preview-wrap {
            padding:14px;
            overflow:auto;
        }
        .builder-preview-frame {
            width:100%;
            max-width:100%;
            margin:0 auto;
            border:1px solid #dbe3ef;
            border-radius:14px;
            background:#fff;
            overflow:hidden;
            box-shadow: 0 8px 24px rgba(15,23,42,.05);
            transition: width .2s ease;
        }
        .builder-preview-frame.device-mobile { width:390px; max-width:100%; }
        .builder-preview-frame.device-tablet { width:768px; max-width:100%; }
        .builder-preview-frame.device-desktop { width:100%; }
        .builder-preview-doc {
            background: linear-gradient(180deg, #f7f3eb, #f1ede4);
            min-height: 100%;
            padding: 14px;
        }
        .builder-preview-page {
            border:1px solid rgba(15,23,42,.08);
            border-radius:16px;
            background:rgba(255,255,255,.9);
            padding:16px;
            box-shadow: 0 10px 20px rgba(15,23,42,.04);
            color:#111827;
            line-height:1.55;
            font: 14px/1.55 ui-sans-serif, system-ui, sans-serif;
        }
        .builder-preview-page > *:first-child { margin-top:0; }
        .builder-preview-page > *:last-child { margin-bottom:0; }
        .builder-preview-page h1, .builder-preview-page h2, .builder-preview-page h3, .builder-preview-page h4 {
            line-height:1.1;
            letter-spacing:-.02em;
            margin: 1.1em 0 .5em;
        }
        .builder-preview-page h1 { font-size:28px; }
        .builder-preview-page h2 { font-size:22px; }
        .builder-preview-page h3 { font-size:18px; }
        .builder-preview-page p, .builder-preview-page ul, .builder-preview-page ol, .builder-preview-page table, .builder-preview-page figure { margin:0 0 12px; }
        .builder-preview-page img { max-width:100%; height:auto; border-radius:10px; }
        .builder-preview-page .cms-section { margin: 0 0 12px; }
        .builder-preview-page .cms-section--bg-surface {
            border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:10px;
        }
        .builder-preview-page .cms-section--bg-brand-soft {
            border:1px solid #fed7aa; border-radius:12px; background:#fff7ed; padding:10px;
        }
        .builder-preview-page .cms-columns {
            display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:10px; margin:0 0 12px;
        }
        .builder-preview-page .cms-col { min-width:0; }
        .builder-preview-page .cms-gallery { display:grid; gap:8px; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); }
        .builder-preview-page .cms-gallery img { width:100%; aspect-ratio:4/3; object-fit:cover; }
        .builder-preview-page .cms-video iframe { width:100%; aspect-ratio:16/9; border:0; border-radius:10px; background:#0f172a; }
        .builder-preview-page .cms-cta {
            display:inline-flex; align-items:center; gap:8px;
            background:linear-gradient(135deg,#d9472b,#ef7f1a); color:#fff;
            padding:9px 12px; border-radius:10px; text-decoration:none; font-weight:700;
        }
        .builder-preview-page .cms-faq details {
            border:1px solid #e5e7eb; border-radius:10px; padding:8px 10px; background:#fff; margin-bottom:8px;
        }
        .builder-preview-page .cms-divider { border:0; height:1px; background:#e5e7eb; }
        .builder-preview-page table { width:100%; border-collapse:collapse; border:1px solid #e5e7eb; display:block; overflow:auto; }
        .builder-preview-page td { padding:8px 10px; border-bottom:1px solid #e5e7eb; white-space:nowrap; }
        .builder-preview-page .cms-post-listing {
            border:1px dashed #cbd5e1; border-radius:10px; padding:10px; background:#f8fafc; color:#64748b;
        }
        .builder-preview-page .tc-hero {
            position: relative;
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 16px;
            padding: 16px;
            background:
                radial-gradient(circle at 12% 15%, rgba(217,71,43,.16), transparent 55%),
                radial-gradient(circle at 88% 20%, rgba(14,165,233,.14), transparent 52%),
                linear-gradient(180deg, #fff, #fffaf5);
            margin-bottom: 14px;
        }
        .builder-preview-page .tc-hero h1,
        .builder-preview-page .tc-hero h2,
        .builder-preview-page .tc-hero h3 { margin-top: 0; }
        .builder-preview-page .tc-kicker {
            margin: 0 0 8px;
            color: #b45309;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
        }
        .builder-preview-page .tc-lead {
            color: #475467;
            font-size: 15px;
            margin: 0 0 10px;
        }
        .builder-preview-page .tc-features-grid,
        .builder-preview-page .tc-cards-grid,
        .builder-preview-page .tc-pricing-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            margin: 10px 0 12px;
        }
        .builder-preview-page .tc-feature,
        .builder-preview-page .tc-card,
        .builder-preview-page .tc-price-card {
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 14px;
            padding: 12px;
            box-shadow: 0 6px 18px rgba(15,23,42,.04);
        }
        .builder-preview-page .tc-feature h3,
        .builder-preview-page .tc-card h3,
        .builder-preview-page .tc-price-card h3 { margin: 0 0 6px; font-size: 16px; }
        .builder-preview-page .tc-feature p,
        .builder-preview-page .tc-card p { margin: 0; color: #667085; font-size: 13px; line-height: 1.5; }
        .builder-preview-page .tc-card a {
            display: inline-flex;
            margin-top: 10px;
            font-weight: 700;
            color: #1d4ed8;
            text-decoration: none;
        }
        .builder-preview-page .tc-card a:hover { text-decoration: underline; }
        .builder-preview-page .tc-price-card.featured {
            border-color: #f59e0b;
            background: linear-gradient(180deg, #fff, #fffbeb);
            box-shadow: 0 10px 24px rgba(245,158,11,.10);
        }
        .builder-preview-page .tc-price {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -.03em;
            margin: 4px 0 8px;
        }
        .builder-preview-page .tc-price small {
            font-size: 12px;
            color: #667085;
            font-weight: 600;
            letter-spacing: normal;
        }
        .builder-preview-page .tc-price-card ul {
            margin: 0 0 10px;
            padding-left: 18px;
            color: #475467;
        }
        .builder-preview-page .tc-price-card .cms-cta {
            width: 100%;
            justify-content: center;
        }
        @media (max-width: 900px) {
            .builder-preview-page .cms-columns { grid-template-columns:1fr; }
            .builder-preview-page .cms-col { grid-column: span 1 !important; }
        }
        .builder-preview-placeholder {
            color:#667085;
            border:1px dashed #cbd5e1;
            border-radius:10px;
            padding:12px;
            background:#f8fafc;
            text-align:center;
        }
        .autosave-chip {
            display:inline-flex;
            align-items:center;
            gap:6px;
            border-radius:999px;
            padding:6px 10px;
            border:1px solid #d0d5dd;
            background:#fff;
            color:#475467;
            font-size:12px;
            font-weight:600;
        }
        .autosave-chip.saving { border-color:#93c5fd; background:#eff6ff; color:#1d4ed8; }
        .autosave-chip.restored { border-color:#86efac; background:#ecfdf3; color:#166534; }
        .autosave-summary {
            display:inline-flex;
            align-items:center;
            border:1px solid #dbe3ef;
            background:#fff;
            color:#475467;
            border-radius:999px;
            padding:6px 10px;
            font-size:12px;
            white-space:nowrap;
            max-width:42ch;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .autosave-action-btn {
            border:1px solid #d0d5dd;
            background:#fff;
            color:#111827;
            border-radius:10px;
            padding:8px 10px;
            cursor:pointer;
            font-weight:600;
            font-size:13px;
        }
        .autosave-action-btn:hover { background:#f8fafc; }
        .autosave-action-btn.danger { border-color:#fecaca; color:#b42318; background:#fff5f5; }

        .advanced-json textarea {
            font: 12px/1.45 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .seo-preview {
            border:1px solid #dbe3ef;
            border-radius:12px;
            background:#fff;
            padding:12px;
        }
        .seo-preview-title { margin:0 0 4px; color:#1a0dab; font-size:18px; line-height:1.25; }
        .seo-preview-url { margin:0 0 4px; color:#0f766e; font-size:13px; word-break:break-all; }
        .seo-preview-desc { margin:0; color:#4b5563; font-size:13px; }

        @media (max-width: 1320px) {
            .composer-shell {
                grid-template-columns: minmax(0, 1fr) 340px;
            }
            .builder-layout { grid-template-columns: 1fr; }
            .builder-canvas { border-right:0; border-bottom:1px solid #e5e7eb; }
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
        }
        @media (max-width: 900px) {
            .metadata-strip-grid,
            .block-fields-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $isEdit ? 'Редактирование страницы' : 'Создание страницы' }}</h1>
            <p>
                {{ $isEdit ? 'Страница #'.$page->id : 'Создание локализованной страницы.' }}
                <span class="muted">Используйте slug <span class="mono">home</span> для главной страницы локали.</span>
            </p>
        </div>
        <div class="actions">
            <button type="button" class="btn" data-open-create-template-modal>Создать из шаблона</button>
            <button type="button" class="btn" data-open-save-template-modal>Сохранить как шаблон</button>
            <button type="button" class="btn btn-ghost" data-composer-sidebar-toggle aria-expanded="true" title="Свернуть/развернуть инспектор">Инспектор</button>
            <a href="{{ route('admin.pages.index') }}" class="btn">Назад к страницам</a>
            @if($isEdit)
                @php $defaultT = $translationsByLocale[$defaultLocale] ?? null; @endphp
                @if($defaultT && $page->status === 'published')
                    <a class="btn" href="{{ url('/'.$defaultLocale.'/'.$defaultT->slug) }}" target="_blank" rel="noreferrer">Открыть страницу</a>
                @endif
            @endif
            @if($templateSourceName !== '')
                <span class="autosave-summary" title="Шаблон-источник">Шаблон: {{ $templateSourceName }}</span>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('admin.pages.update', $page) : route('admin.pages.store') }}" id="page-form">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="composer-shell" data-composer-shell="page">
            <section class="panel composer-main composer-main-panel" data-composer-main>
                <h2 style="margin-top:0;">Конструктор страницы</h2>
                <p class="muted" style="margin-top:0;">Визуальный конструктор блоков с живым превью. Внизу доступен исходный JSON для продвинутого режима.</p>

                <div class="locale-tabs" data-locale-tabs="page">
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
                <p class="locale-tip">Локаль считается используемой, если в ней есть блоки/контент, SEO-поля или custom code в <span class="mono">&lt;head&gt;</span>. Для используемой локали обязательны и <strong>Заголовок</strong>, и <strong>Slug</strong>.</p>

                @foreach($locales as $locale)
                    @php
                        $t = $translationsByLocale[$locale] ?? null;
                        $oldTranslation = old('translations.'.$locale, []);
                        $title = $oldTranslation['title'] ?? $t?->title ?? '';
                        $slug = $oldTranslation['slug'] ?? $t?->slug ?? ($locale === $defaultLocale ? 'home' : '');
                        $richHtml = $oldTranslation['rich_html'] ?? $t?->rich_html ?? '';
                        $blocksJson = $oldTranslation['blocks_json'] ?? (!empty($t?->content_blocks) ? json_encode($t->content_blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
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
                        <div class="metadata-strip" data-page-metadata-strip="{{ $locale }}">
                            <div class="metadata-strip-grid">
                                <div class="field">
                                    <label for="page-{{ $locale }}-title">Заголовок ({{ strtoupper($locale) }})</label>
                                    <input
                                        id="page-{{ $locale }}-title"
                                        type="text"
                                        name="translations[{{ $locale }}][title]"
                                        value="{{ $title }}"
                                        data-page-title="{{ $locale }}"
                                    >
                                </div>
                                <div class="field">
                                    <label for="page-{{ $locale }}-slug">Slug</label>
                                    <div class="inline">
                                        <input
                                            id="page-{{ $locale }}-slug"
                                            type="text"
                                            name="translations[{{ $locale }}][slug]"
                                            value="{{ $slug }}"
                                            data-page-slug="{{ $locale }}"
                                        >
                                        <button type="button" class="btn btn-small" data-slug-generate="{{ $locale }}">Сгенерировать</button>
                                    </div>
                                    <small>Публичный URL: <span class="mono">/{{ $locale }}/&lt;slug&gt;</span></small>
                                </div>
                            </div>
                        </div>

                        <div class="builder-shell" data-page-builder="{{ $locale }}">
                            <div class="builder-header">
                                <h3>Конструктор · {{ strtoupper($locale) }}</h3>
                                <div class="builder-actions">
                                    <button type="button" class="btn btn-ghost" data-builder-open-fullscreen>Визуальный конструктор</button>
                                    <div class="segmented" data-builder-view-toggle>
                                        <button type="button" class="active" data-builder-view="split">Разделено</button>
                                        <button type="button" data-builder-view="builder">Конструктор</button>
                                        <button type="button" data-builder-view="preview">Превью</button>
                                    </div>
                                    <div class="segmented" data-builder-device-toggle>
                                        <button type="button" class="active" data-builder-device="desktop">Десктоп</button>
                                        <button type="button" data-builder-device="tablet">Планшет</button>
                                        <button type="button" data-builder-device="mobile">Моб.</button>
                                    </div>
                                </div>
                            </div>

                            <div class="builder-layout" data-builder-layout>
                                <div class="builder-canvas" data-builder-pane="builder">
                                    <div class="builder-subtabs" data-builder-subtabs>
                                        <button type="button" class="builder-subtab active" data-builder-subtab="blocks">Блоки</button>
                                        <button type="button" class="builder-subtab" data-builder-subtab="add">Добавить</button>
                                        <button type="button" class="builder-subtab" data-builder-subtab="presets">Пресеты</button>
                                    </div>
                                    <div class="builder-panel" data-builder-subpanel="add">
                                        <h4>Быстро добавить блок</h4>
                                        <div class="palette-grid">
                                            @php
                                                $quickBlockLabels = [
                                                    'heading' => 'Заголовок',
                                                    'rich_text' => 'Текст (HTML)',
                                                    'image' => 'Изображение',
                                                    'gallery' => 'Галерея',
                                                    'cta' => 'CTA',
                                                    'divider' => 'Разделитель',
                                                    'faq' => 'FAQ',
                                                    'post_listing' => 'Список постов',
                                                    'module_widget' => 'Виджет модуля',
                                                    'custom_code_embed' => 'Кастомный код',
                                                ];
                                            @endphp
                                            @foreach(array_keys($quickBlockLabels) as $type)
                                                @if(in_array($type, $allowedBlockTypes, true))
                                                    <button type="button" class="palette-button" data-add-block="{{ $type }}">{{ $quickBlockLabels[$type] ?? $type }}</button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="builder-panel" data-builder-subpanel="presets">
                                        <h4>Пресеты секций</h4>
                                        <div class="preset-grid">
                                            <div class="preset-card">
                                                <strong>Hero + CTA</strong>
                                                <p>Заголовок, текст и кнопка для лендинга.</p>
                                                <div class="preset-tags"><span>Hero</span><span>CTA</span></div>
                                                <button type="button" class="btn btn-small" data-add-preset="hero">Вставить пресет</button>
                                            </div>
                                            <div class="preset-card">
                                                <strong>Текстовая секция</strong>
                                                <p>Заголовок + rich text + разделитель.</p>
                                                <div class="preset-tags"><span>Контент</span></div>
                                                <button type="button" class="btn btn-small" data-add-preset="text-section">Вставить пресет</button>
                                            </div>
                                            <div class="preset-card">
                                                <strong>Сетка преимуществ</strong>
                                                <p>Секция преимуществ в стиле лендинга (3 карточки).</p>
                                                <div class="preset-tags"><span>Features</span><span>Карточки</span></div>
                                                <button type="button" class="btn btn-small" data-add-preset="features">Вставить пресет</button>
                                            </div>
                                            <div class="preset-card">
                                                <strong>Тарифные планы</strong>
                                                <p>Тарифные карточки с CTA, включая featured plan.</p>
                                                <div class="preset-tags"><span>Pricing</span><span>CTA</span></div>
                                                <button type="button" class="btn btn-small" data-add-preset="pricing">Вставить пресет</button>
                                            </div>
                                            <div class="preset-card">
                                                <strong>Карточки / Кейсы</strong>
                                                <p>Карточки кейсов или сервисов с ссылками.</p>
                                                <div class="preset-tags"><span>Карточки</span><span>Сетка</span></div>
                                                <button type="button" class="btn btn-small" data-add-preset="cards">Вставить пресет</button>
                                            </div>
                                            <div class="preset-card">
                                                <strong>FAQ-секция</strong>
                                                <p>Заголовок + FAQ блок.</p>
                                                <div class="preset-tags"><span>FAQ</span></div>
                                                <button type="button" class="btn btn-small" data-add-preset="faq">Вставить пресет</button>
                                            </div>
                                            <div class="preset-card">
                                                <strong>Блог-лендинг</strong>
                                                <p>Заголовок, описание и динамический список постов.</p>
                                                <div class="preset-tags"><span>Блог</span><span>Dynamic</span></div>
                                                <button type="button" class="btn btn-small" data-add-preset="blog">Вставить пресет</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="blocks-list active" data-builder-subpanel="blocks" data-blocks-list></div>
                                </div>

                                <div class="builder-preview-shell" data-builder-pane="preview">
                                    <div class="builder-preview-top">
                                        <span class="muted" style="font-size:13px;">Предпросмотр страницы (без сохранения)</span>
                                        <span class="muted" style="font-size:12px;">Рендер клиентский, сервер валидирует при сохранении</span>
                                    </div>
                                    <div class="builder-preview-wrap">
                                        <div class="builder-preview-frame device-desktop" data-builder-preview-frame>
                                            <div class="builder-preview-doc">
                                                <div class="builder-preview-page" data-builder-preview></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="builder-panel advanced-json" data-page-locale-advanced-panel="{{ $locale }}">
                                <details>
                                    <summary><strong>Продвинутый режим: исходный JSON / fallback HTML</strong></summary>
                                    <div style="margin-top:10px; display:grid; gap:12px;">
                                        <div class="field">
                                            <label for="page-{{ $locale }}-blocks-json">JSON блоков (источник истины)</label>
                                            <textarea
                                                id="page-{{ $locale }}-blocks-json"
                                                rows="10"
                                                class="mono"
                                                name="translations[{{ $locale }}][blocks_json]"
                                                data-blocks-json
                                            >{{ $blocksJson }}</textarea>
                                            <div class="inline">
                                                <button type="button" class="btn btn-small" data-apply-raw-json>Применить JSON к конструктору</button>
                                                <button type="button" class="btn btn-small" data-format-raw-json>Форматировать JSON</button>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label for="page-{{ $locale }}-rich-html">Быстрый fallback Rich HTML</label>
                                            <textarea
                                                id="page-{{ $locale }}-rich-html"
                                                rows="6"
                                                name="translations[{{ $locale }}][rich_html]"
                                                data-rich-html-fallback
                                            >{{ $richHtml }}</textarea>
                                            <small>Используется как быстрый источник контента, если вы переносите HTML вручную. Кнопка ниже добавит его в структуру страницы как `rich_text` блок.</small>
                                            <div class="inline" style="margin-top:8px;">
                                                <button type="button" class="btn btn-small" data-convert-fallback-to-block>Добавить fallback HTML в rich text блок</button>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>

                        <details class="panel editor-secondary-collapsible" data-page-locale-seo-panel="{{ $locale }}">
                            <summary>
                                <h3>SEO ({{ strtoupper($locale) }})</h3>
                                <span class="muted" style="font-size:12px;">Развернуть</span>
                            </summary>
                            <div style="margin-top:12px;">
                            <div class="grid cols-2">
                                <div class="field">
                                    <label for="page-{{ $locale }}-meta-title">Meta title</label>
                                    <input
                                        id="page-{{ $locale }}-meta-title"
                                        type="text"
                                        name="translations[{{ $locale }}][meta_title]"
                                        value="{{ $metaTitle }}"
                                        data-page-meta-title="{{ $locale }}"
                                    >
                                </div>
                                <div class="field">
                                    <label for="page-{{ $locale }}-canonical">Canonical URL</label>
                                    <input
                                        id="page-{{ $locale }}-canonical"
                                        type="text"
                                        name="translations[{{ $locale }}][canonical_url]"
                                        value="{{ $canonicalUrl }}"
                                        data-page-canonical="{{ $locale }}"
                                    >
                                </div>
                            </div>
                            <div class="field">
                                <label for="page-{{ $locale }}-meta-desc">Meta description</label>
                                <textarea
                                    id="page-{{ $locale }}-meta-desc"
                                    rows="3"
                                    name="translations[{{ $locale }}][meta_description]"
                                    data-page-meta-description="{{ $locale }}"
                                >{{ $metaDescription }}</textarea>
                            </div>
                            <div class="field">
                                <label for="page-{{ $locale }}-custom-head">Кастомный код в &lt;head&gt; (advanced)</label>
                                <textarea
                                    id="page-{{ $locale }}-custom-head"
                                    rows="6"
                                    class="mono"
                                    name="translations[{{ $locale }}][custom_head_html]"
                                >{{ $customHeadHtml }}</textarea>
                                <small>Вставляется в <span class="mono">&lt;head&gt;</span> страницы. Подходит для verification tags, custom meta, structured scripts, styles.</small>
                            </div>
                            <div class="seo-preview" data-page-seo-preview="{{ $locale }}">
                                <p class="seo-preview-title" data-seo-preview-title>{{ $metaTitle !== '' ? $metaTitle : ($title !== '' ? $title : 'Заголовок страницы') }}</p>
                                <p class="seo-preview-url" data-seo-preview-url>{{ url('/'.$locale.'/'.($slug !== '' ? $slug : 'slug')) }}</p>
                                <p class="seo-preview-desc" data-seo-preview-desc>{{ $metaDescription !== '' ? $metaDescription : 'Превью meta description.' }}</p>
                            </div>
                            </div>
                        </details>
                    </section>
                @endforeach
            </section>

            <aside class="composer-sidebar" data-composer-sidebar="page">
                <section class="panel composer-sidebar-card">
                    <div class="composer-sidebar-head">
                        <button type="button" class="composer-sidebar-toggle" data-composer-sidebar-toggle aria-expanded="true" title="Свернуть/развернуть инспектор">›</button>
                        <div class="composer-sidebar-tabs" role="tablist" aria-label="Инспектор страницы">
                            <button type="button" class="composer-sidebar-tab active" data-composer-sidebar-tab="publish" role="tab" aria-selected="true">Публикация</button>
                            <button type="button" class="composer-sidebar-tab {{ !empty($sidebarTabErrorMap['seo']) ? 'has-error' : '' }}" data-composer-sidebar-tab="seo" role="tab" aria-selected="false">SEO</button>
                            <button type="button" class="composer-sidebar-tab {{ !empty($sidebarTabErrorMap['advanced']) ? 'has-error' : '' }}" data-composer-sidebar-tab="advanced" role="tab" aria-selected="false">Advanced</button>
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
                                        <label for="page-status">Статус</label>
                                        <select id="page-status" name="status">
                                            @foreach(config('cms.statuses', ['draft','published']) as $status)
                                                <option value="{{ $status }}" @selected(old('status', $page->status) === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label for="page-type">Тип страницы</label>
                                        <input id="page-type" type="text" name="page_type" value="{{ old('page_type', $page->page_type ?: 'landing') }}">
                                    </div>
                                    <div class="actions">
                                        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Сохранить изменения' : 'Создать страницу' }}</button>
                                    </div>
                                </div>
                            </section>
                            <section class="composer-sidebar-section" data-composer-sidebar-section="seo" role="tabpanel">
                                <div class="composer-sidebar-box">
                                    <h3>SEO (активная локаль)</h3>
                                    <p class="muted" style="margin:0 0 10px; font-size:12px;">SEO-панель выбранной локали отображается здесь.</p>
                                    <div data-page-seo-sidebar-host></div>
                                </div>
                            </section>
                            <section class="composer-sidebar-section" data-composer-sidebar-section="advanced" role="tabpanel">
                                <div class="composer-sidebar-box">
                                    <h3>Advanced JSON / Fallback</h3>
                                    <div class="grid" style="gap:8px;">
                                        <div class="muted" style="font-size:12px;" data-page-advanced-summary>Сводка недоступна.</div>
                                        <div class="actions">
                                            <button type="button" class="btn btn-small" data-page-advanced-open>Открыть Advanced JSON секцию</button>
                                            <button type="button" class="btn btn-small" data-page-advanced-format>Форматировать JSON</button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                            @if($isEdit)
                                <section class="composer-sidebar-section" data-composer-sidebar-section="actions" role="tabpanel">
                                    <div class="composer-sidebar-box">
                                        <h3>Публикационные действия</h3>
                                        <div class="actions">
                                            <button type="submit" form="page-publish-now-form" class="btn btn-primary">Опубликовать сейчас</button>
                                            <button type="submit" form="page-unpublish-form" class="btn">Вернуть в черновик</button>
                                        </div>
                                    </div>
                                    <div class="composer-sidebar-box">
                                        <h3 style="color:#b42318;">Опасная зона</h3>
                                        <button type="submit" form="page-delete-form" class="btn btn-danger">Удалить страницу</button>
                                    </div>
                                </section>
                                <section class="composer-sidebar-section" data-composer-sidebar-section="preview" role="tabpanel">
                                    <div class="composer-sidebar-box">
                                        <h3>Предпросмотр</h3>
                                        <div class="field">
                                            <label for="preview-locale">Локаль предпросмотра</label>
                                            <select id="preview-locale" name="locale" form="page-preview-token-form">
                                                @foreach($locales as $locale)
                                                    <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" form="page-preview-token-form" class="btn">Сгенерировать ссылку предпросмотра (24ч)</button>
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
                                            <label for="schedule-action">Запланировать действие</label>
                                            <select id="schedule-action" name="action" form="page-schedule-form">
                                                <option value="publish">Опубликовать</option>
                                                <option value="unpublish">Снять с публикации</option>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="schedule-due">Выполнить в</label>
                                            <input id="schedule-due" type="datetime-local" name="due_at" required form="page-schedule-form">
                                        </div>
                                        <button type="submit" form="page-schedule-form" class="btn">Создать расписание</button>
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
                            <span class="status-pill status-{{ e(old('status', $page->status ?: 'draft')) }}" data-composer-status-badge>{{ old('status', $page->status ?: 'draft') }}</span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </form>

    <div class="template-modal" data-create-template-modal style="position:fixed; inset:0; z-index:95; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(15,23,42,.48);">
        <div class="panel" style="width:min(560px,100%); margin:0;">
            <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
                <h3 style="margin:0;">Создать страницу из шаблона</h3>
                <button type="button" class="btn btn-small" data-close-create-template-modal>Закрыть</button>
            </div>
            <form method="GET" action="{{ route('admin.pages.create') }}" class="grid" style="gap:10px;">
                <div class="field" style="margin:0;">
                    <label for="page-template-create-select">Шаблон</label>
                    <select id="page-template-create-select" name="from_template" required>
                        <option value="">Выберите шаблон…</option>
                        @foreach(($templates ?? collect()) as $templateItem)
                            <option value="{{ $templateItem->id }}">#{{ $templateItem->id }} · {{ $templateItem->name }}</option>
                        @endforeach
                    </select>
                    <small>Новая страница будет создана как черновик с уникальными slug.</small>
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
                <h3 style="margin:0;">Сохранить страницу как шаблон</h3>
                <button type="button" class="btn btn-small" data-close-save-template-modal>Закрыть</button>
            </div>
            <form method="POST" action="{{ route('admin.templates.store') }}" class="grid" style="gap:10px;" data-save-template-form>
                @csrf
                <input type="hidden" name="entity_type" value="page">
                <input type="hidden" name="payload_json" data-template-payload-json>
                <div class="field" style="margin:0;">
                    <label for="page-template-name">Название шаблона</label>
                    <input id="page-template-name" type="text" name="name" maxlength="190" required>
                </div>
                <div class="field" style="margin:0;">
                    <label for="page-template-description">Описание</label>
                    <textarea id="page-template-description" name="description" rows="3" maxlength="2000"></textarea>
                </div>
                <div class="inline" style="justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
                </div>
            </form>
        </div>
    </div>

    @if($isEdit)
        <div style="display:none" aria-hidden="true">
            <form id="page-publish-now-form" method="POST" action="{{ route('admin.pages.publish', $page) }}">
                @csrf
            </form>
            <form id="page-unpublish-form" method="POST" action="{{ route('admin.pages.unpublish', $page) }}">
                @csrf
            </form>
            <form id="page-preview-token-form" method="POST" action="{{ route('admin.pages.preview-token', $page) }}">
                @csrf
            </form>
            <form id="page-schedule-form" method="POST" action="{{ route('admin.pages.schedule', $page) }}">
                @csrf
            </form>
            <form id="page-delete-form" method="POST" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="Удалить эту страницу?">
                @csrf
                @method('DELETE')
            </form>
        </div>
    @endif

    @include('admin.partials.media-picker', ['mediaPickerAssets' => $assets])
    <script type="application/json" id="testocms-page-editor-boot">{!! json_encode([
        'supportedLocales' => array_values($locales),
        'templateSourceName' => $templateSourceName,
        'allowedBlockTypes' => array_values($allowedBlockTypes),
        'isEditMode' => (bool) $page,
        'moduleWidgetCatalog' => array_values($moduleWidgetCatalog ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'editor-shared.js']) }}"></script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'page-form.js']) }}"></script>
    @include('admin.pages.partials.fullscreen-builder')
    <script src="{{ route('admin.runtime.show', ['runtime' => 'page-fullscreen.js']) }}"></script>
@endsection
