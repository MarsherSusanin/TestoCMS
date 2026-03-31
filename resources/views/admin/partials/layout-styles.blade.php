<style>
    :root {
        --bg: #f5f7fb;
        --panel: #ffffff;
        --border: #d7dce7;
        --text: #101828;
        --muted: #667085;
        --nav: #0f172a;
        --nav-2: #1e293b;
        --accent: #0ea5e9;
        --danger: #dc2626;
        --success-bg: #ecfdf3;
        --success-border: #86efac;
        --error-bg: #fef2f2;
        --error-border: #fca5a5;
        --code: #111827;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif;
        color: var(--text);
        background: linear-gradient(180deg, #eff4ff 0%, var(--bg) 220px);
    }
    a { color: #0369a1; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .shell {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        min-height: 100vh;
        transition: grid-template-columns .18s ease;
    }
    .sidebar {
        background: radial-gradient(circle at top, #1e293b, #0b1220 65%);
        color: #e5e7eb;
        padding: 18px;
        border-right: 1px solid rgba(255,255,255,.08);
        transition: padding .18s ease;
    }
    .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }
    .brand {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -.02em;
        margin: 0;
        min-width: 0;
    }
    .brand a { color: inherit; text-decoration: none; }
    .brand-short { display: none; }
    .sidebar-toggle {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,.16);
        background: rgba(255,255,255,.05);
        color: #e2e8f0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
    }
    .sidebar-toggle:hover { background: rgba(255,255,255,.09); }
    .sidebar-toggle-icon {
        display: inline-block;
        transition: transform .18s ease;
        font-size: 16px;
        line-height: 1;
    }
    .nav-group { margin: 14px 0 0; }
    .nav-title {
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .1em;
        margin: 10px 8px;
    }
    .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #e2e8f0;
        padding: 10px 12px;
        border-radius: 10px;
        margin: 4px 0;
        background: transparent;
    }
    .nav-link .nav-short {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        color: #dbeafe;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.12);
        flex-shrink: 0;
    }
    .nav-link .nav-short svg {
        width: 14px;
        height: 14px;
        display: block;
        flex-shrink: 0;
    }
    .nav-link .nav-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .nav-badge {
        margin-left: auto;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 999px;
        border: 1px solid rgba(14,165,233,.45);
        background: rgba(14,165,233,.2);
        color: #e0f2fe;
        flex-shrink: 0;
    }
    .nav-link:hover { background: rgba(255,255,255,.06); text-decoration: none; }
    .nav-link.active { background: rgba(14,165,233,.2); color: #e0f2fe; border: 1px solid rgba(14,165,233,.35); }
    .sidebar form { margin-top: 18px; }
    .sidebar .logout-btn {
        width: 100%;
        border: 1px solid rgba(255,255,255,.18);
        background: rgba(255,255,255,.04);
        color: #f8fafc;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .logout-icon {
        width: 18px;
        height: 18px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.12);
        font-size: 11px;
        font-weight: 800;
    }
    .content { padding: 20px; }
    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }
    .page-header h1 {
        margin: 0;
        font-size: 30px;
        letter-spacing: -.03em;
    }
    .page-header p { margin: 6px 0 0; color: var(--muted); }
    .page-header-meta { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; justify-content: flex-end; }
    .panel {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 8px 30px rgba(15,23,42,.04);
    }
    .panel + .panel { margin-top: 14px; }
    .panel-section-title { margin: 0 0 10px; }
    .panel-section-description { margin-top: 4px; }
    .management-stats { margin-bottom: 14px; }
    .json-context-details pre {
        white-space: pre-wrap;
        margin: 8px 0 0;
    }
    .list-toolbar {
        display: grid;
        gap: 10px;
    }
    .list-toolbar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .list-summary {
        color: var(--muted);
        font-size: 14px;
    }
    .bulk-form {
        display: grid;
        gap: 10px;
    }
    .bulk-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        padding: 12px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fbfdff;
    }
    .bulk-controls[hidden] { display: none !important; }
    .action-row {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
    }
    .action-row-primary { flex-shrink: 0; }
    .action-menu {
        position: relative;
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }
    .action-menu-trigger {
        min-width: 40px;
        padding-left: 10px;
        padding-right: 10px;
        font-size: 18px;
        line-height: 1;
    }
    .action-menu-panel {
        position: fixed;
        top: -9999px;
        left: -9999px;
        min-width: 220px;
        max-width: min(280px, calc(100vw - 16px));
        padding: 8px;
        display: grid;
        gap: 4px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 18px 40px rgba(15,23,42,.12);
        z-index: 140;
        visibility: hidden;
        pointer-events: none;
    }
    .action-menu.is-open .action-menu-panel:not([hidden]) {
        visibility: visible;
        pointer-events: auto;
    }
    .action-menu[data-action-align="start"] .action-menu-panel {
        left: 0;
        right: auto;
    }
    .action-menu-item {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        padding: 9px 10px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: var(--text);
        text-decoration: none;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
        text-align: left;
    }
    .action-menu-item:hover {
        background: #f8fafc;
        text-decoration: none;
    }
    .action-menu-item.is-danger {
        color: var(--danger);
    }
    .action-menu-form {
        margin: 0;
    }
    .action-modal-card {
        width: min(620px, 100%);
    }
    .action-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }
    .action-modal-title {
        margin: 0;
        font-size: 20px;
        letter-spacing: -.02em;
    }
    .action-modal-description {
        margin: 6px 0 0;
    }
    .action-toolbar-shell {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .table-wrap {
        width: 100%;
        overflow: auto;
    }
    .grid {
        display: grid;
        gap: 14px;
    }
    .grid.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .cards { display:grid; gap:12px; grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); }
    .card { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 14px; }
    .card .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; }
    .card .value { font-size: 28px; margin-top: 6px; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #eaecf0; vertical-align: top; }
    th { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
    tr:last-child td { border-bottom: 0; }
    input[type="text"], input[type="email"], input[type="password"], input[type="datetime-local"], input[type="number"], select, textarea {
        width: 100%;
        border: 1px solid #cfd8e3;
        background: #fff;
        border-radius: 10px;
        padding: 10px 12px;
        font: inherit;
        color: inherit;
    }
    textarea { min-height: 120px; resize: vertical; }
    label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
    .field { margin-bottom: 12px; }
    .field small { display:block; margin-top:4px; color:var(--muted); }
    .inline { display:flex; gap:10px; align-items:center; flex-wrap: wrap; }
    .inline > * { margin:0; }
    .btn, button.btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: var(--text);
        border-radius: 10px;
        padding: 9px 12px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 600;
    }
    .btn:hover { text-decoration:none; background:#f8fafc; }
    .btn-primary { border-color: #0ea5e9; background: #0ea5e9; color: #fff; }
    .btn-primary:hover { background: #0284c7; }
    .btn-danger { border-color: #ef4444; background: #ef4444; color: #fff; }
    .btn-danger:hover { background: #dc2626; }
    .btn-ghost { background: transparent; }
    .btn-small { padding: 6px 10px; border-radius: 8px; font-size: 13px; }
    .status-pill {
        display:inline-block; padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: 700;
        border: 1px solid #d0d5dd; background: #fff;
    }
    .status-published { background: #ecfdf3; border-color: #86efac; color: #166534; }
    .status-draft { background: #f8fafc; color: #334155; }
    .status-scheduled { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
    .status-review { background: #fff7ed; border-color: #fdba74; color: #c2410c; }
    .status-archived { background: #f3f4f6; color: #4b5563; }
    .muted { color: var(--muted); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .flash { border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
    .flash.success { background: var(--success-bg); border: 1px solid var(--success-border); }
    .flash.error { background: var(--error-bg); border: 1px solid var(--error-border); }
    .template-modal {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, 0.46);
    }
    .template-modal.open { display:flex; }
    .template-modal-card {
        width: min(560px, 100%);
        background:#fff;
        border:1px solid #d0d5dd;
        border-radius:12px;
        padding:14px;
    }
    .tabs { display:flex; gap:8px; flex-wrap: wrap; margin-bottom: 12px; }
    .tab { padding: 6px 10px; border: 1px solid #d0d5dd; border-radius: 999px; background:#fff; font-size: 13px; }
    details.panel summary { cursor: pointer; font-weight: 700; }
    .split { display:grid; gap:14px; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
    .checkbox { display:flex; align-items:center; gap:8px; font-weight:500; }
    .checkbox input { width:auto; }
    .pagination { margin-top: 12px; }
    .pagination nav { display:flex; justify-content:center; }
    .pagination svg { width: 16px; }
    @media (min-width: 1025px) {
        body.admin-sidebar-collapsed .shell {
            grid-template-columns: 86px minmax(0, 1fr);
        }
        body.admin-sidebar-collapsed .sidebar {
            padding: 18px 10px;
        }
        body.admin-sidebar-collapsed .sidebar .brand-text {
            display: none;
        }
        body.admin-sidebar-collapsed .sidebar .brand-short {
            display: inline;
        }
        body.admin-sidebar-collapsed .sidebar .nav-title {
            display: none;
        }
        body.admin-sidebar-collapsed .sidebar .nav-link {
            justify-content: center;
            padding: 10px 8px;
            gap: 0;
        }
        body.admin-sidebar-collapsed .sidebar .nav-link .nav-label {
            display: none;
        }
        body.admin-sidebar-collapsed .sidebar .nav-link .nav-badge {
            display: none;
        }
        body.admin-sidebar-collapsed .sidebar .nav-link .nav-short {
            width: 28px;
            height: 28px;
            font-size: 10px;
        }
        body.admin-sidebar-collapsed .sidebar .nav-link .nav-short svg {
            width: 16px;
            height: 16px;
        }
        body.admin-sidebar-collapsed .sidebar .logout-btn {
            padding: 10px 8px;
        }
        body.admin-sidebar-collapsed .sidebar .logout-btn .logout-label {
            display: none;
        }
        body.admin-sidebar-collapsed .sidebar .sidebar-toggle-icon {
            transform: rotate(180deg);
        }
    }
    @media (max-width: 1024px) {
        .shell { grid-template-columns: 1fr; }
        .sidebar { position: sticky; top: 0; z-index: 10; }
        .split, .grid.cols-2, .grid.cols-3 { grid-template-columns: 1fr; }
        .content { padding: 14px; }
    }
</style>
