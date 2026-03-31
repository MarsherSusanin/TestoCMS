<style>
        .theme-presets { display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
        .theme-preset-card { border:1px solid var(--border); border-radius:14px; background:#fff; padding:14px; }
        .theme-preset-card.active { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(14,165,233,.15) inset; }
        .theme-preset-card h3 { margin:0 0 6px; font-size:16px; }
        .theme-preset-card p { margin:0 0 10px; color:var(--muted); font-size:13px; }
        .theme-swatches { display:grid; grid-template-columns: repeat(6, 1fr); gap:6px; margin-bottom:10px; }
        .theme-swatches span { display:block; height:24px; border-radius:8px; border:1px solid rgba(15,23,42,.08); }
        .theme-form-grid { display:grid; gap:14px; grid-template-columns: minmax(0, 1.25fr) minmax(0, .75fr); }
        .theme-color-grid { display:grid; gap:10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .theme-color-row { border:1px solid #e5e7eb; border-radius:12px; padding:10px; background:#fff; }
        .theme-color-row label { margin-bottom:8px; }
        .theme-color-row .pickers { display:grid; grid-template-columns: 54px 1fr; gap:8px; align-items:center; }
        .theme-color-row input[type="color"] { width: 100%; height: 40px; border:1px solid #cfd8e3; border-radius:10px; padding:2px; background:#fff; }
        .theme-preview {
            border-radius: 18px;
            border: 1px solid #dbe3ef;
            overflow: hidden;
            background: var(--preview-bg, #f4f1ea);
            color: var(--preview-text, #111827);
            font-family: var(--preview-body, ui-sans-serif);
            box-shadow: 0 8px 24px rgba(15,23,42,.05);
        }
        .theme-preview .hero {
            padding: 16px;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--preview-brand, #d9472b) 16%, transparent), transparent 55%),
                linear-gradient(180deg, var(--preview-bg-start, #f7f3eb), var(--preview-bg-end, #f1ede4));
            border-bottom: 1px solid color-mix(in srgb, var(--preview-line, #d0d5dd) 70%, white);
        }
        .theme-preview .hero h3 {
            margin: 0 0 8px;
            font-size: 22px;
            line-height: 1.05;
            letter-spacing: -.02em;
            font-family: var(--preview-heading, ui-sans-serif);
        }
        .theme-preview .hero p { margin: 0; color: var(--preview-muted, #667085); }
        .theme-preview .chips { margin-top: 10px; display:flex; gap:8px; flex-wrap: wrap; }
        .theme-preview .chip {
            border:1px solid color-mix(in srgb, var(--preview-line, #d0d5dd) 80%, white);
            background: color-mix(in srgb, var(--preview-surface, white) 85%, transparent);
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            color: var(--preview-muted, #667085);
        }
        .theme-preview .chip.brand {
            background: color-mix(in srgb, var(--preview-brand, #d9472b) 10%, white);
            border-color: color-mix(in srgb, var(--preview-brand, #d9472b) 25%, white);
            color: var(--preview-brand-deep, #b5361d);
        }
        .theme-preview .body { padding: 14px 16px 16px; background: color-mix(in srgb, var(--preview-surface-tint, #ffffff) 65%, white); }
        .theme-preview .card {
            background: color-mix(in srgb, var(--preview-surface, #fff) 88%, white);
            border: 1px solid color-mix(in srgb, var(--preview-line, #d0d5dd) 75%, white);
            border-radius: 14px;
            padding: 12px;
        }
        .theme-preview .card h4 { margin:0 0 6px; font-family: var(--preview-heading, ui-sans-serif); font-size: 16px; }
        .theme-preview .card p { margin:0 0 10px; color: var(--preview-muted, #667085); font-size: 13px; }
        .theme-preview .btn {
            display:inline-flex; align-items:center; justify-content:center;
            border-radius: 10px; padding: 7px 10px; font-weight: 700; font-size: 13px;
            background: linear-gradient(135deg, var(--preview-brand, #d9472b), var(--preview-brand-alt, #ef7f1a));
            color:#fff; border:0;
        }
        .theme-preview code {
            display:block;
            margin-top:10px;
            padding:8px 10px;
            background: color-mix(in srgb, var(--preview-accent, #0f172a) 6%, white);
            border-radius:10px;
            border:1px solid color-mix(in srgb, var(--preview-line-strong, #cfd8e3) 70%, white);
            font-family: var(--preview-mono, ui-monospace);
            color: color-mix(in srgb, var(--preview-accent, #0f172a) 85%, black);
            font-size: 12px;
        }
        .chrome-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .chrome-tab {
            border:1px solid #d0d5dd;
            background:#fff;
            color:var(--text);
            border-radius:999px;
            padding:7px 11px;
            font-weight:700;
            cursor:pointer;
        }
        .chrome-tab.active { background:#0f172a; border-color:#0f172a; color:#fff; }
        .chrome-panel { display:none; }
        .chrome-panel.active { display:block; }
        .chrome-builder-grid { display:grid; gap:14px; grid-template-columns: minmax(0, 1.25fr) minmax(0, .75fr); }
        .chrome-list-shell {
            border:1px solid #e5e7eb;
            border-radius:12px;
            background:#fff;
            overflow:hidden;
        }
        .chrome-list-header {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            padding:10px 12px;
            border-bottom:1px solid #e5e7eb;
            background:#f8fafc;
        }
        .chrome-list-header h3 { margin:0; font-size:14px; }
        .chrome-list-items { display:grid; gap:10px; padding:10px; }
        .chrome-item-card {
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:10px;
            background:#fff;
            display:grid;
            gap:10px;
        }
        .chrome-item-row {
            display:grid;
            gap:8px;
            grid-template-columns: 1fr 1fr;
        }
        .chrome-item-row.cols-3 {
            grid-template-columns: minmax(120px, .9fr) minmax(0, 1.35fr) minmax(0, 1.35fr);
        }
        .chrome-item-actions {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:8px;
            flex-wrap:wrap;
        }
        .chrome-item-tools { display:flex; gap:6px; flex-wrap:wrap; }
        .chrome-kv { display:grid; gap:4px; }
        .chrome-kv label { font-size:12px; margin:0; color:var(--muted); }
        .chrome-kv input, .chrome-kv select { padding:8px 10px; }
        .chrome-check-grid { display:grid; gap:10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .chrome-preview-shell {
            border:1px solid #dbe3ef;
            border-radius:14px;
            background:#fff;
            overflow:hidden;
            box-shadow: 0 8px 20px rgba(15,23,42,.04);
        }
        .chrome-preview-head {
            padding:10px 12px;
            border-bottom:1px solid #e5e7eb;
            background:#f8fafc;
            font-weight:700;
        }
        .chrome-preview-body { padding:12px; display:grid; gap:12px; }
        .chrome-preview-topbar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:10px;
            background:#fff;
        }
        .chrome-preview-brand {
            display:grid;
            gap:2px;
            min-width:0;
        }
        .chrome-preview-brand strong { line-height:1.1; }
        .chrome-preview-brand span {
            font-size:12px;
            color:var(--muted);
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .chrome-preview-nav,
        .chrome-preview-footer-links {
            display:flex;
            gap:6px;
            flex-wrap:wrap;
        }
        .chrome-preview-pill {
            border:1px solid #d0d5dd;
            border-radius:999px;
            background:#fff;
            padding:5px 8px;
            font-size:12px;
            font-weight:600;
        }
        .chrome-preview-pill.primary { background:#0ea5e9; border-color:#0ea5e9; color:#fff; }
        .chrome-preview-pill.secondary { background:#f8fafc; }
        .chrome-preview-pill.ghost { background:transparent; }
        .chrome-preview-search {
            border:1px solid #d0d5dd;
            border-radius:999px;
            padding:7px 10px;
            font-size:12px;
            color:#667085;
            min-width: 180px;
        }
        .chrome-footer-preview {
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:10px;
            background:#fff;
            display:grid;
            gap:10px;
        }
        .chrome-hidden-input { display:none; }
        @media (max-width: 1100px) {
            .theme-form-grid, .theme-color-grid { grid-template-columns: 1fr; }
            .chrome-builder-grid { grid-template-columns: 1fr; }
            .chrome-item-row, .chrome-item-row.cols-3, .chrome-check-grid { grid-template-columns: 1fr; }
        }
</style>
