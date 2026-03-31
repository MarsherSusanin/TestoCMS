<style>
    .media-picker-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 80;
        padding: 14px;
    }
    .media-picker-overlay.open { display: flex; }
    .media-picker-modal {
        width: min(1120px, 100%);
        max-height: min(88vh, 920px);
        background: #fff;
        border: 1px solid #dbe3ef;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(15,23,42,.18);
        display: grid;
        grid-template-rows: auto auto minmax(0, 1fr) auto;
        overflow: hidden;
    }
    .media-picker-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #fff, #f8fafc);
    }
    .media-picker-header h3 { margin: 0; font-size: 18px; letter-spacing: -.02em; }
    .media-picker-header p { margin: 2px 0 0; color: #667085; font-size: 13px; }
    .media-picker-header .close-btn {
        border: 1px solid #d0d5dd;
        background: #fff;
        color: #111827;
        border-radius: 10px;
        width: 38px;
        height: 38px;
        cursor: pointer;
        font-weight: 700;
    }
    .media-picker-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto auto;
        gap: 10px;
        padding: 10px 14px;
        border-bottom: 1px solid #e5e7eb;
        background: #fff;
    }
    .media-picker-toolbar input,
    .media-picker-toolbar select {
        width: 100%;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 10px 12px;
        font: inherit;
    }
    .media-picker-grid-wrap {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        min-height: 0;
    }
    .media-picker-upload {
        display: grid;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #fcfdff, #f8fbff);
    }
    .media-picker-upload-dropzone {
        min-height: 104px;
        border: 1px dashed #98a2b3;
        border-radius: 14px;
        background: #fff;
        display: grid;
        place-items: center;
        gap: 6px;
        padding: 16px;
        text-align: center;
        color: #475467;
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }
    .media-picker-upload-dropzone strong {
        color: #101828;
        font-size: 14px;
    }
    .media-picker-upload-dropzone span {
        font-size: 13px;
    }
    .media-picker-upload-dropzone.is-active,
    .media-picker-upload-dropzone:focus-visible {
        border-color: #2563eb;
        background: #eff6ff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        outline: none;
    }
    .media-picker-upload-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .media-picker-upload-fields input {
        width: 100%;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 10px 12px;
        font: inherit;
    }
    .media-picker-upload-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }
    .media-picker-upload-footer .status {
        color: #667085;
        font-size: 13px;
    }
    .media-picker-upload-footer .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .media-picker-grid {
        padding: 14px;
        background: #f8fafc;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 10px;
        align-content: start;
        overflow: auto;
        min-height: 0;
    }
    .media-picker-card {
        border: 1px solid #dbe3ef;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
        display: grid;
        grid-template-rows: 132px auto;
        cursor: pointer;
        transition: .15s ease;
    }
    .media-picker-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(15,23,42,.06);
        border-color: #bfd4ff;
    }
    .media-picker-card.selected {
        border-color: #2563eb;
        box-shadow: inset 0 0 0 2px rgba(37,99,235,.15);
    }
    .media-picker-thumb {
        background: linear-gradient(180deg, #eff6ff, #f8fafc);
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #eef2f7;
        position: relative;
        overflow: hidden;
    }
    .media-picker-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .media-picker-thumb .badge {
        position: absolute;
        top: 8px;
        left: 8px;
        padding: 3px 7px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(255,255,255,.9);
        border: 1px solid rgba(15,23,42,.08);
        color: #475467;
    }
    .media-picker-thumb .placeholder {
        color: #64748b;
        font-size: 13px;
        text-align: center;
        padding: 0 10px;
        line-height: 1.3;
    }
    .media-picker-card-body {
        padding: 10px;
        min-width: 0;
    }
    .media-picker-card-title {
        margin: 0;
        font-weight: 700;
        font-size: 13px;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .media-picker-card-meta {
        margin: 4px 0 0;
        color: #667085;
        font-size: 12px;
        line-height: 1.25;
        word-break: break-word;
    }
    .media-picker-side {
        border-left: 1px solid #e5e7eb;
        background: #fff;
        padding: 14px;
        overflow: auto;
        min-height: 0;
    }
    .media-picker-side h4 {
        margin: 0 0 10px;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #475467;
    }
    .media-picker-side .empty {
        color: #667085;
        border: 1px dashed #d0d5dd;
        border-radius: 12px;
        padding: 12px;
        background: #f8fafc;
        font-size: 13px;
    }
    .media-picker-detail-preview {
        width: 100%;
        aspect-ratio: 4/3;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
    }
    .media-picker-detail-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .media-picker-detail-grid {
        display: grid;
        gap: 8px;
    }
    .media-picker-detail-row {
        border: 1px solid #eef2f7;
        border-radius: 10px;
        padding: 8px 10px;
        background: #fff;
    }
    .media-picker-detail-row .label {
        color: #667085;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 4px;
    }
    .media-picker-detail-row .value {
        color: #101828;
        font-size: 13px;
        line-height: 1.3;
        word-break: break-word;
    }
    .media-picker-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 14px;
        border-top: 1px solid #e5e7eb;
        background: #fff;
    }
    .media-picker-footer .status {
        color: #667085;
        font-size: 13px;
    }
    .media-picker-footer .actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .media-picker-empty-grid {
        grid-column: 1 / -1;
        border: 1px dashed #d0d5dd;
        border-radius: 12px;
        padding: 16px;
        background: #fff;
        color: #667085;
        text-align: center;
    }
    @media (max-width: 980px) {
        .media-picker-modal {
            width: 100%;
            max-height: 92vh;
        }
        .media-picker-upload-fields {
            grid-template-columns: 1fr;
        }
        .media-picker-toolbar {
            grid-template-columns: 1fr;
        }
        .media-picker-grid-wrap {
            grid-template-columns: 1fr;
        }
        .media-picker-side {
            border-left: 0;
            border-top: 1px solid #e5e7eb;
            max-height: 250px;
        }
    }
</style>
