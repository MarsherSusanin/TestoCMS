(() => {
    const readBootPayload = (id) => {
        const el = document.getElementById(String(id || ''));
        if (!el) return {};
        try {
            return JSON.parse(el.textContent || '{}') || {};
        } catch (_) {
            return {};
        }
    };

    const createUiStorage = (probeKey) => {
        try {
            const probe = String(probeKey || '__testocms_ui_probe__');
            window.localStorage.setItem(probe, '1');
            window.localStorage.removeItem(probe);
            return window.localStorage;
        } catch (_) {
            return null;
        }
    };

    const uiRead = (storage, key, fallback = null) => {
        try {
            const value = storage ? storage.getItem(key) : null;
            return value ?? fallback;
        } catch (_) {
            return fallback;
        }
    };

    const uiWrite = (storage, key, value) => {
        try {
            if (storage) storage.setItem(key, String(value));
        } catch (_) {}
    };

    const openModal = (modal) => {
        if (!modal) return;
        modal.style.display = 'flex';
    };

    const closeModal = (modal) => {
        if (!modal) return;
        modal.style.display = 'none';
    };

    const dialogService = {
        alert(message) {
            return window.alert(String(message ?? ''));
        },
        confirm(message) {
            return window.confirm(String(message ?? ''));
        },
        prompt(message, defaultValue = '') {
            return window.prompt(String(message ?? ''), defaultValue);
        },
    };

    window.TestoCmsEditorShared = {
        readBootPayload,
        createUiStorage,
        uiRead,
        uiWrite,
        openModal,
        closeModal,
        dialogService,
    };
})();
