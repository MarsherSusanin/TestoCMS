<script>
(() => {
    try {
        const boot = window.TestoCmsAccessibilityBoot = {{ \Illuminate\Support\Js::from($a11y['boot_payload']) }};
        const root = document.documentElement;
        const rawDefaults = boot.defaultState && typeof boot.defaultState === 'object' ? boot.defaultState : {};
        const maps = boot.maps && typeof boot.maps === 'object' ? boot.maps : {};
        const storageKey = String(boot.storageKey || 'testocms:a11y');
        let storage = null;

        try {
            const probe = '__testocms_a11y_probe__';
            window.sessionStorage.setItem(probe, '1');
            window.sessionStorage.removeItem(probe);
            storage = window.sessionStorage;
        } catch (error) {
            storage = null;
        }

        const clampChoice = (value, allowed, fallback) => allowed.includes(value) ? value : fallback;
        const clampNumber = (value, allowed, fallback) => allowed.includes(Number(value)) ? Number(value) : fallback;
        const fontOptions = Array.isArray(boot.fontScaleOptions) ? boot.fontScaleOptions.map(Number) : [100, 110, 120, 130, 140];
        const contrastKeys = Object.keys(maps.contrastPresets || {});
        const imageModeKeys = Object.keys(maps.imageModes || {});
        const letterKeys = Object.keys(maps.letterSpacing || {});
        const lineKeys = Object.keys(maps.lineHeights || {});
        const fontFamilyKeys = Object.keys(maps.fontFamilies || {});
        let parsed = {};

        if (storage) {
            try {
                parsed = JSON.parse(storage.getItem(storageKey) || '{}') || {};
            } catch (error) {
                parsed = {};
            }
        }

        const state = {
            enabled: parsed.enabled === true,
            panelOpen: parsed.enabled === true && parsed.panelOpen === true,
            contrast: clampChoice(String(parsed.contrast || rawDefaults.contrast || 'bw'), contrastKeys, String(rawDefaults.contrast || 'bw')),
            fontScale: clampNumber(parsed.fontScale, fontOptions, Number(rawDefaults.fontScale || 120)),
            imageMode: clampChoice(String(parsed.imageMode || rawDefaults.imageMode || 'normal'), imageModeKeys, String(rawDefaults.imageMode || 'normal')),
            speechEnabled: parsed.enabled === true && parsed.speechEnabled === true,
            letterSpacing: clampChoice(String(parsed.letterSpacing || rawDefaults.letterSpacing || 'medium'), letterKeys, String(rawDefaults.letterSpacing || 'medium')),
            lineHeight: clampChoice(String(parsed.lineHeight || rawDefaults.lineHeight || 'medium'), lineKeys, String(rawDefaults.lineHeight || 'medium')),
            fontFamily: clampChoice(String(parsed.fontFamily || rawDefaults.fontFamily || 'sans'), fontFamilyKeys, String(rawDefaults.fontFamily || 'sans')),
            embedsEnabled: parsed.embedsEnabled !== false,
        };

        const preset = (maps.contrastPresets || {})[state.contrast] || null;
        const letter = (maps.letterSpacing || {})[state.letterSpacing] || null;
        const line = (maps.lineHeights || {})[state.lineHeight] || null;
        const fontFamily = (maps.fontFamilies || {})[state.fontFamily] || null;
        const theme = preset && typeof preset.theme === 'object' ? preset.theme : {};

        root.dataset.a11yEnabled = state.enabled ? '1' : '0';
        root.dataset.a11yPanelOpen = state.enabled && state.panelOpen ? '1' : '0';
        root.dataset.a11yContrast = state.contrast;
        root.dataset.a11yImageMode = state.imageMode;
        root.dataset.a11yFontFamily = state.fontFamily;
        root.dataset.a11yEmbeds = state.embedsEnabled ? 'on' : 'off';

        root.style.setProperty('--a11y-font-scale', String(state.fontScale / 100));
        root.style.setProperty('--a11y-letter-spacing', String(letter && letter.value ? letter.value : '0em'));
        root.style.setProperty('--a11y-line-height', String(line && line.value ? line.value : '1.55'));
        root.style.setProperty('--a11y-font-body', String(fontFamily && fontFamily.bodyStack ? fontFamily.bodyStack : '"Arial", "Helvetica Neue", "Segoe UI", sans-serif'));
        root.style.setProperty('--a11y-font-heading', String(fontFamily && fontFamily.headingStack ? fontFamily.headingStack : '"Arial", "Helvetica Neue", "Segoe UI", sans-serif'));

        const themeMap = {
            '--a11y-bg': 'bg',
            '--a11y-surface': 'surface',
            '--a11y-surface-strong': 'surfaceStrong',
            '--a11y-ink': 'ink',
            '--a11y-muted': 'muted',
            '--a11y-line': 'line',
            '--a11y-line-strong': 'lineStrong',
            '--a11y-brand': 'brand',
            '--a11y-brand-deep': 'brandDeep',
            '--a11y-brand-alt': 'brandAlt',
            '--a11y-brand-soft': 'brandSoft',
            '--a11y-accent': 'accent',
            '--a11y-accent-2': 'accent2',
            '--a11y-success': 'success',
        };

        Object.entries(themeMap).forEach(([cssVar, payloadKey]) => {
            if (typeof theme[payloadKey] === 'string' && theme[payloadKey] !== '') {
                root.style.setProperty(cssVar, theme[payloadKey]);
            }
        });
    } catch (error) {
        // Fail soft: public pages must keep rendering even if the bootstrap script fails.
    }
})();
</script>
