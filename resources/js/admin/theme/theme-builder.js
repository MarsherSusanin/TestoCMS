(() => {
    const shared = window.TestoCmsEditorShared || {};
    const boot = typeof shared.readBootPayload === 'function'
        ? shared.readBootPayload('testocms-theme-editor-boot')
        : {};
    const presets = boot.presets || {};
    const fontOptions = boot.fontOptions || {};
    const savedTheme = boot.savedTheme || {};

    const form = document.getElementById('theme-builder-form');
    const preview = document.getElementById('theme-preview');
    if (!form || !preview) return;

    const colorTextInputs = Array.from(form.querySelectorAll('[data-theme-color]'));
    const colorPickers = Array.from(form.querySelectorAll('[data-color-sync]'));
    const fontFields = {
        body: form.querySelector('[data-theme-field="body_font"]'),
        heading: form.querySelector('[data-theme-field="heading_font"]'),
        mono: form.querySelector('[data-theme-field="mono_font"]'),
    };
    const presetField = form.querySelector('[data-theme-field="preset_key"]');
    const previewCode = document.getElementById('theme-preview-code');

    const toHex = (value) => {
        const v = String(value || '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(v) ? v.toUpperCase() : null;
    };

    const updatePreview = () => {
        const state = currentFormState();
        const c = state.colors;
        preview.style.setProperty('--preview-bg', c.bg);
        preview.style.setProperty('--preview-bg-start', c.bg_start);
        preview.style.setProperty('--preview-bg-end', c.bg_end);
        preview.style.setProperty('--preview-surface', c.surface);
        preview.style.setProperty('--preview-surface-tint', c.surface_tint);
        preview.style.setProperty('--preview-text', c.text);
        preview.style.setProperty('--preview-muted', c.muted);
        preview.style.setProperty('--preview-line', c.line);
        preview.style.setProperty('--preview-line-strong', c.line_strong);
        preview.style.setProperty('--preview-brand', c.brand);
        preview.style.setProperty('--preview-brand-deep', c.brand_deep);
        preview.style.setProperty('--preview-brand-alt', c.brand_alt);
        preview.style.setProperty('--preview-accent', c.accent);
        preview.style.setProperty('--preview-accent-2', c.accent_2);
        preview.style.setProperty('--preview-success', c.success);

        const bodyStack = fontOptions[state.body_font]?.stack || 'ui-sans-serif';
        const headingStack = fontOptions[state.heading_font]?.stack || bodyStack;
        const monoStack = fontOptions[state.mono_font]?.stack || 'ui-monospace';
        preview.style.setProperty('--preview-body', bodyStack);
        preview.style.setProperty('--preview-heading', headingStack);
        preview.style.setProperty('--preview-mono', monoStack);

        if (previewCode) {
            previewCode.textContent = `body: ${state.body_font}; headings: ${state.heading_font}; mono: ${state.mono_font};`;
        }

        document.querySelectorAll('[data-preset-card]').forEach((card) => {
            card.classList.toggle('active', card.getAttribute('data-preset-card') === state.preset_key);
        });
    };

    const currentFormState = () => {
        const colors = {};
        colorTextInputs.forEach((input) => {
            const key = input.getAttribute('data-theme-color');
            if (!key) return;
            colors[key] = toHex(input.value) || '#000000';
        });

        return {
            preset_key: presetField ? presetField.value : 'warm_editorial',
            body_font: fontFields.body ? fontFields.body.value : 'manrope',
            heading_font: fontFields.heading ? fontFields.heading.value : 'space_grotesk',
            mono_font: fontFields.mono ? fontFields.mono.value : 'ibm_plex_mono',
            colors,
        };
    };

    const setFormFromTheme = (themePayload) => {
        if (!themePayload || typeof themePayload !== 'object') return;
        if (presetField && themePayload.preset_key) presetField.value = themePayload.preset_key;
        if (fontFields.body && themePayload.body_font) fontFields.body.value = themePayload.body_font;
        if (fontFields.heading && themePayload.heading_font) fontFields.heading.value = themePayload.heading_font;
        if (fontFields.mono && themePayload.mono_font) fontFields.mono.value = themePayload.mono_font;

        const colors = themePayload.colors || {};
        colorTextInputs.forEach((input) => {
            const key = input.getAttribute('data-theme-color');
            if (!key) return;
            const hex = toHex(colors[key]);
            if (!hex) return;
            input.value = hex;
            const picker = form.querySelector(`[data-color-sync="colors[${key}]"]`);
            if (picker) picker.value = hex;
        });

        updatePreview();
    };

    colorPickers.forEach((picker) => {
        const textInput = picker.closest('.pickers')?.querySelector('input[type="text"]');
        if (!textInput) return;
        picker.addEventListener('input', () => {
            textInput.value = picker.value.toUpperCase();
            updatePreview();
        });
        textInput.addEventListener('input', () => {
            const hex = toHex(textInput.value);
            if (hex) picker.value = hex;
            updatePreview();
        });
    });

    document.querySelectorAll('[data-apply-preset-to-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.getAttribute('data-apply-preset-to-form');
            const preset = presets[key];
            if (!preset) return;
            setFormFromTheme({
                preset_key: key,
                body_font: preset.body_font,
                heading_font: preset.heading_font,
                mono_font: preset.mono_font,
                colors: preset.colors || {},
            });
        });
    });

    if (presetField) presetField.addEventListener('change', updatePreview);
    Object.values(fontFields).forEach((field) => field && field.addEventListener('change', updatePreview));
    colorTextInputs.forEach((input) => input.addEventListener('change', updatePreview));

    const resetButton = document.getElementById('theme-reset-form');
    if (resetButton) {
        resetButton.addEventListener('click', () => {
            setFormFromTheme(savedTheme);
        });
    }

    updatePreview();
})();
