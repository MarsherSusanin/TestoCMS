<style>
    .cms-a11y-ribbon {
        display: none;
        width: min(1360px, calc(100% - 16px));
        margin: 10px auto 0;
        padding: 10px;
        border: 1px solid var(--line);
        border-radius: 20px;
        background: var(--surface-strong, #ffffff);
        color: var(--ink);
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        backdrop-filter: blur(12px);
        position: relative;
        z-index: 50;
    }

    html[data-a11y-enabled="1"][data-a11y-panel-open="1"] .cms-a11y-ribbon {
        display: block;
    }

    .cms-a11y-ribbon__inner {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .cms-a11y-group {
        display: grid;
        gap: 8px;
        padding: 10px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--surface, #ffffff);
    }

    .cms-a11y-group--compact {
        align-content: start;
    }

    .cms-a11y-group--exit {
        align-content: end;
    }

    .cms-a11y-group__title {
        font-size: 0.78rem;
        line-height: 1.3;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        font-weight: 800;
    }

    .cms-a11y-chip-group,
    .cms-a11y-inline-actions,
    .cms-a11y-stepper {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .cms-a11y-chip-group button,
    .cms-a11y-inline-actions button,
    .cms-a11y-stepper button,
    .cms-a11y-exit,
    .cms-a11y-header-toggle {
        appearance: none;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-strong, #ffffff);
        color: var(--accent);
        font: inherit;
        font-weight: 700;
        line-height: 1.2;
        cursor: pointer;
        transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
    }

    .cms-a11y-chip-group button,
    .cms-a11y-inline-actions button,
    .cms-a11y-exit,
    .cms-a11y-header-toggle {
        padding: 9px 12px;
        min-height: 40px;
    }

    .cms-a11y-stepper button {
        min-width: 44px;
        min-height: 40px;
        padding: 9px 12px;
    }

    .cms-a11y-chip-group button:hover,
    .cms-a11y-inline-actions button:hover,
    .cms-a11y-stepper button:hover,
    .cms-a11y-exit:hover,
    .cms-a11y-header-toggle:hover {
        transform: translateY(-1px);
        border-color: rgba(15, 23, 42, 0.25);
    }

    .cms-a11y-chip-group button[data-selected="1"],
    .cms-a11y-inline-actions button[data-selected="1"],
    .cms-a11y-exit[data-selected="1"],
    .cms-a11y-header-toggle[aria-pressed="true"] {
        background: linear-gradient(135deg, var(--accent) 0%, var(--brand) 100%);
        color: #ffffff;
        border-color: transparent;
    }

    .cms-a11y-stepper output {
        min-width: 70px;
        min-height: 40px;
        padding: 9px 14px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--accent) 8%, var(--surface-strong, #ffffff));
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: var(--accent);
    }

    .cms-a11y-status {
        font-size: 0.84rem;
        color: var(--muted);
    }

    .cms-a11y-header-action {
        display: flex;
        align-items: center;
    }

    .cms-a11y-header-toggle {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .cms-a11y-header-toggle__icon {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(in srgb, var(--accent) 10%, var(--surface-strong, #ffffff));
        font-size: 0.9rem;
        font-weight: 900;
    }

    html[data-a11y-enabled="1"] {
        --bg: var(--a11y-bg);
        --surface: var(--a11y-surface);
        --surface-strong: var(--a11y-surface-strong);
        --ink: var(--a11y-ink);
        --muted: var(--a11y-muted);
        --line: var(--a11y-line);
        --line-strong: var(--a11y-line-strong);
        --brand: var(--a11y-brand);
        --brand-deep: var(--a11y-brand-deep);
        --brand-alt: var(--a11y-brand-alt);
        --brand-soft: var(--a11y-brand-soft);
        --accent: var(--a11y-accent);
        --accent-2: var(--a11y-accent-2);
        --success: var(--a11y-success);
        font-size: calc(16px * var(--a11y-font-scale, 1));
    }

    html[data-a11y-enabled="1"] body,
    html[data-a11y-enabled="1"] .nav-pill,
    html[data-a11y-enabled="1"] .button,
    html[data-a11y-enabled="1"] .site-search-form,
    html[data-a11y-enabled="1"] .content-prose,
    html[data-a11y-enabled="1"] .surface,
    html[data-a11y-enabled="1"] .site-footer,
    html[data-a11y-enabled="1"] .hero-panel,
    html[data-a11y-enabled="1"] .hero-description {
        letter-spacing: var(--a11y-letter-spacing, 0em);
        line-height: var(--a11y-line-height, 1.55);
        font-family: var(--a11y-font-body, var(--font-body)) !important;
    }

    html[data-a11y-enabled="1"] .brand-title,
    html[data-a11y-enabled="1"] .hero-title,
    html[data-a11y-enabled="1"] .page-title,
    html[data-a11y-enabled="1"] .post-card-title,
    html[data-a11y-enabled="1"] .section-header h2,
    html[data-a11y-enabled="1"] .content-prose h1,
    html[data-a11y-enabled="1"] .content-prose h2,
    html[data-a11y-enabled="1"] .content-prose h3,
    html[data-a11y-enabled="1"] .content-prose h4,
    html[data-a11y-enabled="1"] .content-prose h5,
    html[data-a11y-enabled="1"] .content-prose h6 {
        font-family: var(--a11y-font-heading, var(--font-heading)) !important;
        letter-spacing: var(--a11y-letter-spacing, 0em);
    }

    html[data-a11y-enabled="1"] .hero-shell :where(img, picture, figure img),
    html[data-a11y-enabled="1"] .content-shell :where(img, picture, figure img),
    html[data-a11y-enabled="1"] .site-footer :where(img, picture, figure img) {
        transition: filter 0.18s ease, opacity 0.18s ease;
    }

    html[data-a11y-enabled="1"][data-a11y-image-mode="grayscale"] .hero-shell :where(img, picture, figure img),
    html[data-a11y-enabled="1"][data-a11y-image-mode="grayscale"] .content-shell :where(img, picture, figure img),
    html[data-a11y-enabled="1"][data-a11y-image-mode="grayscale"] .site-footer :where(img, picture, figure img) {
        filter: grayscale(1) contrast(1.08);
    }

    html[data-a11y-enabled="1"][data-a11y-image-mode="hidden"] .hero-shell :where(img, picture, figure img, .cms-gallery),
    html[data-a11y-enabled="1"][data-a11y-image-mode="hidden"] .content-shell :where(img, picture, figure img, .cms-gallery),
    html[data-a11y-enabled="1"][data-a11y-image-mode="hidden"] .site-footer :where(img, picture, figure img) {
        display: none !important;
    }

    html[data-a11y-enabled="1"][data-a11y-embeds="off"] .hero-shell :where(iframe, video, audio, object, embed, .cms-video, .cms-custom-embed),
    html[data-a11y-enabled="1"][data-a11y-embeds="off"] .content-shell :where(iframe, video, audio, object, embed, .cms-video, .cms-custom-embed),
    html[data-a11y-enabled="1"][data-a11y-embeds="off"] .site-footer :where(iframe, video, audio, object, embed, .cms-video, .cms-custom-embed) {
        display: none !important;
    }

    @media (max-width: 980px) {
        .cms-a11y-ribbon__inner {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .cms-a11y-ribbon {
            width: calc(100% - 12px);
            margin-top: 6px;
            padding: 8px;
            border-radius: 16px;
        }

        .cms-a11y-ribbon__inner {
            grid-template-columns: 1fr;
        }

        .cms-a11y-group {
            padding: 9px;
        }

        .cms-a11y-header-toggle {
            width: 100%;
            justify-content: center;
        }
    }
</style>
