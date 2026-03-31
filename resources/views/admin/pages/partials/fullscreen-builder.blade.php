<div class="page-fullscreen-overlay" data-page-fullscreen-overlay data-page-fullscreen-stage-render-url="{{ route('admin.pages.stage.render') }}" hidden aria-hidden="true">
    <div class="page-fullscreen-shell" role="dialog" aria-modal="true" aria-label="Визуальный конструктор страницы">
        <div class="fs-topbar">
            <div class="fs-topbar-left">
                <button type="button" class="btn btn-ghost" data-page-fullscreen-close>Закрыть</button>
                <div class="segmented fs-locale-tabs" data-page-fullscreen-locale-tabs>
                    @foreach($locales as $locale)
                        <button type="button" data-page-fullscreen-locale="{{ $locale }}">{{ strtoupper($locale) }}</button>
                    @endforeach
                </div>
                <div class="fs-page-meta">
                    <strong data-fs-page-title>Страница</strong>
                    <span class="mono" data-fs-page-slug>/ru/slug</span>
                </div>
            </div>
            <div class="fs-topbar-right">
                <div class="segmented" data-page-fullscreen-device-toggle>
                    <button type="button" data-fs-device="desktop" class="active">Десктоп</button>
                    <button type="button" data-fs-device="tablet">Планшет</button>
                    <button type="button" data-fs-device="mobile">Моб.</button>
                </div>
                <span class="autosave-chip" data-page-fullscreen-sync-state>Синхронизировано с формой</span>
                <button type="button" class="btn" data-page-fullscreen-save>Сохранить</button>
                <button type="button" class="btn btn-primary" data-page-fullscreen-save-publish>Сохранить и опубликовать</button>
            </div>
        </div>

        <div class="fs-body">
            <aside class="fs-side fs-side-left">
                <div class="fs-side-head">
                    <div class="segmented" data-page-fullscreen-left-tabs>
                        <button type="button" data-fs-left-tab="elements" class="active">Элементы</button>
                        <button type="button" data-fs-left-tab="presets">Пресеты</button>
                        <button type="button" data-fs-left-tab="structure">Структура</button>
                    </div>
                </div>
                <div class="fs-side-body">
                    <section class="fs-side-panel active" data-fs-left-panel="elements">
                        <div class="fs-panel-block">
                            <h4>Секции и колонки</h4>
                            <div class="fs-element-grid" data-page-fullscreen-elements></div>
                        </div>
                    </section>
                    <section class="fs-side-panel" data-fs-left-panel="presets">
                        <div class="fs-panel-block">
                            <h4>Пресеты секций</h4>
                            <div class="fs-preset-list" data-page-fullscreen-presets></div>
                        </div>
                    </section>
                    <section class="fs-side-panel" data-fs-left-panel="structure">
                        <div class="fs-panel-block">
                            <h4>Навигатор структуры</h4>
                            <div class="fs-structure-tree" data-page-fullscreen-structure></div>
                        </div>
                    </section>
                </div>
            </aside>

            <main class="fs-canvas-wrap">
                <div class="fs-canvas-top">
                    <div class="fs-canvas-top-left">
                        <div class="muted" style="font-size:12px;" data-page-fullscreen-locale-badge>Локаль: RU</div>
                        <div class="muted" style="font-size:12px;" data-page-fullscreen-layout-state>Секции и колонки</div>
                    </div>
                    <div class="segmented fs-center-mode-toggle" data-page-fullscreen-center-toggle>
                        <button type="button" data-fs-center-mode="stage" class="active">Сцена</button>
                        <button type="button" data-fs-center-mode="canvas">Структура</button>
                    </div>
                </div>
                <div class="fs-stage-wrap" data-page-fullscreen-stage-wrap>
                    <iframe class="fs-stage-iframe" data-page-fullscreen-stage-iframe title="Живая сцена страницы"></iframe>
                    <div class="fs-stage-overlay" data-page-fullscreen-stage-overlay></div>
                    <div class="fs-stage-banner fs-hidden" data-page-fullscreen-stage-banner></div>
                </div>
                <div class="fs-canvas-viewport" data-page-fullscreen-canvas-viewport>
                <div class="fs-canvas-frame fs-device-desktop" data-page-fullscreen-frame>
                    <div class="fs-canvas" data-page-fullscreen-canvas></div>
                </div>
                </div>
                <div class="fs-col-resize-overlay" data-fs-col-resize-overlay hidden>6 / 6</div>
            </main>

            <aside class="fs-side fs-side-right">
                <div class="fs-side-head">
                    <div class="segmented" data-page-fullscreen-right-tabs>
                        <button type="button" data-fs-right-tab="content" class="active">Контент</button>
                        <button type="button" data-fs-right-tab="layout">Схема</button>
                        <button type="button" data-fs-right-tab="style">Стиль</button>
                        <button type="button" data-fs-right-tab="seo">SEO</button>
                        <button type="button" data-fs-right-tab="publish">Публикация</button>
                    </div>
                </div>
                <div class="fs-side-body">
                    <section class="fs-side-panel active" data-fs-right-panel="content">
                        <div class="fs-panel-block" data-page-fullscreen-inspector>
                            <p class="muted">Выберите элемент на сцене.</p>
                        </div>
                    </section>
                    <section class="fs-side-panel" data-fs-right-panel="layout">
                        <div class="fs-panel-block" data-page-fullscreen-layout-panel>
                            <p class="muted">Выберите секцию или группу колонок.</p>
                        </div>
                    </section>
                    <section class="fs-side-panel" data-fs-right-panel="style">
                        <div class="fs-panel-block" data-page-fullscreen-style-panel>
                            <p class="muted">Базовые визуальные настройки доступны для секции на вкладке «Схема».</p>
                        </div>
                    </section>
                    <section class="fs-side-panel" data-fs-right-panel="seo">
                        <div class="fs-panel-block" data-page-fullscreen-seo-panel></div>
                    </section>
                    <section class="fs-side-panel" data-fs-right-panel="publish">
                        <div class="fs-panel-block" data-page-fullscreen-publish-panel></div>
                    </section>
                </div>
            </aside>
        </div>
    </div>
</div>

<style>
    .page-fullscreen-overlay {
        position: fixed;
        inset: 0;
        z-index: 1200;
        background: rgba(10, 14, 22, .62);
        backdrop-filter: blur(6px);
        padding: 10px;
    }
    .page-fullscreen-overlay[hidden] { display: none !important; }
    .page-fullscreen-shell {
        height: calc(100vh - 20px);
        border-radius: 16px;
        background: #eef2f7;
        border: 1px solid rgba(255,255,255,.35);
        box-shadow: 0 30px 80px rgba(2,6,23,.35);
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        overflow: hidden;
    }
    .fs-topbar {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        padding: 10px 12px;
        border-bottom: 1px solid #dbe3ef;
        background: linear-gradient(180deg, #fff, #f8fafc);
        flex-wrap: wrap;
    }
    .fs-topbar-left, .fs-topbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        min-width: 0;
    }
    .fs-page-meta {
        display: grid;
        gap: 2px;
        padding: 6px 10px;
        border: 1px solid #d0d5dd;
        background: #fff;
        border-radius: 10px;
        min-width: 180px;
        max-width: 36ch;
    }
    .fs-page-meta strong {
        font-size: 12px;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fs-page-meta span { font-size: 11px; color:#64748b; }
    .fs-body {
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr) 360px;
        min-height: 0;
        height: 100%;
    }
    .fs-side {
        min-width: 0;
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        background: #fff;
        min-height: 0;
    }
    .fs-side-left { border-right: 1px solid #dbe3ef; }
    .fs-side-right { border-left: 1px solid #dbe3ef; }
    .fs-side-head {
        padding: 8px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #fff, #f8fafc);
    }
    .fs-side-head .segmented { width: 100%; }
    .fs-side-head .segmented button { flex: 1 1 auto; }
    .fs-side-body {
        min-height: 0;
        height: 100%;
        overflow: auto;
        padding: 8px;
        display: grid;
        gap: 8px;
        align-content: start;
    }
    .fs-side-panel { display: none; }
    .fs-side-panel.active { display: block; }
    .fs-panel-block {
        border: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 12px;
        padding: 10px;
        display: grid;
        gap: 8px;
    }
    .fs-panel-block h4 {
        margin: 0;
        font-size: 12px;
        color: #475467;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .fs-panel-block h5 { margin: 0; font-size: 12px; color:#334155; }
    .fs-element-grid {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .fs-element-btn {
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        background: #fff;
        padding: 10px;
        text-align: left;
        cursor: pointer;
        font-weight: 700;
        color: #0f172a;
        font-size: 12px;
    }
    .fs-element-btn small { display:block; color:#667085; font-weight: 600; margin-top: 3px; }
    .fs-preset-list {
        display: grid;
        gap: 8px;
    }
    .fs-preset-item {
        border: 1px solid #dbe3ef;
        background: #f8fafc;
        border-radius: 10px;
        padding: 10px;
        display: grid;
        gap: 6px;
    }
    .fs-preset-item p { margin:0; font-size:12px; color:#667085; }
    .fs-preset-item .btn { justify-self: start; }
    .fs-structure-tree {
        display: grid;
        gap: 6px;
        font-size: 12px;
    }
    .fs-structure-row {
        display:flex;
        align-items:center;
        gap:6px;
        padding: 6px 8px;
        border: 1px solid #e5e7eb;
        border-radius: 9px;
        background: #fff;
        cursor:pointer;
    }
    .fs-structure-row.selected {
        border-color: #60a5fa;
        background: #eff6ff;
    }
    .fs-structure-row .tag-mini {
        display:inline-flex;
        align-items:center;
        border-radius:999px;
        padding:2px 6px;
        font-size:10px;
        border:1px solid #dbe3ef;
        background:#fff;
        color:#475467;
    }
    .fs-canvas-wrap {
        min-width: 0;
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        background: radial-gradient(circle at 20% 0%, rgba(59,130,246,.08), transparent 55%), #eef2f7;
        position: relative;
        min-height: 0;
    }
    .fs-canvas-top {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        padding: 8px 10px;
        border-bottom: 1px solid #dbe3ef;
        background: rgba(255,255,255,.65);
    }
    .fs-canvas-top-left {
        display:flex;
        align-items:center;
        gap:10px;
        min-width:0;
        flex-wrap:wrap;
    }
    .fs-center-mode-toggle {
        flex: 0 0 auto;
    }
    .fs-canvas-viewport {
        overflow: auto;
        padding: 14px;
        min-height: 0;
        height: 100%;
    }
    .fs-canvas-frame {
        width: 100%;
        margin: 0 auto;
        background: #fff;
        border-radius: 16px;
        border: 1px solid #dbe3ef;
        box-shadow: 0 10px 30px rgba(15,23,42,.06);
        min-height: 1px;
        transition: width .2s ease;
    }
    .fs-stage-wrap {
        position: absolute;
        inset: 45px 0 0 0;
        min-height: 0;
        z-index: 1;
        padding: 14px;
        display: grid;
        grid-template-rows: minmax(0, 1fr);
        background: transparent;
    }
    .fs-stage-wrap.fs-hidden { display: none !important; }
    .fs-stage-iframe {
        width: 100%;
        height: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 30px rgba(15,23,42,.06);
    }
    .fs-stage-overlay {
        position: absolute;
        inset: 14px;
        pointer-events: none;
        border-radius: 16px;
        overflow: hidden;
        z-index: 2;
    }
    .fs-stage-box {
        position: absolute;
        border: 2px solid rgba(59,130,246,.95);
        border-radius: 10px;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.7);
        background: rgba(59,130,246,.06);
        pointer-events: none;
    }
    .fs-stage-box.hover {
        border-color: rgba(14,165,233,.85);
        background: rgba(14,165,233,.05);
    }
    .fs-stage-drop-box {
        border-color: rgba(34,197,94,.95);
        background: rgba(34,197,94,.08);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.65), 0 0 0 2px rgba(34,197,94,.22);
    }
    .fs-stage-drop-label {
        position:absolute;
        z-index:3;
        pointer-events:none;
        border-radius:999px;
        border:1px solid rgba(255,255,255,.24);
        background: rgba(22,163,74,.92);
        color:#fff;
        font-size:11px;
        font-weight:700;
        line-height:1;
        padding:6px 8px;
        box-shadow: 0 8px 24px rgba(2,6,23,.22);
        white-space:nowrap;
        transform: translateY(-120%);
        max-width:min(80%, 340px);
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .fs-stage-actions {
        position: absolute;
        display:flex;
        align-items:center;
        gap:4px;
        padding:4px;
        border-radius:10px;
        border:1px solid rgba(255,255,255,.25);
        background: rgba(15,23,42,.88);
        box-shadow: 0 8px 24px rgba(2,6,23,.24);
        pointer-events: auto;
        transform: translateY(-110%);
    }
    .fs-stage-actions .fs-node-btn {
        border-color: rgba(255,255,255,.14);
        background: rgba(255,255,255,.08);
        color: #fff;
    }
    .fs-stage-actions .fs-node-btn:hover { background: rgba(255,255,255,.14); }
    .fs-stage-actions .fs-node-btn.danger {
        background: rgba(220,38,38,.18);
        border-color: rgba(248,113,113,.35);
        color: #fee2e2;
    }
    .fs-stage-inline-panel {
        position: absolute;
        width: min(560px, calc(100% - 24px));
        max-height: min(54vh, 460px);
        overflow: auto;
        border-radius: 12px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.96);
        backdrop-filter: blur(8px);
        box-shadow: 0 16px 40px rgba(2,6,23,.18);
        padding: 10px;
        pointer-events: auto;
        display: grid;
        gap: 8px;
    }
    .fs-stage-inline-head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
    }
    .fs-stage-inline-title {
        font-size: 12px;
        font-weight: 700;
        color:#334155;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .fs-stage-inline-rich {
        min-height: 180px;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        background: #fff;
        padding: 10px;
        color:#0f172a;
        line-height: 1.55;
    }
    .fs-stage-inline-rich:focus,
    .fs-stage-inline-input:focus {
        outline: 2px solid rgba(96,165,250,.32);
        border-color: #60a5fa;
    }
    .fs-stage-inline-input {
        width: 100%;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        background: #fff;
        padding: 8px 10px;
        font: inherit;
    }
    .fs-stage-inline-row {
        display:grid;
        gap:8px;
    }
    .fs-stage-inline-toolbar {
        display:flex;
        gap:6px;
        flex-wrap:wrap;
        border:1px solid #e5e7eb;
        border-radius:10px;
        background:#fff;
        padding:6px;
    }
    .fs-stage-inline-toolbar .fs-node-btn {
        min-width: auto;
        height: 30px;
        padding: 0 8px;
        font-size: 12px;
    }
    .fs-stage-banner {
        position: absolute;
        left: 20px;
        right: 20px;
        bottom: 20px;
        z-index: 3;
        border-radius: 12px;
        border: 1px solid #fecaca;
        background: rgba(255,255,255,.95);
        color: #991b1b;
        padding: 10px 12px;
        box-shadow: 0 10px 30px rgba(2,6,23,.12);
        pointer-events: auto;
    }
    .fs-canvas-viewport.fs-hidden { display: none !important; }
    .fs-canvas-frame.fs-device-tablet { width: 768px; max-width: 100%; }
    .fs-canvas-frame.fs-device-mobile { width: 390px; max-width: 100%; }
    .fs-canvas {
        padding: 14px;
        display: grid;
        gap: 12px;
        align-content: start;
    }
    .fs-banner {
        border: 1px dashed #f59e0b;
        background: #fffbeb;
        color: #92400e;
        border-radius: 12px;
        padding: 10px;
        display: grid;
        gap: 8px;
    }
    .fs-banner p { margin: 0; font-size: 13px; }
    .fs-list {
        display: grid;
        gap: 8px;
        align-content: start;
    }
    .fs-dropzone {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        padding: 6px;
        text-align: center;
        color: #64748b;
        font-size: 11px;
        background: rgba(248,250,252,.8);
    }
    .fs-dropzone.is-over {
        border-color: #60a5fa;
        background: #eff6ff;
        color: #1d4ed8;
    }
    .fs-node {
        border: 1px solid #dbe3ef;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
    }
    .fs-node.selected { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,.15); }
    .fs-node-head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        padding: 8px 10px;
        border-bottom:1px solid #eef2f7;
        background: linear-gradient(180deg, #fff, #f8fafc);
    }
    .fs-node-title {
        display:flex;
        align-items:center;
        gap:8px;
        min-width:0;
        font-weight:700;
        color:#0f172a;
    }
    .fs-node-title .drag { cursor:grab; color:#94a3b8; user-select:none; }
    .fs-node-title small {
        color:#64748b;
        font-weight:600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fs-node-actions {
        display:flex;
        align-items:center;
        gap:4px;
        flex-wrap:wrap;
    }
    .fs-node-btn {
        border:1px solid #d0d5dd;
        background:#fff;
        border-radius:8px;
        min-width:28px;
        height:28px;
        padding:0 8px;
        cursor:pointer;
        font-weight:700;
    }
    .fs-node-btn.danger { border-color:#fecaca; color:#b91c1c; background:#fff1f2; }
    .fs-node-body {
        padding: 10px;
        display:grid;
        gap:8px;
        background:#fff;
    }
    .fs-node-preview {
        border:1px solid #eef2f7;
        border-radius:10px;
        background:#f8fafc;
        padding:8px;
        font-size:13px;
        color:#334155;
    }
    .fs-node-preview[data-fs-rich-preview-node] { cursor:text; }
    .fs-node-preview.is-rich-editing {
        border-color:#93c5fd;
        background:#ffffff;
        box-shadow: inset 0 0 0 1px rgba(96,165,250,.25);
        cursor:auto;
    }
    .fs-node-preview > *:last-child { margin-bottom:0; }
    .fs-rich-canvas-toolbar {
        position: sticky;
        top: 0;
        z-index: 2;
        display:flex;
        align-items:center;
        gap:6px;
        flex-wrap:wrap;
        margin:-8px -8px 8px -8px;
        padding:8px;
        border-bottom:1px solid #e5e7eb;
        background:linear-gradient(180deg, rgba(255,255,255,.95), rgba(248,250,252,.92));
        backdrop-filter: blur(2px);
        border-radius:10px 10px 0 0;
    }
    .fs-rich-canvas-toolbar .fs-node-btn {
        min-width:auto;
        height:28px;
        padding:0 8px;
        font-size:12px;
    }
    .fs-rich-inline-editable {
        min-height:180px;
        border:1px solid #d0d5dd;
        border-radius:10px;
        background:#fff;
        padding:10px;
        color:#0f172a;
        line-height:1.55;
    }
    .fs-rich-inline-editable:focus {
        outline:2px solid rgba(96,165,250,.3);
        border-color:#60a5fa;
    }
    .fs-rich-inline-editable > *:first-child { margin-top:0; }
    .fs-rich-inline-editable > *:last-child { margin-bottom:0; }
    .fs-rich-inline-editable .cms-cta {
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 12px;
        border-radius:10px;
        text-decoration:none;
        color:#fff !important;
        background:linear-gradient(135deg,#d9472b,#ef7f1a);
    }
    .fs-rich-inline-editable .cms-gallery {
        display:grid;
        gap:8px;
        grid-template-columns:repeat(auto-fit,minmax(90px,1fr));
    }
    .fs-rich-inline-editable .cms-gallery img {
        width:100%;
        aspect-ratio:4/3;
        object-fit:cover;
        border-radius:8px;
    }
    .fs-col-resize-overlay {
        position:absolute;
        z-index:10;
        pointer-events:none;
        min-width:64px;
        text-align:center;
        padding:6px 10px;
        border-radius:999px;
        border:1px solid rgba(255,255,255,.3);
        background:rgba(15,23,42,.88);
        color:#fff;
        font-size:12px;
        font-weight:700;
        letter-spacing:.02em;
        box-shadow:0 8px 24px rgba(2,6,23,.28);
        transform:translate(-50%, -140%);
    }
    .fs-rich-shell {
        display:grid;
        gap:8px;
    }
    .fs-rich-toolbar {
        display:flex;
        gap:6px;
        flex-wrap:wrap;
        border:1px solid #e5e7eb;
        border-radius:10px;
        padding:6px;
        background:#fff;
    }
    .fs-rich-toolbar .fs-node-btn {
        min-width:auto;
        height:30px;
        padding:0 8px;
        font-size:12px;
    }
    .fs-rich-toolbar .fs-node-btn.wide {
        padding:0 10px;
    }
    .fs-rich-panels [data-fs-rich-mode-panel] { display:none; }
    .fs-rich-panels [data-fs-rich-mode-panel].active { display:block; }
    .fs-rich-editable {
        min-height:220px;
        max-height:46vh;
        overflow:auto;
        border:1px solid #d0d5dd;
        border-radius:10px;
        padding:10px;
        background:#fff;
        color:#111827;
        line-height:1.5;
    }
    .fs-rich-editable:focus {
        outline:2px solid rgba(96,165,250,.35);
        border-color:#60a5fa;
    }
    .fs-rich-editable > *:first-child { margin-top:0; }
    .fs-rich-editable > *:last-child { margin-bottom:0; }
    .fs-rich-editable .cms-cta {
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 12px;
        border-radius:10px;
        text-decoration:none;
        color:#fff !important;
        background:linear-gradient(135deg, #d9472b, #ef7f1a);
    }
    .fs-rich-editable .cms-gallery {
        display:grid;
        gap:8px;
        grid-template-columns:repeat(auto-fit,minmax(90px,1fr));
    }
    .fs-rich-editable .cms-gallery img {
        width:100%;
        aspect-ratio:4/3;
        object-fit:cover;
        border-radius:8px;
    }
    .fs-rich-status {
        font-size:11px;
        color:#667085;
    }
    .fs-section-shell {
        border:1px dashed #cbd5e1;
        border-radius:10px;
        padding:8px;
        background:#f8fafc;
        display:grid;
        gap:8px;
    }
    .fs-columns-grid {
        display:grid;
        grid-template-columns: repeat(12, minmax(0,1fr));
        gap:8px;
    }
    .fs-column {
        min-width:0;
        border:1px dashed #cbd5e1;
        border-radius:10px;
        background:#f8fafc;
        padding:8px;
        display:grid;
        gap:8px;
        position:relative;
    }
    .fs-column.dragging-col {
        opacity:.65;
        border-color:#60a5fa;
    }
    .fs-column.is-drag-over {
        border-color:#60a5fa;
        background:#eff6ff;
    }
    .fs-column-head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:6px;
        font-size:12px;
        color:#475467;
        font-weight:700;
    }
    .fs-column-head-left {
        display:flex;
        align-items:center;
        gap:6px;
        min-width:0;
    }
    .fs-column-drag {
        cursor:grab;
        color:#94a3b8;
        user-select:none;
        font-weight:700;
    }
    .fs-column-head-right {
        display:flex;
        align-items:center;
        gap:6px;
    }
    .fs-col-resize-handle {
        width:14px;
        height:24px;
        border-radius:999px;
        border:1px solid #cbd5e1;
        background:#fff;
        cursor:ew-resize;
        position:relative;
        flex:0 0 auto;
    }
    .fs-col-resize-handle::before,
    .fs-col-resize-handle::after {
        content:'';
        position:absolute;
        top:5px;
        bottom:5px;
        width:1px;
        background:#94a3b8;
    }
    .fs-col-resize-handle::before { left:5px; }
    .fs-col-resize-handle::after { right:5px; }
    .fs-col-resize-handle:hover {
        border-color:#60a5fa;
        background:#eff6ff;
    }
    .fs-empty {
        border:1px dashed #cbd5e1;
        border-radius:10px;
        padding:10px;
        text-align:center;
        color:#64748b;
        background:#f8fafc;
        font-size:12px;
    }
    .fs-field { display:grid; gap:4px; }
    .fs-field label { font-size:12px; color:#475467; font-weight:700; }
    .fs-field input,
    .fs-field select,
    .fs-field textarea {
        width:100%;
        border:1px solid #d0d5dd;
        border-radius:10px;
        padding:8px 10px;
        font: inherit;
        background:#fff;
    }
    .fs-field textarea { resize: vertical; min-height: 84px; }
    .fs-field small { color:#667085; font-size:11px; }
    .fs-grid-2 { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:8px; }
    .fs-inline { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .fs-table-editor { display:grid; gap:10px; }
    .fs-table-toolbar { justify-content:space-between; gap:8px; }
    .fs-table-meta { font-size:11px; color:#667085; font-weight:700; }
    .fs-table-grid-wrap {
        border:1px solid #dbe3ef;
        border-radius:12px;
        overflow:auto;
        background:#fff;
    }
    .fs-table-grid {
        width:100%;
        border-collapse:separate;
        border-spacing:0;
        min-width:420px;
    }
    .fs-table-grid th,
    .fs-table-grid td {
        border-right:1px solid #e5e7eb;
        border-bottom:1px solid #e5e7eb;
        vertical-align:top;
        padding:0;
        background:#fff;
    }
    .fs-table-grid th:last-child,
    .fs-table-grid td:last-child { border-right:0; }
    .fs-table-grid tr:last-child td { border-bottom:0; }
    .fs-table-grid thead th {
        background:#f8fafc;
        font-size:11px;
        color:#475467;
        font-weight:700;
    }
    .fs-table-grid .fs-table-col-head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:6px;
        padding:6px 8px;
        min-width:0;
    }
    .fs-table-grid .fs-table-col-head span {
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }
    .fs-table-grid .fs-table-row-tools {
        display:flex;
        align-items:center;
        justify-content:center;
        gap:4px;
        width:44px;
        min-width:44px;
        background:#f8fafc;
    }
    .fs-table-grid input[data-fs-table-cell] {
        width:100%;
        border:0;
        border-radius:0;
        padding:8px 10px;
        min-height:38px;
        box-shadow:none;
        background:transparent;
    }
    .fs-table-grid input[data-fs-table-cell]:focus {
        outline:0;
        background:#eff6ff;
    }
    .fs-pill {
        display:inline-flex;
        align-items:center;
        border-radius:999px;
        border:1px solid #dbe3ef;
        background:#fff;
        padding:4px 8px;
        font-size:11px;
        color:#475467;
        font-weight:700;
    }
    .fs-columns-list {
        display:grid;
        gap:8px;
    }
    .fs-columns-item {
        border:1px solid #e5e7eb;
        border-radius:10px;
        padding:8px;
        display:grid;
        gap:6px;
        background:#fff;
    }
    .fs-columns-item-head {
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:6px;
        font-size:12px;
        font-weight:700;
        color:#334155;
    }
    .fs-node-hint {
        color:#667085;
        font-size:12px;
        line-height:1.4;
    }
    .fs-hidden { display: none !important; }

    @media (max-width: 1279px) {
        .fs-body { grid-template-columns: 240px minmax(0,1fr) 320px; }
    }
    @media (max-width: 1024px) {
        .page-fullscreen-shell { height: calc(100vh - 12px); }
        .fs-body { grid-template-columns: 1fr; grid-template-rows: auto minmax(0,1fr) auto; }
        .fs-side-left, .fs-side-right { border: 0; border-top:1px solid #dbe3ef; }
        .fs-side-left { order: 2; }
        .fs-canvas-wrap { order: 1; }
        .fs-side-right { order: 3; }
        .fs-canvas-frame.fs-device-mobile, .fs-canvas-frame.fs-device-tablet { width: 100%; }
        .fs-columns-grid { grid-template-columns: 1fr; }
        .fs-column { grid-column: span 1 !important; }
    }
</style>

