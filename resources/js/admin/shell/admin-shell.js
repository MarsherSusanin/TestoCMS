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

    const boot = readBootPayload('testocms-admin-shell-boot');
    const sidebar = boot.sidebar || {};
    const body = document.body;
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');
    if (!body || !toggle) return;

    const storageKey = String(sidebar.storageKey || 'testocms_admin_sidebar_collapsed');
    const desktopMq = window.matchMedia('(min-width: 1025px)');

    const readCollapsed = () => {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (_) {
            return false;
        }
    };

    const writeCollapsed = (collapsed) => {
        try {
            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (_) {
            // ignore storage failures
        }
    };

    const apply = (collapsed) => {
        const isDesktop = desktopMq.matches;
        const isCollapsed = !!collapsed && isDesktop;
        const expanded = !isCollapsed;
        body.classList.toggle('admin-sidebar-collapsed', isCollapsed);
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? String(sidebar.collapseLabel || 'Свернуть меню') : String(sidebar.expandLabel || 'Развернуть меню'));
        toggle.title = expanded ? String(sidebar.collapseTitle || 'Свернуть/развернуть меню') : String(sidebar.expandTitle || 'Развернуть/свернуть меню');
    };

    let collapsed = readCollapsed();
    apply(collapsed);

    toggle.addEventListener('click', () => {
        collapsed = !collapsed;
        writeCollapsed(collapsed);
        apply(collapsed);
    });

    const onMediaChange = () => apply(collapsed);
    if (typeof desktopMq.addEventListener === 'function') {
        desktopMq.addEventListener('change', onMediaChange);
    } else if (typeof desktopMq.addListener === 'function') {
        desktopMq.addListener(onMediaChange);
    }
})();
