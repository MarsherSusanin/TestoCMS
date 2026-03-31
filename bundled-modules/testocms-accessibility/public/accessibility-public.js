(function () {
    const boot = window.TestoCmsAccessibilityBoot;
    const root = document.documentElement;
    const ribbon = document.querySelector('[data-a11y-ribbon]');
    const toggle = document.querySelector('[data-a11y-toggle]');

    if (!boot || !root || !ribbon || !toggle) {
        return;
    }

    const labels = boot.labels && typeof boot.labels === 'object' ? boot.labels : {};
    const maps = boot.maps && typeof boot.maps === 'object' ? boot.maps : {};
    const storageKey = String(boot.storageKey || 'testocms:a11y');
    const fontScaleOptions = Array.isArray(boot.fontScaleOptions)
        ? boot.fontScaleOptions.map((value) => Number(value)).filter((value) => Number.isFinite(value))
        : [100, 110, 120, 130, 140];
    const contrastKeys = Object.keys(maps.contrastPresets || {});
    const imageModeKeys = Object.keys(maps.imageModes || {});
    const letterSpacingKeys = Object.keys(maps.letterSpacing || {});
    const lineHeightKeys = Object.keys(maps.lineHeights || {});
    const fontFamilyKeys = Object.keys(maps.fontFamilies || {});
    const speechSupported = typeof window.speechSynthesis !== 'undefined' && typeof window.SpeechSynthesisUtterance === 'function';
    const speechStatus = ribbon.querySelector('[data-a11y-speech-status]');
    const speechPauseButton = ribbon.querySelector('[data-a11y-action="speech-pause"]');
    const fontScaleReadout = ribbon.querySelector('[data-a11y-readout="fontScale"]');
    const embeddedSelector = '.hero-shell iframe, .hero-shell video, .hero-shell audio, .hero-shell object, .hero-shell embed, .content-shell iframe, .content-shell video, .content-shell audio, .content-shell object, .content-shell embed, .site-footer iframe, .site-footer video, .site-footer audio, .site-footer object, .site-footer embed';
    const embedAttrMap = {
        iframe: ['src', 'srcdoc'],
        object: ['data'],
        embed: ['src'],
    };
    let fallbackState = null;
    let state = null;
    let speechPaused = false;

    const storage = createStorage();

    function createStorage() {
        try {
            const probe = '__testocms_a11y_probe__';
            window.sessionStorage.setItem(probe, '1');
            window.sessionStorage.removeItem(probe);

            return window.sessionStorage;
        } catch (error) {
            return null;
        }
    }

    function clone(value) {
        return JSON.parse(JSON.stringify(value || {}));
    }

    function defaultState() {
        const rawDefaults = boot.defaultState && typeof boot.defaultState === 'object' ? boot.defaultState : {};

        return {
            enabled: rawDefaults.enabled === true,
            panelOpen: rawDefaults.panelOpen === true,
            contrast: pickChoice(rawDefaults.contrast, contrastKeys, 'bw'),
            fontScale: pickNumber(rawDefaults.fontScale, fontScaleOptions, 120),
            imageMode: pickChoice(rawDefaults.imageMode, imageModeKeys, 'normal'),
            speechEnabled: rawDefaults.speechEnabled === true,
            letterSpacing: pickChoice(rawDefaults.letterSpacing, letterSpacingKeys, 'medium'),
            lineHeight: pickChoice(rawDefaults.lineHeight, lineHeightKeys, 'medium'),
            fontFamily: pickChoice(rawDefaults.fontFamily, fontFamilyKeys, 'sans'),
            embedsEnabled: rawDefaults.embedsEnabled !== false,
        };
    }

    function pickChoice(value, allowed, fallback) {
        const candidate = String(value || '');

        return allowed.includes(candidate) ? candidate : fallback;
    }

    function pickNumber(value, allowed, fallback) {
        const candidate = Number(value);

        return allowed.includes(candidate) ? candidate : fallback;
    }

    function normalizeState(raw) {
        const input = raw && typeof raw === 'object' ? raw : {};
        const defaults = defaultState();
        const normalized = {
            enabled: input.enabled === true,
            panelOpen: input.enabled === true && input.panelOpen === true,
            contrast: pickChoice(input.contrast || defaults.contrast, contrastKeys, defaults.contrast),
            fontScale: pickNumber(input.fontScale, fontScaleOptions, defaults.fontScale),
            imageMode: pickChoice(input.imageMode || defaults.imageMode, imageModeKeys, defaults.imageMode),
            speechEnabled: input.enabled === true && input.speechEnabled === true,
            letterSpacing: pickChoice(input.letterSpacing || defaults.letterSpacing, letterSpacingKeys, defaults.letterSpacing),
            lineHeight: pickChoice(input.lineHeight || defaults.lineHeight, lineHeightKeys, defaults.lineHeight),
            fontFamily: pickChoice(input.fontFamily || defaults.fontFamily, fontFamilyKeys, defaults.fontFamily),
            embedsEnabled: input.embedsEnabled !== false,
        };

        if (!normalized.enabled) {
            normalized.panelOpen = false;
            normalized.speechEnabled = false;
        }

        return normalized;
    }

    function loadState() {
        if (!storage) {
            return fallbackState ? clone(fallbackState) : defaultState();
        }

        try {
            const raw = storage.getItem(storageKey);
            if (!raw) {
                return defaultState();
            }

            return normalizeState(JSON.parse(raw));
        } catch (error) {
            return defaultState();
        }
    }

    function persistState(nextState) {
        if (!storage) {
            fallbackState = clone(nextState);
            return;
        }

        if (!nextState.enabled) {
            storage.removeItem(storageKey);
            return;
        }

        storage.setItem(storageKey, JSON.stringify(nextState));
    }

    function applyThemePreset(presetKey) {
        const preset = (maps.contrastPresets || {})[presetKey];
        const theme = preset && typeof preset.theme === 'object' ? preset.theme : {};
        const cssVarMap = {
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

        Object.entries(cssVarMap).forEach(([cssVar, payloadKey]) => {
            if (typeof theme[payloadKey] === 'string' && theme[payloadKey] !== '') {
                root.style.setProperty(cssVar, theme[payloadKey]);
            }
        });
    }

    function applyTypography(nextState) {
        const letterSpacing = (maps.letterSpacing || {})[nextState.letterSpacing] || {};
        const lineHeight = (maps.lineHeights || {})[nextState.lineHeight] || {};
        const fontFamily = (maps.fontFamilies || {})[nextState.fontFamily] || {};

        root.style.setProperty('--a11y-font-scale', String(nextState.fontScale / 100));
        root.style.setProperty('--a11y-letter-spacing', String(letterSpacing.value || '0em'));
        root.style.setProperty('--a11y-line-height', String(lineHeight.value || '1.55'));
        root.style.setProperty(
            '--a11y-font-body',
            String(fontFamily.bodyStack || '"Arial", "Helvetica Neue", "Segoe UI", sans-serif')
        );
        root.style.setProperty(
            '--a11y-font-heading',
            String(fontFamily.headingStack || '"Arial", "Helvetica Neue", "Segoe UI", sans-serif')
        );
    }

    function datasetFromState(nextState) {
        root.dataset.a11yEnabled = nextState.enabled ? '1' : '0';
        root.dataset.a11yPanelOpen = nextState.enabled && nextState.panelOpen ? '1' : '0';
        root.dataset.a11yContrast = nextState.contrast;
        root.dataset.a11yImageMode = nextState.imageMode;
        root.dataset.a11yFontFamily = nextState.fontFamily;
        root.dataset.a11yEmbeds = nextState.embedsEnabled ? 'on' : 'off';
    }

    function datasetKeyForAttr(attr) {
        return 'a11yOriginal' + attr.charAt(0).toUpperCase() + attr.slice(1);
    }

    function disableEmbeds() {
        document.querySelectorAll(embeddedSelector).forEach((element) => {
            const tagName = element.tagName.toLowerCase();
            const attrs = embedAttrMap[tagName] || [];

            if (typeof element.pause === 'function') {
                try {
                    element.pause();
                } catch (error) {
                    // Ignore media pause errors.
                }
            }

            attrs.forEach((attr) => {
                const datasetKey = datasetKeyForAttr(attr);
                if (element.dataset[datasetKey]) {
                    return;
                }

                if (element.hasAttribute(attr)) {
                    element.dataset[datasetKey] = element.getAttribute(attr) || '';
                }

                if (tagName === 'iframe' && attr === 'src') {
                    element.setAttribute('src', 'about:blank');
                    return;
                }

                if (attr === 'srcdoc') {
                    element.removeAttribute(attr);
                    return;
                }

                element.removeAttribute(attr);
            });
        });
    }

    function restoreEmbeds() {
        document.querySelectorAll(embeddedSelector).forEach((element) => {
            const tagName = element.tagName.toLowerCase();
            const attrs = embedAttrMap[tagName] || [];

            attrs.forEach((attr) => {
                const datasetKey = datasetKeyForAttr(attr);
                if (!Object.prototype.hasOwnProperty.call(element.dataset, datasetKey)) {
                    return;
                }

                const originalValue = element.dataset[datasetKey];
                if (originalValue !== '') {
                    element.setAttribute(attr, originalValue);
                } else {
                    element.removeAttribute(attr);
                }

                delete element.dataset[datasetKey];
            });
        });
    }

    function syncEmbeds(nextState) {
        if (nextState.enabled && !nextState.embedsEnabled) {
            disableEmbeds();
            return;
        }

        restoreEmbeds();
    }

    function extractReadableText() {
        const sections = document.querySelectorAll('.hero-shell, .content-shell');
        const parts = [];

        sections.forEach((section) => {
            const cloneNode = section.cloneNode(true);
            cloneNode.querySelectorAll('[data-a11y-ui], script, style, noscript, iframe, video, audio, object, embed').forEach((node) => {
                node.remove();
            });

            const text = String(cloneNode.textContent || '').replace(/\s+/g, ' ').trim();
            if (text !== '') {
                parts.push(text);
            }
        });

        return parts.join('. ');
    }

    function stopSpeech() {
        if (!speechSupported) {
            return;
        }

        window.speechSynthesis.cancel();
        speechPaused = false;
    }

    function playSpeech() {
        if (!speechSupported) {
            syncSpeechUi();
            return;
        }

        const text = extractReadableText();
        if (text === '') {
            stopSpeech();
            syncSpeechUi();
            return;
        }

        stopSpeech();

        const utterance = new window.SpeechSynthesisUtterance(text);
        utterance.lang = root.lang === 'ru' ? 'ru-RU' : 'en-US';
        utterance.rate = 1;
        utterance.pitch = 1;
        utterance.onend = function () {
            speechPaused = false;
            syncSpeechUi();
        };
        utterance.onerror = function () {
            speechPaused = false;
            syncSpeechUi();
        };

        window.speechSynthesis.speak(utterance);
        syncSpeechUi();
    }

    function pauseOrResumeSpeech() {
        if (!speechSupported) {
            syncSpeechUi();
            return;
        }

        if (window.speechSynthesis.speaking && !speechPaused) {
            window.speechSynthesis.pause();
            speechPaused = true;
            syncSpeechUi();
            return;
        }

        if (speechPaused) {
            window.speechSynthesis.resume();
            speechPaused = false;
            syncSpeechUi();
            return;
        }

        playSpeech();
    }

    function syncSpeechUi() {
        if (speechPauseButton) {
            speechPauseButton.textContent = speechPaused ? String(labels.resume || 'Resume') : String(labels.pause || 'Pause');
        }

        if (speechStatus) {
            if (!speechSupported) {
                speechStatus.textContent = String(labels.speech_status_unavailable || 'Web Speech API unavailable');
            } else if (!state.enabled || !state.speechEnabled) {
                speechStatus.textContent = String(labels.speech_status_off || 'Speech is off');
            } else if (speechPaused) {
                speechStatus.textContent = String(labels.speech_status_paused || 'Speech is paused');
            } else {
                speechStatus.textContent = String(labels.speech_status_on || 'Speech is active');
            }
        }

        ribbon.querySelectorAll('[data-a11y-action^="speech-"], [data-a11y-setting="speechEnabled"]').forEach((button) => {
            button.disabled = !speechSupported;
            button.setAttribute('aria-disabled', !speechSupported ? 'true' : 'false');
        });
    }

    function syncSelectionUi() {
        if (fontScaleReadout) {
            fontScaleReadout.textContent = state.fontScale + '%';
        }

        ribbon.querySelectorAll('[data-a11y-setting]').forEach((button) => {
            const setting = String(button.getAttribute('data-a11y-setting') || '');
            const value = String(button.getAttribute('data-a11y-value') || '');
            let selected = false;

            if (setting === 'embedsEnabled' || setting === 'speechEnabled') {
                selected = value === (state[setting] ? '1' : '0');
            } else if (Object.prototype.hasOwnProperty.call(state, setting)) {
                selected = String(state[setting]) === value;
            }

            button.dataset.selected = selected ? '1' : '0';
        });

        toggle.setAttribute('aria-pressed', state.enabled ? 'true' : 'false');
        toggle.setAttribute('aria-expanded', state.enabled && state.panelOpen ? 'true' : 'false');
        syncSpeechUi();
    }

    function applyState(nextState, options) {
        const opts = options || {};
        const previousState = state ? clone(state) : defaultState();
        state = normalizeState(nextState);

        datasetFromState(state);
        applyThemePreset(state.contrast);
        applyTypography(state);
        syncEmbeds(state);
        syncSelectionUi();

        if (opts.persist !== false) {
            persistState(state);
        }

        if (!state.enabled || !state.speechEnabled) {
            stopSpeech();
            syncSpeechUi();
            return;
        }

        if (opts.forceSpeechRestart || opts.initialLoad || !previousState.enabled || !previousState.speechEnabled) {
            playSpeech();
        }
    }

    function baselineState() {
        const defaults = defaultState();

        return normalizeState(Object.assign({}, defaults, {
            enabled: true,
            panelOpen: true,
        }));
    }

    function changeFontScale(direction) {
        const currentIndex = fontScaleOptions.indexOf(state.fontScale);
        const nextIndex = direction === 'up'
            ? Math.min(fontScaleOptions.length - 1, currentIndex + 1)
            : Math.max(0, currentIndex - 1);

        applyState(Object.assign({}, state, {
            fontScale: fontScaleOptions[nextIndex],
        }));
    }

    function handleSettingButton(button) {
        const setting = String(button.getAttribute('data-a11y-setting') || '');
        const rawValue = String(button.getAttribute('data-a11y-value') || '');
        if (setting === '') {
            return;
        }

        const nextState = Object.assign({}, state);
        let forceSpeechRestart = false;

        if (setting === 'speechEnabled' || setting === 'embedsEnabled') {
            nextState[setting] = rawValue === '1';
            forceSpeechRestart = setting === 'speechEnabled' && nextState[setting] === true;
        } else {
            nextState[setting] = rawValue;
        }

        applyState(nextState, {
            forceSpeechRestart: forceSpeechRestart,
        });
    }

    toggle.addEventListener('click', function () {
        if (!state.enabled) {
            applyState(baselineState(), {
                forceSpeechRestart: false,
            });

            return;
        }

        applyState(Object.assign({}, state, {
            panelOpen: !state.panelOpen,
        }));
    });

    ribbon.addEventListener('click', function (event) {
        const target = event.target instanceof Element ? event.target.closest('button') : null;
        if (!target) {
            return;
        }

        const action = String(target.getAttribute('data-a11y-action') || '');
        if (action === 'font-decrease') {
            changeFontScale('down');
            return;
        }

        if (action === 'font-increase') {
            changeFontScale('up');
            return;
        }

        if (action === 'speech-play') {
            if (!state.speechEnabled) {
                applyState(Object.assign({}, state, {
                    speechEnabled: true,
                }), {forceSpeechRestart: true});
                return;
            }

            playSpeech();
            return;
        }

        if (action === 'speech-pause') {
            pauseOrResumeSpeech();
            return;
        }

        if (action === 'speech-stop') {
            stopSpeech();
            syncSpeechUi();
            return;
        }

        if (action === 'exit-normal') {
            applyState(Object.assign({}, defaultState(), {
                enabled: false,
                panelOpen: false,
                speechEnabled: false,
            }));
            return;
        }

        if (target.hasAttribute('data-a11y-setting')) {
            handleSettingButton(target);
        }
    });

    window.addEventListener('beforeunload', function () {
        stopSpeech();
    });

    applyState(loadState(), {
        initialLoad: true,
        persist: false,
    });
})();
