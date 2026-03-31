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
    const i18n = boot.i18n || {};
    const map = i18n.map || {};
    if (!i18n.enabled || !map || typeof map !== 'object') return;

    const entries = Object.entries(map).sort((a, b) => String(b[0]).length - String(a[0]).length);
    if (entries.length === 0) return;

    const skipClosestSelectors = [
        'script',
        'style',
        'textarea',
        'code',
        'pre',
        '[contenteditable="true"]',
        '[data-html-canvas]',
        '[data-html-preview]',
        '[data-builder-preview]',
        '.builder-preview-page',
        '.preview-doc',
        '.seo-preview',
        '.cms-gallery',
        '.cms-faq',
    ].join(',');

    const translateString = (input) => {
        if (typeof input !== 'string' || input === '') return input;
        let out = input;
        for (const [from, to] of entries) {
            if (!from || from === to || !out.includes(from)) continue;
            out = out.split(from).join(String(to));
        }
        return out;
    };

    const shouldSkipTextNode = (node) => {
        const parent = node.parentElement;
        if (!parent) return true;
        return !!parent.closest(skipClosestSelectors);
    };

    const translateTextNodes = (root) => {
        if (!root) return;
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);

        nodes.forEach((node) => {
            if (shouldSkipTextNode(node)) return;
            const original = node.nodeValue;
            if (!original || !/[А-Яа-яЁё]/.test(original)) return;
            const translated = translateString(original);
            if (translated !== original) node.nodeValue = translated;
        });
    };

    const translateAttrs = (root) => {
        if (!root || !(root instanceof Element || root instanceof Document)) return;
        const selector = '[title],[aria-label],[placeholder],[data-placeholder]';
        const elements = root instanceof Document
            ? Array.from(root.querySelectorAll(selector))
            : [root, ...root.querySelectorAll(selector)];

        elements.forEach((el) => {
            if (!(el instanceof Element) || el.closest(skipClosestSelectors)) return;
            ['title', 'aria-label', 'placeholder', 'data-placeholder'].forEach((attr) => {
                const value = el.getAttribute(attr);
                if (!value || !/[А-Яа-яЁё]/.test(value)) return;
                const translated = translateString(value);
                if (translated !== value) el.setAttribute(attr, translated);
            });
        });
    };

    const translateTitle = () => {
        if (!document.title || !/[А-Яа-яЁё]/.test(document.title)) return;
        document.title = translateString(document.title);
    };

    const translateRoot = (root) => {
        translateTextNodes(root instanceof Document ? root.documentElement : root);
        translateAttrs(root);
        translateTitle();
    };

    translateRoot(document);

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'characterData' && mutation.target instanceof Text) {
                const node = mutation.target;
                if (shouldSkipTextNode(node)) continue;
                const original = node.nodeValue || '';
                if (!/[А-Яа-яЁё]/.test(original)) continue;
                const translated = translateString(original);
                if (translated !== original) node.nodeValue = translated;
                continue;
            }

            if (mutation.type === 'attributes' && mutation.target instanceof Element) {
                translateAttrs(mutation.target);
                continue;
            }

            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) translateRoot(node);
                if (node instanceof Text && !shouldSkipTextNode(node)) {
                    const original = node.nodeValue || '';
                    if (/[А-Яа-яЁё]/.test(original)) {
                        const translated = translateString(original);
                        if (translated !== original) node.nodeValue = translated;
                    }
                }
            });
        }
    });

    observer.observe(document.documentElement, {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true,
        attributeFilter: ['title', 'aria-label', 'placeholder', 'data-placeholder'],
    });

    window.TestoCmsAdminI18n = {
        locale: String(boot.locale || 'en'),
        translateString,
        translateRoot,
        map,
    };
})();
