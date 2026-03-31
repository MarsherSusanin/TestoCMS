(() => {
    const bridge = window.TestoCmsPageBuilderBridge;
    if (!bridge || !bridge.form) return;

    const form = bridge.form;
    const dialogs = window.TestoCmsEditorShared?.dialogService || window;
    const overlay = document.querySelector('[data-page-fullscreen-overlay]');
    if (!overlay) return;

    const refs = {
        shell: overlay.querySelector('.page-fullscreen-shell'),
        localeTabs: overlay.querySelector('[data-page-fullscreen-locale-tabs]'),
        deviceToggle: overlay.querySelector('[data-page-fullscreen-device-toggle]'),
        centerToggle: overlay.querySelector('[data-page-fullscreen-center-toggle]'),
        leftTabs: overlay.querySelector('[data-page-fullscreen-left-tabs]'),
        rightTabs: overlay.querySelector('[data-page-fullscreen-right-tabs]'),
        leftPanels: Array.from(overlay.querySelectorAll('[data-fs-left-panel]')),
        rightPanels: Array.from(overlay.querySelectorAll('[data-fs-right-panel]')),
        canvas: overlay.querySelector('[data-page-fullscreen-canvas]'),
        frame: overlay.querySelector('[data-page-fullscreen-frame]'),
        canvasViewport: overlay.querySelector('[data-page-fullscreen-canvas-viewport]'),
        stageWrap: overlay.querySelector('[data-page-fullscreen-stage-wrap]'),
        stageIframe: overlay.querySelector('[data-page-fullscreen-stage-iframe]'),
        stageOverlay: overlay.querySelector('[data-page-fullscreen-stage-overlay]'),
        stageBanner: overlay.querySelector('[data-page-fullscreen-stage-banner]'),
        elements: overlay.querySelector('[data-page-fullscreen-elements]'),
        presets: overlay.querySelector('[data-page-fullscreen-presets]'),
        structure: overlay.querySelector('[data-page-fullscreen-structure]'),
        inspector: overlay.querySelector('[data-page-fullscreen-inspector]'),
        layoutPanel: overlay.querySelector('[data-page-fullscreen-layout-panel]'),
        stylePanel: overlay.querySelector('[data-page-fullscreen-style-panel]'),
        seoPanel: overlay.querySelector('[data-page-fullscreen-seo-panel]'),
        publishPanel: overlay.querySelector('[data-page-fullscreen-publish-panel]'),
        title: overlay.querySelector('[data-fs-page-title]'),
        slug: overlay.querySelector('[data-fs-page-slug]'),
        syncState: overlay.querySelector('[data-page-fullscreen-sync-state]'),
        localeBadge: overlay.querySelector('[data-page-fullscreen-locale-badge]'),
        layoutState: overlay.querySelector('[data-page-fullscreen-layout-state]'),
        resizeOverlay: overlay.querySelector('[data-fs-col-resize-overlay]'),
        closeBtns: Array.from(overlay.querySelectorAll('[data-page-fullscreen-close]')),
        saveBtn: overlay.querySelector('[data-page-fullscreen-save]'),
        savePublishBtn: overlay.querySelector('[data-page-fullscreen-save-publish]'),
    };

    const KEYS = {
        open: 'testocms:admin:page-builder:fullscreen',
        leftTab: 'testocms:admin:page-builder:fullscreen:left-tab',
        rightTab: 'testocms:admin:page-builder:fullscreen:right-tab',
        device: 'testocms:admin:page-builder:fullscreen:device',
        centerMode: 'testocms:admin:page-builder:fullscreen:center-mode',
    };

    const state = {
        open: false,
        locale: bridge.getActiveLocale ? bridge.getActiveLocale() : 'ru',
        nodes: [],
        selectedNodeId: null,
        leftTab: bridge.uiRead?.(KEYS.leftTab, 'elements') || 'elements',
        rightTab: bridge.uiRead?.(KEYS.rightTab, 'content') || 'content',
        device: bridge.uiRead?.(KEYS.device, 'desktop') || 'desktop',
        centerMode: bridge.uiRead?.(KEYS.centerMode, 'stage') || 'stage',
        localDirty: false,
        dragSourceNodePath: null,
        dragPaletteType: null,
        dragColumn: null,
        columnResize: null,
        canvasRichEditingNodeId: null,
        canvasRichCommitTimer: null,
        canvasRichHasUncommittedChanges: false,
        richCommitTimer: null,
        richMode: 'visual',
        stageRenderTimer: null,
        stageRenderAbort: null,
        stageLastPayloadHash: '',
        stageFrameReady: false,
        stageSelectedMarkerId: null,
        stageHoverMarkerId: null,
        stageRichInlineNodeId: null,
        stageRichHasUncommittedChanges: false,
        stageRichRenderIdleTimer: null,
        stageDropCandidate: null,
        tableInspectorHasUncommittedChanges: false,
    };

    const layoutLabels = {
        section: 'Секция',
        columns: 'Колонки',
    };

    const leafTypes = (bridge.allowedTypes || []).filter((t) => !['section', 'columns'].includes(String(t || '')));

    const shouldAutoOpenFullscreen = () => window.innerWidth >= 1024;
    const esc = bridge.escapeHtml || ((v) => String(v || ''));
    const clone = bridge.cloneJson || ((v) => JSON.parse(JSON.stringify(v)));
    const containsStructuredLayout = bridge.containsStructuredLayout || ((nodes) => Array.isArray(nodes) && nodes.some((n) => ['section', 'columns'].includes(String(n?.type || ''))));
    const normalizeLayoutNodes = bridge.normalizeLayoutNodes || ((nodes) => Array.isArray(nodes) ? nodes : []);

    const makeId = (prefix = 'n') => `${prefix}_${Math.random().toString(36).slice(2, 8)}${Date.now().toString(36).slice(-4)}`;

    const makeSection = (label = 'Секция') => ({
        id: makeId('sec'),
        type: 'section',
        data: {
            label,
            container: 'boxed',
            padding_y: 'md',
            background: 'none',
            background_color: '',
            anchor_id: '',
        },
        children: [],
    });

    const makeColumns = (count = 2) => {
        const normalized = Math.min(4, Math.max(2, Number(count || 2)));
        const base = Math.floor(12 / normalized);
        let rest = 12 - (base * normalized);
        const columns = Array.from({ length: normalized }).map((_, idx) => {
            const extra = rest > 0 ? 1 : 0;
            if (rest > 0) rest -= 1;
            return {
                id: makeId(`col${idx + 1}`),
                span: base + extra,
                children: [],
            };
        });
        return {
            id: makeId('cols'),
            type: 'columns',
            data: {
                gap: 'md',
                align_y: 'stretch',
                columns,
            },
        };
    };

    const makeLeaf = (type) => {
        const base = bridge.defaultBlock ? bridge.defaultBlock(type) : { type, data: {} };
        return { ...clone(base), id: makeId('blk') };
    };

    const ensureStructuredNodes = (nodes, options = {}) => ensureNodeIds(
        normalizeLayoutNodes(Array.isArray(nodes) ? nodes : [], {
            fallbackHtml: String(options.fallbackHtml || ''),
        })
    );

    const ensureNodeIds = (nodes) => {
        if (!Array.isArray(nodes)) return [];
        nodes.forEach((node) => {
            if (!node || typeof node !== 'object') return;
            if (!node.id) node.id = makeId(node.type === 'section' ? 'sec' : node.type === 'columns' ? 'cols' : 'blk');
            if (node.type === 'section') {
                if (!Array.isArray(node.children)) node.children = [];
                ensureNodeIds(node.children);
                node.data = (node.data && typeof node.data === 'object') ? node.data : {};
                node.data.container = ['boxed', 'wide', 'full'].includes(String(node.data.container || '')) ? node.data.container : 'boxed';
                node.data.padding_y = ['none', 'sm', 'md', 'lg', 'xl'].includes(String(node.data.padding_y || '')) ? node.data.padding_y : 'md';
                node.data.background = ['none', 'surface', 'brand-soft', 'custom'].includes(String(node.data.background || '')) ? node.data.background : 'none';
                node.data.background_color = String(node.data.background_color || '');
                node.data.label = String(node.data.label || '');
                node.data.anchor_id = String(node.data.anchor_id || '');
            } else if (node.type === 'columns') {
                node.data = (node.data && typeof node.data === 'object') ? node.data : {};
                node.data.gap = ['sm', 'md', 'lg'].includes(String(node.data.gap || '')) ? node.data.gap : 'md';
                node.data.align_y = ['start', 'center', 'end', 'stretch'].includes(String(node.data.align_y || '')) ? node.data.align_y : 'stretch';
                if (!Array.isArray(node.data.columns) || node.data.columns.length < 2) node.data.columns = makeColumns(2).data.columns;
                node.data.columns = node.data.columns.slice(0, 4).map((col, idx) => {
                    const safe = (col && typeof col === 'object') ? col : {};
                    if (!safe.id) safe.id = makeId(`col${idx + 1}`);
                    safe.span = Math.min(12, Math.max(1, Number(safe.span || 6)));
                    if (!Array.isArray(safe.children)) safe.children = [];
                    ensureNodeIds(safe.children);
                    return safe;
                });
                rebalanceColumns(node, { soft: true });
            } else {
                node.data = (node.data && typeof node.data === 'object') ? node.data : {};
            }
        });
        return nodes;
    };

    const isLeafNode = (node) => !!node && typeof node === 'object' && !['section', 'columns'].includes(String(node.type || ''));

    const rebalanceColumns = (columnsNode, opts = {}) => {
        if (!columnsNode || columnsNode.type !== 'columns') return;
        const columns = Array.isArray(columnsNode?.data?.columns) ? columnsNode.data.columns : [];
        if (columns.length === 0) return;
        let sum = columns.reduce((acc, c) => acc + Math.max(1, Math.min(12, Number(c?.span || 0))), 0);
        if (sum === 12 && opts.soft) return;
        const base = Math.floor(12 / columns.length);
        let rest = 12 - base * columns.length;
        columns.forEach((col) => {
            const extra = rest > 0 ? 1 : 0;
            if (rest > 0) rest -= 1;
            col.span = base + extra;
        });
    };

    const parsePath = (path) => String(path || '').split('.').filter(Boolean);
    const pathStartsWith = (a, b) => String(a || '') === String(b || '') || String(a || '').startsWith(`${String(b || '')}.`);

    const resolveListRef = (rootNodes, path) => {
        const tokens = parsePath(path);
        if (tokens[0] !== 'root') return null;
        let currentList = rootNodes;
        let currentNode = null;
        for (let i = 1; i < tokens.length; i++) {
            const token = tokens[i];
            if (/^\d+$/.test(token)) {
                const idx = Number(token);
                if (!Array.isArray(currentList) || !currentList[idx]) return null;
                currentNode = currentList[idx];
                continue;
            }
            if (token === 'children') {
                if (!currentNode) return null;
                currentNode.children = Array.isArray(currentNode.children) ? currentNode.children : [];
                currentList = currentNode.children;
                continue;
            }
            if (token === 'data') continue;
            if (token === 'columns') {
                if (!currentNode) return null;
                currentNode.data = (currentNode.data && typeof currentNode.data === 'object') ? currentNode.data : {};
                currentNode.data.columns = Array.isArray(currentNode.data.columns) ? currentNode.data.columns : [];
                const colIdxToken = tokens[++i];
                if (!/^\d+$/.test(String(colIdxToken || ''))) return null;
                const colIdx = Number(colIdxToken);
                const column = currentNode.data.columns[colIdx];
                if (!column) return null;
                const childToken = tokens[++i];
                if (childToken !== 'children') return null;
                column.children = Array.isArray(column.children) ? column.children : [];
                currentList = column.children;
                currentNode = null;
                continue;
            }
        }
        return Array.isArray(currentList) ? currentList : null;
    };

    const resolveNodeRef = (rootNodes, path) => {
        const tokens = parsePath(path);
        if (tokens[0] !== 'root') return null;
        const last = tokens[tokens.length - 1];
        if (!/^\d+$/.test(String(last || ''))) return null;
        const parentPath = tokens.slice(0, -1).join('.');
        const parentList = resolveListRef(rootNodes, parentPath);
        if (!Array.isArray(parentList)) return null;
        const index = Number(last);
        if (!parentList[index]) return null;
        return { parentList, index, node: parentList[index], parentPath };
    };

    const classifyListPath = (path) => {
        const p = String(path || '');
        if (p === 'root') return 'root';
        if (/\.data\.columns\.\d+\.children$/.test(p)) return 'column-children';
        if (/\.children$/.test(p)) return 'section-children';
        return 'unknown';
    };

    const canPlaceNodeInList = (node, listPath) => {
        if (!node || typeof node !== 'object') return false;
        const kind = classifyListPath(listPath);
        const type = String(node.type || '');
        if (kind === 'root') return type === 'section';
        if (kind === 'section-children') return isLeafNode(node) || type === 'columns';
        if (kind === 'column-children') return isLeafNode(node);
        return false;
    };

    const walkNodes = (nodes, cb, listPath = 'root') => {
        if (!Array.isArray(nodes)) return;
        nodes.forEach((node, index) => {
            const nodePath = `${listPath}.${index}`;
            cb(node, nodePath, listPath);
            if (!node || typeof node !== 'object') return;
            if (node.type === 'section' && Array.isArray(node.children)) {
                walkNodes(node.children, cb, `${nodePath}.children`);
            }
            const cols = node?.data?.columns;
            if (node.type === 'columns' && Array.isArray(cols)) {
                cols.forEach((col, colIndex) => {
                    if (Array.isArray(col?.children)) {
                        walkNodes(col.children, cb, `${nodePath}.data.columns.${colIndex}.children`);
                    }
                });
            }
        });
    };

    const findNodePathById = (nodes, id) => {
        let found = null;
        walkNodes(nodes, (node, nodePath) => {
            if (found) return;
            if (String(node?.id || '') === String(id || '')) found = nodePath;
        });
        return found;
    };

    const getSelectedRef = () => {
        if (!state.selectedNodeId) return null;
        const path = findNodePathById(state.nodes, state.selectedNodeId);
        if (!path) return null;
        return resolveNodeRef(state.nodes, path);
    };

    const getNodeRefById = (id) => {
        const path = findNodePathById(state.nodes, id);
        if (!path) return null;
        return resolveNodeRef(state.nodes, path);
    };

    const getCurrentLocale = () => String(state.locale || bridge.getActiveLocale?.() || 'ru');

    const setDirtyOnly = (message = 'Изменения в fullscreen') => {
        state.localDirty = true;
        setSyncState(message, 'saving');
    };

    const getLocaleFields = (locale = getCurrentLocale()) => ({
        title: form.querySelector(`[data-page-title="${locale}"]`),
        slug: form.querySelector(`[data-page-slug="${locale}"]`),
        metaTitle: form.querySelector(`[data-page-meta-title="${locale}"]`),
        canonical: form.querySelector(`[data-page-canonical="${locale}"]`),
        metaDescription: form.querySelector(`[data-page-meta-description="${locale}"]`),
        customHead: form.querySelector(`[name="translations[${locale}][custom_head_html]"]`),
        blocksJson: form.querySelector(`[data-locale-pane="${locale}"] [data-blocks-json]`),
    });

    const getPageStatusField = () => form.querySelector('#page-status');
    const getPageTypeField = () => form.querySelector('#page-type');

    const setSyncState = (text, mode = '') => {
        if (!refs.syncState) return;
        refs.syncState.textContent = text;
        refs.syncState.classList.remove('saving', 'restored');
        if (mode) refs.syncState.classList.add(mode);
    };

    const refreshTopMeta = () => {
        const locale = getCurrentLocale();
        const { title, slug } = getLocaleFields(locale);
        const titleValue = String(title?.value || '').trim() || 'Страница';
        const slugValue = String(slug?.value || '').trim() || 'slug';
        refs.title.textContent = titleValue;
        refs.slug.textContent = `/${locale}/${slugValue.toLowerCase() === 'home' ? '' : slugValue}`.replace(/\/$/, '') || `/${locale}`;
        refs.localeBadge.textContent = `Локаль: ${locale.toUpperCase()}`;
        refs.layoutState.textContent = 'Секции и колонки';
    };

    const getCsrfToken = () => String(form.querySelector('input[name="_token"]')?.value || '');
    const stageRenderUrl = String(overlay.getAttribute('data-page-fullscreen-stage-render-url') || '');
    const stageOverlayUi = {
        selectedBox: null,
        hoverBox: null,
        actions: null,
        dropBox: null,
        dropLabel: null,
    };

    const showStageBanner = (text, isError = false) => {
        if (!refs.stageBanner) return;
        refs.stageBanner.textContent = String(text || '');
        refs.stageBanner.classList.toggle('error', !!isError);
        refs.stageBanner.classList.toggle('fs-hidden', !text);
    };

    const hideStageBanner = () => showStageBanner('');

    const ensureStageOverlayUi = () => {
        if (!refs.stageOverlay) return null;
        if (!stageOverlayUi.selectedBox) {
            const el = document.createElement('div');
            el.className = 'fs-stage-box';
            el.hidden = true;
            refs.stageOverlay.appendChild(el);
            stageOverlayUi.selectedBox = el;
        }
        if (!stageOverlayUi.hoverBox) {
            const el = document.createElement('div');
            el.className = 'fs-stage-box hover';
            el.hidden = true;
            refs.stageOverlay.appendChild(el);
            stageOverlayUi.hoverBox = el;
        }
        if (!stageOverlayUi.actions) {
            const el = document.createElement('div');
            el.className = 'fs-stage-actions';
            el.hidden = true;
            el.innerHTML = `
                <button type="button" class="fs-node-btn" data-fs-stage-action="edit" title="Редактировать">✎</button>
                <button type="button" class="fs-node-btn" data-fs-stage-action="up" title="Выше">↑</button>
                <button type="button" class="fs-node-btn" data-fs-stage-action="down" title="Ниже">↓</button>
                <button type="button" class="fs-node-btn" data-fs-stage-action="duplicate" title="Дублировать">⧉</button>
                <button type="button" class="fs-node-btn danger" data-fs-stage-action="delete" title="Удалить">✕</button>
            `;
            refs.stageOverlay.appendChild(el);
            stageOverlayUi.actions = el;
        }
        if (!stageOverlayUi.dropBox) {
            const el = document.createElement('div');
            el.className = 'fs-stage-box fs-stage-drop-box';
            el.hidden = true;
            refs.stageOverlay.appendChild(el);
            stageOverlayUi.dropBox = el;
        }
        if (!stageOverlayUi.dropLabel) {
            const el = document.createElement('div');
            el.className = 'fs-stage-drop-label';
            el.hidden = true;
            refs.stageOverlay.appendChild(el);
            stageOverlayUi.dropLabel = el;
        }
        return stageOverlayUi;
    };

    const hideStageOverlaySelectionUi = () => {
        if (stageOverlayUi.selectedBox) stageOverlayUi.selectedBox.hidden = true;
        if (stageOverlayUi.hoverBox) stageOverlayUi.hoverBox.hidden = true;
        if (stageOverlayUi.actions) stageOverlayUi.actions.hidden = true;
    };
    const hideStageDropHintUi = () => {
        if (stageOverlayUi.dropBox) stageOverlayUi.dropBox.hidden = true;
        if (stageOverlayUi.dropLabel) stageOverlayUi.dropLabel.hidden = true;
    };

    const setCenterMode = (mode) => {
        state.centerMode = String(mode) === 'canvas' ? 'canvas' : 'stage';
        refs.centerToggle?.querySelectorAll('[data-fs-center-mode]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-fs-center-mode') === state.centerMode);
        });
        refs.stageWrap?.classList.toggle('fs-hidden', state.centerMode !== 'stage');
        refs.canvasViewport?.classList.toggle('fs-hidden', state.centerMode !== 'canvas');
        bridge.uiWrite?.(KEYS.centerMode, state.centerMode);
        if (state.centerMode !== 'stage') {
            flushStagePendingRender();
            if (state.stageRenderAbort) {
                try { state.stageRenderAbort.abort(); } catch (_) {}
                state.stageRenderAbort = null;
            }
            state.stageDropCandidate = null;
            hideStageDropHintUi();
            closeStageInlineEditor({ commit: true });
        }
        if (state.open && state.centerMode === 'stage') {
            scheduleStageRender(10, 'center-mode');
            window.requestAnimationFrame(recomputeStageOverlayRects);
        }
    };

    const buildStagePayload = () => {
        const locale = getCurrentLocale();
        const fields = getLocaleFields(locale);
        const statusField = getPageStatusField();
        const pageTypeField = getPageTypeField();
        const title = String(fields.title?.value || '').trim();
        const slug = String(fields.slug?.value || '').trim();
        return {
            locale,
            page: {
                title,
                slug,
                status: String(statusField?.value || 'draft'),
                page_type: String(pageTypeField?.value || 'landing'),
            },
            translations: {
                [locale]: {
                    meta_title: String(fields.metaTitle?.value || ''),
                    meta_description: String(fields.metaDescription?.value || ''),
                    canonical_url: String(fields.canonical?.value || ''),
                    custom_head_html: String(fields.customHead?.value || ''),
                    nodes: clone(state.nodes),
                },
            },
            chrome: { use_current_settings: true },
            render: {
                device: state.device,
                instrument: true,
                include_theme: true,
                include_header_footer: true,
            },
        };
    };

    const getStageDocument = () => refs.stageIframe?.contentDocument || null;
    const getStageWindow = () => refs.stageIframe?.contentWindow || null;

    const markerRectFromStageEl = (el) => {
        if (!el) return null;
        let rect = null;
        try {
            rect = el.getBoundingClientRect();
        } catch (_) {
            rect = null;
        }
        const hasSize = rect && rect.width > 0 && rect.height > 0;
        if (!hasSize) {
            const child = el.firstElementChild;
            if (child) {
                try {
                    rect = child.getBoundingClientRect();
                } catch (_) {}
            }
        }
        if (!rect || rect.width <= 0 || rect.height <= 0) return null;
        // DOMRect from iframe content is in iframe viewport coordinates.
        // Stage overlay lives in parent document coordinates, so we must translate.
        const iframeRect = refs.stageIframe?.getBoundingClientRect?.();
        if (!iframeRect) return rect;
        return {
            left: iframeRect.left + rect.left,
            top: iframeRect.top + rect.top,
            right: iframeRect.left + rect.right,
            bottom: iframeRect.top + rect.bottom,
            width: rect.width,
            height: rect.height,
        };
    };

    const normalizeStagePointerToParent = (evt) => {
        const x = Number(evt?.clientX);
        const y = Number(evt?.clientY);
        if (!Number.isFinite(x) || !Number.isFinite(y)) return null;
        const iframeDoc = getStageDocument();
        const iframeRect = refs.stageIframe?.getBoundingClientRect?.();
        const targetDoc = evt?.target?.ownerDocument || null;
        if (iframeDoc && iframeRect && targetDoc === iframeDoc) {
            return { x: iframeRect.left + x, y: iframeRect.top + y };
        }
        return { x, y };
    };

    const getStageMarkerByNodeId = (nodeId) => {
        const doc = getStageDocument();
        if (!doc || !nodeId) return null;
        return doc.querySelector(`[data-builder-node-id="${CSS.escape(String(nodeId))}"]`);
    };

    const resolveStageTarget = (target) => {
        let el = target || null;
        if (el && Number(el.nodeType) === 3) {
            el = el.parentElement || el.parentNode || null;
        }
        if (!el || Number(el.nodeType) !== 1 || typeof el.closest !== 'function') return null;

        const nodeEl = el.closest('[data-builder-node-id]');
        const columnEl = el.closest('[data-builder-column-id]');
        let chosenNodeEl = nodeEl;
        let chosenColumnEl = columnEl;
        if (nodeEl && columnEl) {
            // Choose the deeper marker:
            // - leaf block inside column => node marker
            // - empty click in column area => column marker
            if (typeof nodeEl.contains === 'function' && nodeEl.contains(columnEl)) {
                chosenNodeEl = null;
            } else if (typeof columnEl.contains === 'function' && columnEl.contains(nodeEl)) {
                chosenColumnEl = null;
            }
        }
        if (chosenNodeEl) {
            return {
                type: 'node',
                nodeId: chosenNodeEl.getAttribute('data-builder-node-id'),
                nodeType: chosenNodeEl.getAttribute('data-builder-node-type'),
                markerEl: chosenNodeEl,
            };
        }
        if (chosenColumnEl) {
            const owner = chosenColumnEl.getAttribute('data-builder-owner-node-id');
            const columnId = chosenColumnEl.getAttribute('data-builder-column-id');
            return owner ? { type: 'column', ownerNodeId: owner, columnId, markerEl: chosenColumnEl } : null;
        }
        return null;
    };

    const recomputeStageOverlayRects = () => {
        if (!state.open || state.centerMode !== 'stage' || !refs.stageOverlay) return;
        ensureStageOverlayUi();
        const overlayRect = refs.stageOverlay.getBoundingClientRect();
        const placeBox = (el, box, forceShow = true) => {
            if (!box) return;
            const rect = markerRectFromStageEl(el);
            if (!rect) {
                box.hidden = true;
                return;
            }
            box.hidden = !forceShow;
            if (!forceShow) return;
            box.style.left = `${rect.left - overlayRect.left}px`;
            box.style.top = `${rect.top - overlayRect.top}px`;
            box.style.width = `${rect.width}px`;
            box.style.height = `${rect.height}px`;
        };

        const selectedMarker = state.stageSelectedMarkerId ? getStageMarkerByNodeId(state.stageSelectedMarkerId) : null;
        const hoverMarker = state.stageHoverMarkerId ? getStageMarkerByNodeId(state.stageHoverMarkerId) : null;
        placeBox(selectedMarker, stageOverlayUi.selectedBox, !!selectedMarker);
        placeBox(hoverMarker && hoverMarker !== selectedMarker ? hoverMarker : null, stageOverlayUi.hoverBox, !!hoverMarker && hoverMarker !== selectedMarker);

        if (stageOverlayUi.actions) {
            const rect = markerRectFromStageEl(selectedMarker);
            if (!rect || !state.stageSelectedMarkerId) {
                stageOverlayUi.actions.hidden = true;
            } else {
                stageOverlayUi.actions.hidden = false;
                const left = Math.max(6, rect.left - overlayRect.left);
                const top = Math.max(28, rect.top - overlayRect.top);
                stageOverlayUi.actions.style.left = `${left}px`;
                stageOverlayUi.actions.style.top = `${top}px`;
            }
        }
        if (stageOverlayUi.dropBox || stageOverlayUi.dropLabel) {
            const candidate = state.stageDropCandidate;
            let marker = null;
            if (candidate?.markerKind === 'column' && candidate.columnId) {
                const doc = getStageDocument();
                marker = doc?.querySelector?.(`[data-builder-column-id="${CSS.escape(String(candidate.columnId))}"]`) || null;
            } else if (candidate?.markerKind === 'node' && candidate.nodeId) {
                marker = getStageMarkerByNodeId(candidate.nodeId);
            }
            const rect = markerRectFromStageEl(marker);
            if (!candidate || !rect) {
                hideStageDropHintUi();
            } else {
                if (stageOverlayUi.dropBox) {
                    stageOverlayUi.dropBox.hidden = false;
                    let left = rect.left - overlayRect.left;
                    let top = rect.top - overlayRect.top;
                    let width = rect.width;
                    let height = rect.height;
                    if (candidate.position === 'before' || candidate.position === 'after') {
                        height = 6;
                        top = (candidate.position === 'before' ? rect.top : (rect.bottom - 6)) - overlayRect.top;
                    }
                    stageOverlayUi.dropBox.style.left = `${left}px`;
                    stageOverlayUi.dropBox.style.top = `${top}px`;
                    stageOverlayUi.dropBox.style.width = `${width}px`;
                    stageOverlayUi.dropBox.style.height = `${height}px`;
                }
                if (stageOverlayUi.dropLabel) {
                    stageOverlayUi.dropLabel.hidden = false;
                    const left = Math.max(8, Math.min(overlayRect.width - 180, rect.left - overlayRect.left));
                    const top = Math.max(24, (candidate.position === 'after' ? rect.bottom : rect.top) - overlayRect.top);
                    stageOverlayUi.dropLabel.style.left = `${left}px`;
                    stageOverlayUi.dropLabel.style.top = `${top}px`;
                    stageOverlayUi.dropLabel.textContent = candidate.label || 'Вставка сюда';
                }
            }
        }
        const inlinePanel = getStageInlinePanel();
        if (inlinePanel && state.stageRichInlineNodeId) {
            positionStageInlinePanel(inlinePanel, state.stageRichInlineNodeId);
        }
    };

    const attachStageFrameBridge = () => {
        const doc = getStageDocument();
        const win = getStageWindow();
        if (!doc || !win) return;
        if (doc.__testocmsStageBridgeAttached) {
            recomputeStageOverlayRects();
            return;
        }
        doc.__testocmsStageBridgeAttached = true;

        doc.addEventListener('click', (e) => {
            const target = resolveStageTarget(e.target);
            if (!target) return;
            e.preventDefault();
            e.stopPropagation();
            const nodeId = target.type === 'column' ? String(target.ownerNodeId || '') : String(target.nodeId || '');
            if (!nodeId) return;
            state.selectedNodeId = nodeId;
            state.stageSelectedMarkerId = nodeId;
            if (target.type === 'node' && isLeafNode(getNodeRefById(nodeId)?.node)) {
                setRightTab('content');
            } else {
                setRightTab('layout');
            }
            renderStructure();
            renderInspector();
            recomputeStageOverlayRects();
        }, true);

        doc.addEventListener('dblclick', (e) => {
            const target = resolveStageTarget(e.target);
            if (!target) return;
            const nodeId = target.type === 'column' ? String(target.ownerNodeId || '') : String(target.nodeId || '');
            const ref = getNodeRefById(nodeId);
            if (!ref || !ref.node) return;
            if (!['rich_text', 'heading', 'cta', 'faq'].includes(String(ref.node.type || ''))) return;
            e.preventDefault();
            e.stopPropagation();
            openStageInlineEditor(nodeId);
        }, true);

        doc.addEventListener('mouseover', (e) => {
            const target = resolveStageTarget(e.target);
            if (!target) {
                state.stageHoverMarkerId = null;
                recomputeStageOverlayRects();
                return;
            }
            state.stageHoverMarkerId = String(target.type === 'column' ? (target.ownerNodeId || '') : (target.nodeId || ''));
            recomputeStageOverlayRects();
        }, true);

        doc.addEventListener('mouseout', (e) => {
            if (!e.relatedTarget) {
                state.stageHoverMarkerId = null;
                recomputeStageOverlayRects();
            }
        }, true);

        doc.addEventListener('dragover', (e) => {
            if (!state.dragPaletteType) return;
            e.preventDefault();
            state.stageDropCandidate = computeStageDropCandidateForPalette(state.dragPaletteType, e.target, e);
            recomputeStageOverlayRects();
        }, true);
        doc.addEventListener('drop', (e) => {
            if (!state.dragPaletteType) return;
            e.preventDefault();
            e.stopPropagation();
            const stageTarget = resolveStageTarget(e.target);
            if (stageTarget) {
                const nodeId = stageTarget.type === 'column' ? String(stageTarget.ownerNodeId || '') : String(stageTarget.nodeId || '');
                if (nodeId) {
                    state.selectedNodeId = nodeId;
                    state.stageSelectedMarkerId = nodeId;
                }
            }
            const node = createPaletteNode(state.dragPaletteType);
            const candidate = state.stageDropCandidate || computeStageDropCandidateForPalette(state.dragPaletteType, e.target, e);
            const listPath = candidate?.listPath || null;
            if (!listPath) {
                state.stageDropCandidate = null;
                hideStageDropHintUi();
                dialogs.alert('Выберите секцию/колонку, чтобы вставить элемент на Stage.');
                return;
            }
            insertIntoList(listPath, node, Number.isInteger(candidate?.index) ? candidate.index : null);
            state.dragPaletteType = null;
            state.stageDropCandidate = null;
            hideStageDropHintUi();
        }, true);

        win.addEventListener('scroll', () => recomputeStageOverlayRects(), { passive: true });
        win.addEventListener('resize', () => recomputeStageOverlayRects());
        try {
            const ro = new ResizeObserver(() => recomputeStageOverlayRects());
            if (doc.body) ro.observe(doc.body);
            if (doc.documentElement) ro.observe(doc.documentElement);
            doc.__testocmsStageResizeObserver = ro;
        } catch (_) {}
    };

    const renderStageNow = async (force = false) => {
        if (!state.open || state.centerMode !== 'stage' || !stageRenderUrl) return;
        const payload = buildStagePayload();
        const hash = JSON.stringify(payload);
        if (!force && hash === state.stageLastPayloadHash) {
            window.requestAnimationFrame(recomputeStageOverlayRects);
            return;
        }
        state.stageLastPayloadHash = hash;

        if (state.stageRenderAbort) {
            try { state.stageRenderAbort.abort(); } catch (_) {}
            state.stageRenderAbort = null;
        }
        const controller = new AbortController();
        state.stageRenderAbort = controller;
        setSyncState('Обновление Stage…', 'saving');
        hideStageBanner();

        try {
            const response = await fetch(stageRenderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/html',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
                signal: controller.signal,
            });
            if (!response.ok) {
                const text = await response.text().catch(() => '');
                throw new Error(`Stage render failed (${response.status})${text ? `: ${text.slice(0, 200)}` : ''}`);
            }
            const html = await response.text();
            if (controller.signal.aborted) return;
            refs.stageIframe.srcdoc = html;
            setSyncState('Stage обновлён');
        } catch (error) {
            if (controller.signal.aborted) return;
            console.error('[FullscreenStage] render error', error);
            showStageBanner('Не удалось обновить Live Stage. Используйте Structure Canvas или попробуйте ещё раз.', true);
            setSyncState('Ошибка обновления Stage');
        } finally {
            if (state.stageRenderAbort === controller) {
                state.stageRenderAbort = null;
            }
        }
    };

    const scheduleStageRender = (delay = 300, _reason = '') => {
        if (!state.open || state.centerMode !== 'stage') return;
        if (state.stageRenderTimer) {
            window.clearTimeout(state.stageRenderTimer);
            state.stageRenderTimer = null;
        }
        state.stageRenderTimer = window.setTimeout(() => {
            state.stageRenderTimer = null;
            renderStageNow(false);
        }, Math.max(0, Number(delay || 0)));
    };

    const flushStagePendingRender = () => {
        if (state.stageRenderTimer) {
            window.clearTimeout(state.stageRenderTimer);
            state.stageRenderTimer = null;
        }
    };

    const syncNodesToBuilder = () => {
        const builder = bridge.getBuilder?.(getCurrentLocale());
        if (!builder) return;
        state.nodes = ensureStructuredNodes(state.nodes);
        state.stageSelectedMarkerId = state.selectedNodeId ? String(state.selectedNodeId) : null;
        builder.setNodes(clone(state.nodes));
        bridge.syncBuilders?.();
        setSyncState('Синхронизировано с формой');
        state.localDirty = true;
        renderStructure();
        renderCanvas();
        renderInspector();
        scheduleStageRender(260, 'nodes-sync');
    };

    let syncRaf = 0;
    const scheduleSyncNodesToBuilder = (message = 'Синхронизировано с формой') => {
        if (syncRaf) return;
        syncRaf = window.requestAnimationFrame(() => {
            syncRaf = 0;
            syncNodesToBuilder();
            if (message) setSyncState(message);
        });
    };

    const resolveColumnsNodeRefByPath = (nodePath) => {
        const ref = resolveNodeRef(state.nodes, nodePath);
        if (!ref || ref.node?.type !== 'columns') return null;
        return ref;
    };

    const moveColumn = (ownerNodePath, fromIndex, toIndex) => {
        const ref = resolveColumnsNodeRefByPath(ownerNodePath);
        if (!ref) return;
        const cols = Array.isArray(ref.node?.data?.columns) ? ref.node.data.columns : [];
        if (!Number.isInteger(fromIndex) || !Number.isInteger(toIndex)) return;
        if (!cols[fromIndex]) return;
        const boundedTarget = Math.max(0, Math.min(cols.length - 1, toIndex));
        if (fromIndex === boundedTarget) return;
        const [moved] = cols.splice(fromIndex, 1);
        cols.splice(boundedTarget, 0, moved);
        syncNodesToBuilder();
    };

    const moveColumnBetweenNodes = (fromOwnerNodePath, fromIndex, toOwnerNodePath, toIndex) => {
        if (String(fromOwnerNodePath) === String(toOwnerNodePath)) {
            moveColumn(fromOwnerNodePath, fromIndex, toIndex);
            return true;
        }
        const sourceRef = resolveColumnsNodeRefByPath(fromOwnerNodePath);
        const targetRef = resolveColumnsNodeRefByPath(toOwnerNodePath);
        if (!sourceRef || !targetRef) return false;
        const sourceCols = Array.isArray(sourceRef.node?.data?.columns) ? sourceRef.node.data.columns : [];
        const targetCols = Array.isArray(targetRef.node?.data?.columns) ? targetRef.node.data.columns : [];
        if (!sourceCols[fromIndex]) return false;
        if (sourceCols.length <= 2) {
            dialogs.alert('Нельзя перенести колонку: в исходной группе должно остаться минимум 2 колонки.');
            return false;
        }
        if (targetCols.length >= 4) {
            dialogs.alert('Нельзя перенести колонку: в целевой группе может быть максимум 4 колонки.');
            return false;
        }
        const insertIndex = Math.max(0, Math.min(targetCols.length, Number.isInteger(toIndex) ? toIndex : targetCols.length));
        const [moved] = sourceCols.splice(fromIndex, 1);
        if (!moved) return false;
        targetCols.splice(insertIndex, 0, moved);
        rebalanceColumns(sourceRef.node);
        rebalanceColumns(targetRef.node);
        syncNodesToBuilder();
        return true;
    };

    const applyColumnSpansPair = (ownerNodePath, leftIndex, newLeftSpan) => {
        const ref = resolveColumnsNodeRefByPath(ownerNodePath);
        if (!ref) return false;
        const cols = Array.isArray(ref.node?.data?.columns) ? ref.node.data.columns : [];
        if (!cols[leftIndex] || !cols[leftIndex + 1]) return false;

        const left = cols[leftIndex];
        const right = cols[leftIndex + 1];
        const pairTotal = Math.max(2, Math.min(24, Number(left.span || 0) + Number(right.span || 0)));
        const boundedLeft = Math.max(1, Math.min(pairTotal - 1, Math.round(Number(newLeftSpan || left.span || 1))));
        const boundedRight = pairTotal - boundedLeft;
        if (boundedRight < 1) return false;

        left.span = boundedLeft;
        right.span = boundedRight;
        return true;
    };

    const showResizeOverlay = (e, ownerNodePath, leftIndex) => {
        const overlayEl = refs.resizeOverlay;
        if (!overlayEl) return;
        const ref = resolveColumnsNodeRefByPath(ownerNodePath);
        const cols = Array.isArray(ref?.node?.data?.columns) ? ref.node.data.columns : [];
        if (!cols[leftIndex] || !cols[leftIndex + 1]) return;
        overlayEl.hidden = false;
        overlayEl.textContent = `${Math.round(Number(cols[leftIndex].span || 0))} / ${Math.round(Number(cols[leftIndex + 1].span || 0))}`;
        const viewportRect = refs.canvasViewport?.getBoundingClientRect?.() || refs.canvas?.getBoundingClientRect?.();
        if (!viewportRect) return;
        const x = Math.min(viewportRect.right - 24, Math.max(viewportRect.left + 24, Number(e.clientX || 0)));
        const y = Math.min(viewportRect.bottom - 12, Math.max(viewportRect.top + 36, Number(e.clientY || 0)));
        overlayEl.style.left = `${x - viewportRect.left + (refs.canvasViewport?.scrollLeft || 0)}px`;
        overlayEl.style.top = `${y - viewportRect.top + (refs.canvasViewport?.scrollTop || 0)}px`;
    };

    const hideResizeOverlay = () => {
        if (!refs.resizeOverlay) return;
        refs.resizeOverlay.hidden = true;
    };

    const insertHtmlAtSelection = (html) => {
        if (!html) return false;
        const sel = window.getSelection && window.getSelection();
        if (!sel || sel.rangeCount === 0) return false;
        const range = sel.getRangeAt(0);
        range.deleteContents();
        const fragment = range.createContextualFragment(String(html));
        const lastNode = fragment.lastChild;
        range.insertNode(fragment);
        if (lastNode) {
            range.setStartAfter(lastNode);
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
        }
        return true;
    };

    const execRichCommand = (command, value = null) => {
        try {
            document.execCommand(command, false, value);
            return true;
        } catch (_) {
            return false;
        }
    };

    const syncRichVisualToTextarea = (root = refs.inspector) => {
        if (!root) return;
        const visual = root.querySelector('[data-fs-rich-editor]');
        const source = root.querySelector('[data-fs-rich-html-source]');
        if (!visual || !source) return;
        source.value = visual.innerHTML;
        setDirtyOnly('Rich text изменён…');
        if (state.richCommitTimer) window.clearTimeout(state.richCommitTimer);
        state.richCommitTimer = window.setTimeout(() => {
            applyInspectorField('data.html', source.value, source);
            state.richCommitTimer = null;
        }, 350);
        const status = root.querySelector('[data-fs-rich-status]');
        if (status) status.textContent = 'Изменения сохранены локально, синхронизация…';
    };

    const syncCanvasRichInlineToState = (editor, opts = {}) => {
        if (!editor) return;
        const nodeId = String(editor.getAttribute('data-fs-rich-inline-editor') || '');
        if (!nodeId) return;
        const ref = getNodeRefById(nodeId);
        if (!ref || ref.node?.type !== 'rich_text') return;
        ref.node.data = (ref.node.data && typeof ref.node.data === 'object') ? ref.node.data : {};
        ref.node.data.html = editor.innerHTML;
        setDirtyOnly('Rich text (canvas) изменён…');
        state.canvasRichHasUncommittedChanges = true;
        if (opts.immediate || opts.commit) {
            if (state.canvasRichCommitTimer) window.clearTimeout(state.canvasRichCommitTimer);
            state.canvasRichCommitTimer = null;
            state.canvasRichHasUncommittedChanges = false;
            syncNodesToBuilder();
            return;
        }
        setSyncState('Rich text в canvas: локальные изменения (commit при blur/Save)', 'saving');
    };

    const flushCanvasRichLateCommit = (message = 'Rich text в canvas синхронизирован с формой') => {
        if (state.canvasRichCommitTimer) {
            window.clearTimeout(state.canvasRichCommitTimer);
            state.canvasRichCommitTimer = null;
        }
        if (!state.canvasRichHasUncommittedChanges) return;
        state.canvasRichHasUncommittedChanges = false;
        syncNodesToBuilder();
        if (message) setSyncState(message);
    };

    const normalizeTableRows = (rows, opts = {}) => {
        let safeRows = Array.isArray(rows) ? rows.filter((r) => Array.isArray(r)) : [];
        safeRows = safeRows.map((row) => row.map((cell) => String(cell ?? '')));
        const minRows = Math.max(1, Number(opts.minRows ?? 1));
        const minCols = Math.max(1, Number(opts.minCols ?? 1));
        const maxCols = Math.max(minCols, Number(opts.maxCols ?? 12));
        let colCount = safeRows.reduce((max, row) => Math.max(max, row.length), 0);
        colCount = Math.max(minCols, Math.min(maxCols, colCount || 0));
        while (safeRows.length < minRows) {
            safeRows.push(Array.from({ length: colCount }, () => ''));
        }
        return safeRows.map((row) => {
            const next = row.slice(0, colCount);
            while (next.length < colCount) next.push('');
            return next;
        });
    };

    const getSelectedTableRows = () => {
        const ref = getSelectedRef();
        if (!ref || ref.node?.type !== 'table') return null;
        ref.node.data = (ref.node.data && typeof ref.node.data === 'object') ? ref.node.data : {};
        ref.node.data.rows = normalizeTableRows(ref.node.data.rows, { minRows: 1, minCols: 1, maxCols: 12 });
        return ref.node.data.rows;
    };

    const commitTableInspectorRows = (message = 'Таблица синхронизирована с формой') => {
        if (!state.tableInspectorHasUncommittedChanges) return;
        state.tableInspectorHasUncommittedChanges = false;
        syncNodesToBuilder();
        if (message) setSyncState(message);
    };

    const flushTableInspectorLateCommit = (message = 'Таблица синхронизирована с формой') => {
        commitTableInspectorRows(message);
    };

    const getStageInlinePanel = () => refs.stageOverlay?.querySelector('[data-fs-stage-inline-panel]') || null;

    const patchStageMarkerContentFromNode = (nodeId) => {
        const marker = getStageMarkerByNodeId(nodeId);
        const ref = getNodeRefById(nodeId);
        if (!marker || !ref || !ref.node) return;
        const node = ref.node;
        const type = String(node.type || '');
        if (type === 'heading') {
            const text = String(node?.data?.text || '');
            const target = marker.matches('h1,h2,h3,h4,h5,h6') ? marker : marker.querySelector('h1,h2,h3,h4,h5,h6');
            if (target) target.textContent = text || 'Заголовок';
            return;
        }
        if (type === 'cta') {
            const a = marker.matches('a.cms-cta') ? marker : marker.querySelector('a.cms-cta');
            if (a) {
                a.textContent = String(node?.data?.label || 'CTA');
                const href = String(node?.data?.url || '#').trim();
                a.setAttribute('href', href || '#');
            }
            return;
        }
        if (type === 'rich_text') {
            const html = String(node?.data?.html || '');
            if (marker.classList.contains('cms-builder-node-wrapper') || marker.hasAttribute('data-builder-node-id')) {
                marker.innerHTML = html || '<p></p>';
            }
            return;
        }
        if (type === 'faq') {
            const firstSummary = marker.querySelector('summary');
            const firstItem = Array.isArray(node?.data?.items) ? node.data.items[0] : null;
            if (firstSummary && firstItem && typeof firstItem === 'object') {
                firstSummary.textContent = String(firstItem.question || 'Вопрос');
            }
            return;
        }
        if (type === 'table') {
            const rows = normalizeTableRows(node?.data?.rows, { minRows: 1, minCols: 1 });
            const table = marker.matches('table') ? marker : marker.querySelector('table');
            if (table) {
                const body = rows
                    .map((row) => `<tr>${row.map((cell) => `<td>${esc(String(cell || ''))}</td>`).join('')}</tr>`)
                    .join('');
                table.innerHTML = `<tbody>${body}</tbody>`;
            }
            return;
        }
    };

    const stageIdleRefreshAfterTextEdit = () => {
        if (state.stageRichRenderIdleTimer) {
            window.clearTimeout(state.stageRichRenderIdleTimer);
            state.stageRichRenderIdleTimer = null;
        }
        state.stageRichRenderIdleTimer = window.setTimeout(() => {
            state.stageRichRenderIdleTimer = null;
            scheduleStageRender(0, 'stage-text-idle');
        }, 1200);
    };

    const flushStageRichLateCommit = (message = 'Stage rich text синхронизирован с формой') => {
        if (state.stageRichRenderIdleTimer) {
            window.clearTimeout(state.stageRichRenderIdleTimer);
            state.stageRichRenderIdleTimer = null;
        }
        if (!state.stageRichHasUncommittedChanges) return;
        state.stageRichHasUncommittedChanges = false;
        syncNodesToBuilder();
        scheduleStageRender(0, 'stage-rich-flush');
        if (message) setSyncState(message);
    };

    const closeStageInlineEditor = ({ commit = true } = {}) => {
        const panel = getStageInlinePanel();
        if (commit) flushStageRichLateCommit('Stage inline commit выполнен');
        if (panel) panel.remove();
        state.stageRichInlineNodeId = null;
        recomputeStageOverlayRects();
    };

    const positionStageInlinePanel = (panel, nodeId) => {
        if (!panel || !refs.stageOverlay) return;
        const marker = getStageMarkerByNodeId(nodeId);
        const rect = markerRectFromStageEl(marker);
        const overlayRect = refs.stageOverlay.getBoundingClientRect();
        if (!rect) {
            panel.style.left = '12px';
            panel.style.top = '12px';
            return;
        }
        const panelWidth = Math.min(560, overlayRect.width - 24);
        const left = Math.min(
            overlayRect.width - panelWidth - 12,
            Math.max(12, rect.left - overlayRect.left)
        );
        let top = rect.bottom - overlayRect.top + 10;
        const maxTop = Math.max(12, overlayRect.height - 200);
        if (top > maxTop) top = Math.max(12, rect.top - overlayRect.top - 220);
        panel.style.left = `${left}px`;
        panel.style.top = `${Math.max(12, top)}px`;
        panel.style.width = `${panelWidth}px`;
    };

    const openStageInlineEditor = (nodeId) => {
        flushStageRichLateCommit('Stage inline изменения синхронизированы перед переключением элемента');
        const ref = getNodeRefById(nodeId);
        if (!ref || !ref.node) return;
        const node = ref.node;
        const type = String(node.type || '');
        if (!['rich_text', 'heading', 'cta', 'faq'].includes(type)) {
            state.selectedNodeId = nodeId;
            setRightTab(isLeafNode(node) ? 'content' : 'layout');
            renderStructure();
            renderInspector();
            return;
        }

        const existing = getStageInlinePanel();
        if (existing) existing.remove();
        ensureStageOverlayUi();
        if (!refs.stageOverlay) return;

        state.selectedNodeId = nodeId;
        state.stageSelectedMarkerId = nodeId;
        state.stageRichInlineNodeId = nodeId;
        setRightTab('content');
        renderStructure();
        renderInspector();

        const panel = document.createElement('div');
        panel.className = 'fs-stage-inline-panel';
        panel.setAttribute('data-fs-stage-inline-panel', nodeId);

        const closeBtnHtml = '<button type="button" class="fs-node-btn danger" data-fs-stage-inline-close>✕</button>';
        if (type === 'heading') {
            panel.innerHTML = `
                <div class="fs-stage-inline-head">
                    <div class="fs-stage-inline-title">Heading</div>
                    ${closeBtnHtml}
                </div>
                <input type="text" class="fs-stage-inline-input" data-fs-stage-heading-input value="${esc(String(node?.data?.text || ''))}">
            `;
        } else if (type === 'cta') {
            panel.innerHTML = `
                <div class="fs-stage-inline-head">
                    <div class="fs-stage-inline-title">CTA</div>
                    ${closeBtnHtml}
                </div>
                <div class="fs-stage-inline-row">
                    <input type="text" class="fs-stage-inline-input" data-fs-stage-cta-label placeholder="Текст кнопки" value="${esc(String(node?.data?.label || ''))}">
                    <input type="text" class="fs-stage-inline-input" data-fs-stage-cta-url placeholder="/ru/blog или https://..." value="${esc(String(node?.data?.url || ''))}">
                    <label class="fs-inline"><input type="checkbox" data-fs-stage-cta-target ${node?.data?.target_blank ? 'checked' : ''}> <span>Новая вкладка</span></label>
                    <label class="fs-inline"><input type="checkbox" data-fs-stage-cta-nofollow ${node?.data?.nofollow ? 'checked' : ''}> <span>Nofollow</span></label>
                </div>
            `;
        } else if (type === 'faq') {
            const first = Array.isArray(node?.data?.items) ? node.data.items[0] : null;
            panel.innerHTML = `
                <div class="fs-stage-inline-head">
                    <div class="fs-stage-inline-title">FAQ Question</div>
                    ${closeBtnHtml}
                </div>
                <input type="text" class="fs-stage-inline-input" data-fs-stage-faq-question value="${esc(String(first?.question || ''))}" placeholder="Вопрос">
                <div class="fs-node-hint">Ответ редактируется в inspector (rich text).</div>
            `;
        } else {
            panel.innerHTML = `
                <div class="fs-stage-inline-head">
                    <div class="fs-stage-inline-title">Rich Text</div>
                    ${closeBtnHtml}
                </div>
                <div class="fs-stage-inline-toolbar" data-fs-stage-rich-toolbar>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-cmd="bold"><strong>B</strong></button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-cmd="italic"><em>I</em></button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-cmd="underline"><u>U</u></button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-block="P">P</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-block="H2">H2</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-block="H3">H3</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-link>Link</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-cmd="unlink">Unlink</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-media="image">Img</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-media="gallery">Gallery</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-rich-snippet="cta">CTA</button>
                    <button type="button" class="fs-node-btn" data-fs-stage-open-inspector>Inspector</button>
                </div>
                <div class="fs-stage-inline-rich" contenteditable="true" data-fs-stage-rich-editor>${String(node?.data?.html || '<p></p>')}</div>
                <div class="fs-node-hint">Late commit: синхронизация в форму при blur / Save / Close / смене локали.</div>
            `;
        }

        refs.stageOverlay.appendChild(panel);
        positionStageInlinePanel(panel, nodeId);
        recomputeStageOverlayRects();

        const focusTarget = panel.querySelector('[data-fs-stage-rich-editor], [data-fs-stage-heading-input], [data-fs-stage-cta-label], [data-fs-stage-faq-question]');
        if (focusTarget) {
            window.requestAnimationFrame(() => focusTarget.focus());
        }
    };

    const setCanvasRichEditing = (nodeId = null) => {
        state.canvasRichEditingNodeId = nodeId ? String(nodeId) : null;
        renderCanvas();
        if (state.canvasRichEditingNodeId) {
            window.requestAnimationFrame(() => {
                const editor = refs.canvas?.querySelector(`[data-fs-rich-inline-editor="${state.canvasRichEditingNodeId}"]`);
                editor?.focus();
            });
        }
    };

    const setRichMode = (mode, root = refs.inspector) => {
        const normalized = ['visual', 'html'].includes(String(mode)) ? String(mode) : 'visual';
        state.richMode = normalized;
        root?.querySelectorAll('[data-fs-rich-mode]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-fs-rich-mode') === normalized);
        });
        root?.querySelectorAll('[data-fs-rich-mode-panel]').forEach((panel) => {
            panel.classList.toggle('active', panel.getAttribute('data-fs-rich-mode-panel') === normalized);
        });
    };

    const initializeRichEditorUi = (root = refs.inspector, node = null) => {
        const shell = root?.querySelector('[data-fs-rich-shell]');
        if (!shell) return;
        const visual = shell.querySelector('[data-fs-rich-editor]');
        const source = shell.querySelector('[data-fs-rich-html-source]');
        const status = shell.querySelector('[data-fs-rich-status]');
        const html = String(node?.data?.html || source?.value || '');
        if (visual && visual.innerHTML !== html) visual.innerHTML = html;
        if (source && source.value !== html) source.value = html;
        if (status) status.textContent = 'Визуальный редактор синхронизирован';
        setRichMode(state.richMode, shell);
    };

    const setLeftTab = (tab) => {
        state.leftTab = ['elements', 'presets', 'structure'].includes(String(tab)) ? String(tab) : 'elements';
        refs.leftPanels.forEach((panel) => panel.classList.toggle('active', panel.getAttribute('data-fs-left-panel') === state.leftTab));
        refs.leftTabs?.querySelectorAll('[data-fs-left-tab]').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-fs-left-tab') === state.leftTab));
        bridge.uiWrite?.(KEYS.leftTab, state.leftTab);
    };

    const setRightTab = (tab) => {
        state.rightTab = ['content', 'layout', 'style', 'seo', 'publish'].includes(String(tab)) ? String(tab) : 'content';
        refs.rightPanels.forEach((panel) => panel.classList.toggle('active', panel.getAttribute('data-fs-right-panel') === state.rightTab));
        refs.rightTabs?.querySelectorAll('[data-fs-right-tab]').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-fs-right-tab') === state.rightTab));
        bridge.uiWrite?.(KEYS.rightTab, state.rightTab);
    };

    const setDevice = (device) => {
        state.device = ['desktop', 'tablet', 'mobile'].includes(String(device)) ? String(device) : 'desktop';
        refs.deviceToggle?.querySelectorAll('[data-fs-device]').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-fs-device') === state.device));
        refs.frame?.classList.remove('fs-device-desktop', 'fs-device-tablet', 'fs-device-mobile');
        refs.frame?.classList.add(`fs-device-${state.device}`);
        bridge.uiWrite?.(KEYS.device, state.device);
        scheduleStageRender(120, 'device');
    };

    const renderElements = () => {
        const items = [
            { type: 'section', title: 'Секция', subtitle: 'Контейнер секции' },
            { type: 'columns-2', title: '2 колонки', subtitle: 'Колонки 7/5' },
            { type: 'columns-3', title: '3 колонки', subtitle: 'Равномерная сетка' },
            ...leafTypes.map((type) => ({ type, title: bridge.typeLabels?.[type] || type, subtitle: 'Блок' })),
        ];
        refs.elements.innerHTML = items.map((item) => `
            <button type="button" class="fs-element-btn" data-fs-element="${esc(item.type)}" draggable="true">
                ${esc(item.title)}
                <small>${esc(item.subtitle)}</small>
            </button>
        `).join('');
    };

    const sectionPresetFactories = {
        hero() {
            const sec = makeSection('Hero');
            const cols = makeColumns(2);
            cols.data.columns[0].children.push(makeLeaf('heading'));
            cols.data.columns[0].children.push(makeLeaf('rich_text'));
            cols.data.columns[0].children.push(makeLeaf('cta'));
            cols.data.columns[1].children.push(makeLeaf('image'));
            sec.children.push(cols);
            return [sec];
        },
        features() {
            const sec = makeSection('Преимущества');
            sec.children.push(makeLeaf('heading'));
            sec.children.push(makeLeaf('rich_text'));
            return [sec];
        },
        pricing() {
            const sec = makeSection('Тарифы');
            sec.children.push(makeLeaf('heading'));
            sec.children.push(makeLeaf('rich_text'));
            sec.children.push(makeLeaf('cta'));
            return [sec];
        },
        faq() {
            const sec = makeSection('FAQ');
            sec.children.push(makeLeaf('heading'));
            sec.children.push(makeLeaf('faq'));
            return [sec];
        },
        blog() {
            const sec = makeSection('Блог');
            sec.children.push(makeLeaf('heading'));
            sec.children.push(makeLeaf('rich_text'));
            sec.children.push(makeLeaf('post_listing'));
            return [sec];
        },
    };

    const renderPresets = () => {
        const presetDescriptions = {
            hero: 'Hero секция с колонками, текстом и CTA',
            features: 'Секция преимуществ',
            pricing: 'Тарифные карточки / CTA',
            faq: 'FAQ секция',
            blog: 'Секция блога со списком постов',
        };
        refs.presets.innerHTML = Object.keys(sectionPresetFactories).map((key) => `
            <div class="fs-preset-item">
                <strong>${esc(key)}</strong>
                <p>${esc(presetDescriptions[key] || 'Пресет')}</p>
                <button type="button" class="btn btn-small" data-fs-preset="${esc(key)}">Вставить</button>
            </div>
        `).join('');
    };

    const renderStructure = () => {
        let html = '';
        walkNodes(state.nodes, (node, nodePath) => {
            const depth = Math.max(0, (String(nodePath).match(/\./g) || []).length - 1);
            const pad = depth * 12;
            const selected = String(node?.id || '') === String(state.selectedNodeId || '');
            const label = String(node?.type || 'node');
            const summary = node?.type === 'section'
                ? String(node?.data?.label || 'Секция')
                : node?.type === 'columns'
                    ? `${Array.isArray(node?.data?.columns) ? node.data.columns.length : 0} колонок`
                    : (bridge.typeLabels?.[label] || label);
            html += `
                <button type="button" class="fs-structure-row ${selected ? 'selected' : ''}" data-fs-select-node="${esc(String(node?.id || ''))}" style="padding-left:${8 + pad}px;">
                    <span class="tag-mini">${esc(label)}</span>
                    <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(summary)}</span>
                </button>
            `;
        });
        refs.structure.innerHTML = html || '<div class="fs-empty">Структура пуста. Добавьте секцию.</div>';
    };

    const listButtonsMarkup = (kind) => {
        if (kind === 'root') {
            return '<div class="fs-inline"><button type="button" class="btn btn-small" data-fs-quick-add="section">+ Section</button></div>';
        }
        if (kind === 'section-children') {
            return '<div class="fs-inline"><button type="button" class="btn btn-small" data-fs-quick-add="heading">+ Heading</button><button type="button" class="btn btn-small" data-fs-quick-add="rich_text">+ Text</button><button type="button" class="btn btn-small" data-fs-quick-add="columns-2">+ Columns</button><button type="button" class="btn btn-small" data-fs-quick-add="custom_code_embed">+ Embed</button></div>';
        }
        return '<div class="fs-inline"><button type="button" class="btn btn-small" data-fs-quick-add="heading">+ Heading</button><button type="button" class="btn btn-small" data-fs-quick-add="rich_text">+ Text</button><button type="button" class="btn btn-small" data-fs-quick-add="image">+ Image</button><button type="button" class="btn btn-small" data-fs-quick-add="cta">+ CTA</button><button type="button" class="btn btn-small" data-fs-quick-add="custom_code_embed">+ Embed</button></div>';
    };

    const nodeSummary = (node) => {
        if (!node || typeof node !== 'object') return '';
        if (node.type === 'section') return String(node?.data?.label || 'Секция');
        if (node.type === 'columns') return `${Array.isArray(node?.data?.columns) ? node.data.columns.length : 0} колонок`;
        if (node.type === 'heading') return String(node?.data?.text || 'Заголовок');
        if (node.type === 'cta') return `${String(node?.data?.label || 'CTA')} → ${String(node?.data?.url || '#')}`;
        return bridge.typeLabels?.[node.type] || String(node.type || 'block');
    };

    const dropZoneMarkup = (listPath, index) => `<div class="fs-dropzone" data-drop-zone data-drop-list-path="${esc(listPath)}" data-drop-index="${index}">Перетащите сюда</div>`;

    const renderNodeList = (list, listPath) => {
        const kind = classifyListPath(listPath);
        if (!Array.isArray(list) || list.length === 0) {
            return `<div class="fs-empty" data-empty-list-path="${esc(listPath)}">Пусто. ${listButtonsMarkup(kind)}</div>`;
        }
        let html = `<div class="fs-list" data-node-list-path="${esc(listPath)}">`;
        list.forEach((node, index) => {
            const nodePath = `${listPath}.${index}`;
            html += dropZoneMarkup(listPath, index);
            html += renderNodeCard(node, nodePath);
        });
        html += dropZoneMarkup(listPath, list.length);
        html += '</div>';
        return html;
    };

    const renderNodeCard = (node, nodePath) => {
        if (!node || typeof node !== 'object') return '';
        const id = String(node.id || '');
        const type = String(node.type || '');
        const selected = id && id === state.selectedNodeId;
        const summary = nodeSummary(node);
        const badge = (bridge.typeLabels?.[type] || layoutLabels[type] || type);
        let body = '';

        if (type === 'section') {
            const children = Array.isArray(node.children) ? node.children : [];
            body = `
                <div class="fs-section-shell">
                    <div class="fs-inline">
                        <span class="fs-pill">container: ${esc(String(node?.data?.container || 'boxed'))}</span>
                        <span class="fs-pill">padding: ${esc(String(node?.data?.padding_y || 'md'))}</span>
                        <span class="fs-pill">bg: ${esc(String(node?.data?.background || 'none'))}</span>
                    </div>
                    ${renderNodeList(children, `${nodePath}.children`)}
                </div>
            `;
        } else if (type === 'columns') {
            const cols = Array.isArray(node?.data?.columns) ? node.data.columns : [];
            body = `
                <div class="fs-inline">
                    <span class="fs-pill">gap: ${esc(String(node?.data?.gap || 'md'))}</span>
                    <span class="fs-pill">align: ${esc(String(node?.data?.align_y || 'stretch'))}</span>
                </div>
                <div class="fs-columns-grid" data-fs-columns-grid-path="${esc(nodePath)}">
                    ${cols.map((col, colIndex) => {
                        const span = Math.min(12, Math.max(1, Number(col?.span || 6)));
                        return `
                            <div
                                class="fs-column"
                                style="grid-column: span ${span};"
                                data-fs-column-card
                                data-fs-columns-node-path="${esc(nodePath)}"
                                data-fs-column-index="${colIndex}"
                            >
                                <div class="fs-column-head">
                                    <div class="fs-column-head-left">
                                        <span class="fs-column-drag" draggable="true" title="Перетащить колонку">⋮⋮</span>
                                        <span>Колонка ${colIndex + 1}</span>
                                    </div>
                                    <div class="fs-column-head-right">
                                        <span>span ${span}</span>
                                        ${colIndex < cols.length - 1 ? `<button type="button" class="fs-col-resize-handle" data-fs-col-resize-handle data-fs-columns-node-path="${esc(nodePath)}" data-fs-col-left-index="${colIndex}" title="Изменить ширину колонок"></button>` : ''}
                                    </div>
                                </div>
                                ${renderNodeList(Array.isArray(col?.children) ? col.children : [], `${nodePath}.data.columns.${colIndex}.children`)}
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        } else {
            const previewHtml = bridge.previewRenderNode ? bridge.previewRenderNode(node) : '';
            const isRich = type === 'rich_text';
            const isInlineEditing = isRich && String(state.canvasRichEditingNodeId || '') === id;
            const rawRichHtml = String(node?.data?.html || '');
            body = `
                <div class="fs-node-preview ${isRich ? 'is-rich' : ''} ${isInlineEditing ? 'is-rich-editing' : ''}" ${isRich ? `data-fs-rich-preview-node="${esc(id)}"` : ''}>
                    ${isInlineEditing ? `
                        <div class="fs-rich-canvas-toolbar" data-fs-canvas-rich-toolbar data-fs-rich-preview-node="${esc(id)}">
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-cmd="bold" title="Жирный"><strong>B</strong></button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-cmd="italic" title="Курсив"><em>I</em></button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-cmd="underline" title="Подчёркивание"><u>U</u></button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-block="P" title="Абзац">P</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-block="H2" title="H2">H2</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-block="H3" title="H3">H3</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-link title="Ссылка">Link</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-cmd="unlink" title="Убрать ссылку">Unlink</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-media="image" title="Изображение">Img</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-media="gallery" title="Галерея">Gallery</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-snippet="cta" title="CTA">CTA</button>
                            <button type="button" class="fs-node-btn" data-fs-canvas-rich-open-inspector title="Открыть тот же блок в inspector">Inspector</button>
                            <button type="button" class="fs-node-btn danger" data-fs-canvas-rich-close title="Закрыть inline-режим">✕</button>
                        </div>
                        <div class="fs-rich-inline-editable" contenteditable="true" data-fs-rich-inline-editor="${esc(id)}">${rawRichHtml || '<p></p>'}</div>
                        <div class="fs-node-hint" style="margin-top:8px;">Inline-редактирование в canvas. Изменения синхронизируются с формой автоматически.</div>
                    ` : `
                        ${previewHtml || `<div class="fs-node-hint">${esc(summary)}</div>`}
                        <div class="fs-node-hint" style="margin-top:8px;">Двойной клик для быстрого редактирования текста в canvas</div>
                    `}
                </div>
            `;
        }

        return `
            <article class="fs-node ${selected ? 'selected' : ''}" data-layout-node-id="${esc(id)}" data-node-path="${esc(nodePath)}" draggable="true">
                <div class="fs-node-head">
                    <button type="button" class="fs-node-title" data-fs-select-node="${esc(id)}">
                        <span class="drag">⋮⋮</span>
                        <span>${esc(badge)}</span>
                        <small>${esc(summary)}</small>
                    </button>
                    <div class="fs-node-actions">
                        <button type="button" class="fs-node-btn" data-node-action="up" title="Выше">↑</button>
                        <button type="button" class="fs-node-btn" data-node-action="down" title="Ниже">↓</button>
                        <button type="button" class="fs-node-btn" data-node-action="duplicate" title="Дублировать">⧉</button>
                        <button type="button" class="fs-node-btn danger" data-node-action="delete" title="Удалить">✕</button>
                    </div>
                </div>
                <div class="fs-node-body">${body}</div>
            </article>
        `;
    };

    const renderCanvas = () => {
        state.nodes = ensureStructuredNodes(state.nodes);
        refs.canvas.innerHTML = renderNodeList(state.nodes, 'root');
    };

    const buildInspectorField = (label, name, value, opts = {}) => {
        const type = opts.type || 'text';
        if (type === 'checkbox') {
            return `
                <label class="fs-inline" style="font-size:12px;font-weight:700;color:#334155;">
                    <input type="checkbox" data-fs-field="${esc(name)}" ${value ? 'checked' : ''}>
                    <span>${esc(label)}</span>
                </label>
            `;
        }
        if (type === 'select') {
            return `
                <div class="fs-field">
                    <label>${esc(label)}</label>
                    <select data-fs-field="${esc(name)}">
                        ${(opts.options || []).map((opt) => `<option value="${esc(opt.value)}" ${String(opt.value) === String(value) ? 'selected' : ''}>${esc(opt.label)}</option>`).join('')}
                    </select>
                </div>
            `;
        }
        if (type === 'textarea') {
            return `
                <div class="fs-field">
                    <label>${esc(label)}</label>
                    <textarea rows="${Number(opts.rows || 5)}" data-fs-field="${esc(name)}">${esc(String(value || ''))}</textarea>
                    ${opts.hint ? `<small>${esc(opts.hint)}</small>` : ''}
                </div>
            `;
        }
        if (type === 'number') {
            return `
                <div class="fs-field">
                    <label>${esc(label)}</label>
                    <input type="number" min="${opts.min ?? 0}" max="${opts.max ?? 999}" step="${opts.step ?? 1}" value="${esc(String(value ?? ''))}" data-fs-field="${esc(name)}">
                </div>
            `;
        }
        return `
            <div class="fs-field">
                <label>${esc(label)}</label>
                <input type="text" value="${esc(String(value || ''))}" data-fs-field="${esc(name)}" ${opts.placeholder ? `placeholder="${esc(opts.placeholder)}"` : ''}>
                ${opts.hint ? `<small>${esc(opts.hint)}</small>` : ''}
            </div>
        `;
    };

    const moduleWidgetInspectorMarkup = (data) => {
        const moduleKey = String(data?.module || '').trim();
        const widgetKey = String(data?.widget || '').trim();
        const moduleOptions = typeof bridge.moduleOptions === 'function' ? bridge.moduleOptions() : [];
        const widgetOptions = typeof bridge.widgetsForModule === 'function'
            ? bridge.widgetsForModule(moduleKey).map((item) => ({
                value: String(item?.widget || ''),
                label: String(item?.label || item?.widget || ''),
            }))
            : [];
        const definition = (typeof bridge.findWidgetDefinition === 'function' ? bridge.findWidgetDefinition(moduleKey, widgetKey) : null)
            || (typeof bridge.widgetsForModule === 'function' ? bridge.widgetsForModule(moduleKey)[0] : null)
            || (Array.isArray(bridge.moduleWidgetCatalog) ? bridge.moduleWidgetCatalog[0] : null);
        const config = (data?.config && typeof data.config === 'object') ? data.config : {};
        const dynamicFields = (Array.isArray(definition?.config_fields) ? definition.config_fields : []).map((field) => {
            const name = String(field.name || '').trim();
            if (!name) return '';
            const type = String(field.type || 'text');
            const currentValue = Object.prototype.hasOwnProperty.call(config, name)
                ? config[name]
                : (Object.prototype.hasOwnProperty.call(field, 'default') ? field.default : (type === 'checkbox' ? false : ''));
            if (type === 'select') {
                return buildInspectorField(String(field.label || name), `data.config.${name}`, currentValue, {
                    type: 'select',
                    options: Array.isArray(field.options) ? field.options : [],
                    hint: field.hint || '',
                });
            }
            if (type === 'checkbox') {
                return buildInspectorField(String(field.label || name), `data.config.${name}`, !!currentValue, { type: 'checkbox' });
            }
            return buildInspectorField(String(field.label || name), `data.config.${name}`, currentValue ?? '', {
                type: type === 'textarea' ? 'textarea' : type,
                rows: field.rows || 4,
                placeholder: field.placeholder || '',
                hint: field.hint || '',
            });
        }).join('');

        return `
            <div class="fs-node-hint">${esc(typeof bridge.moduleWidgetSummary === 'function' ? bridge.moduleWidgetSummary(data) : 'Виджет модуля')}</div>
            <div class="fs-grid-2">
                ${buildInspectorField('Модуль', 'data._module', moduleKey, { type: 'select', options: moduleOptions })}
                ${buildInspectorField('Виджет', 'data._widget', widgetKey, { type: 'select', options: widgetOptions })}
            </div>
            ${dynamicFields || '<div class="fs-node-hint">Для этого виджета нет дополнительных настроек.</div>'}
            ${buildInspectorField('Конфиг JSON (advanced)', 'data._config_json', JSON.stringify(config, null, 2), {
                type: 'textarea',
                rows: 6,
                hint: 'Продвинутый режим. Невалидный JSON не применяется.',
            })}
        `;
    };

    const tableEditorMarkup = (rowsInput) => {
        const rows = normalizeTableRows(rowsInput, { minRows: 1, minCols: 1, maxCols: 12 });
        const rowCount = rows.length;
        const colCount = rows[0]?.length || 1;
        return `
            <div class="fs-table-editor" data-fs-table-editor>
                <div class="fs-inline fs-table-toolbar">
                    <div class="fs-inline">
                        <button type="button" class="btn btn-small" data-fs-table-action="add-row">+ Строка</button>
                        <button type="button" class="btn btn-small" data-fs-table-action="add-col" ${colCount >= 12 ? 'disabled' : ''}>+ Столбец</button>
                        <button type="button" class="btn btn-small" data-fs-table-action="normalize">Выровнять</button>
                    </div>
                    <div class="fs-table-meta">${rowCount} × ${colCount}</div>
                </div>
                <div class="fs-table-grid-wrap">
                    <table class="fs-table-grid">
                        <thead>
                            <tr>
                                ${Array.from({ length: colCount }).map((_, colIndex) => `
                                    <th>
                                        <div class="fs-table-col-head">
                                            <span>Колонка ${colIndex + 1}</span>
                                            <button type="button" class="fs-node-btn danger" data-fs-table-col-remove="${colIndex}" ${colCount <= 1 ? 'disabled' : ''} title="Удалить столбец">✕</button>
                                        </div>
                                    </th>
                                `).join('')}
                                <th class="fs-table-row-tools"><span style="font-size:11px;color:#667085;">Rows</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map((row, rowIndex) => `
                                <tr>
                                    ${row.map((cell, colIndex) => `
                                        <td>
                                            <input
                                                type="text"
                                                value="${esc(String(cell || ''))}"
                                                data-fs-table-cell
                                                data-fs-table-managed
                                                data-fs-table-row="${rowIndex}"
                                                data-fs-table-col="${colIndex}"
                                                placeholder="Значение">
                                        </td>
                                    `).join('')}
                                    <td class="fs-table-row-tools">
                                        <button type="button" class="fs-node-btn danger" data-fs-table-row-remove="${rowIndex}" ${rowCount <= 1 ? 'disabled' : ''} title="Удалить строку">✕</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="fs-node-hint">Редактируйте значения по ячейкам. Синхронизация с формой выполняется при blur/Save.</div>
            </div>
        `;
    };

    const leafInspectorMarkup = (node) => {
        const type = String(node?.type || '');
        const data = (node?.data && typeof node.data === 'object') ? node.data : {};
        if (type === 'heading') {
            return `
                <div class="fs-grid-2">
                    ${buildInspectorField('Уровень', 'data.level', Number(data.level || 2), { type: 'select', options: [1,2,3,4,5,6].map((v) => ({ value: v, label: `H${v}` })) })}
                    ${buildInspectorField('Текст', 'data.text', data.text || '', { placeholder: 'Текст заголовка' })}
                </div>
            `;
        }
        if (type === 'rich_text') {
            return `
                <div class="fs-rich-shell" data-fs-rich-shell>
                    <div class="segmented" data-fs-rich-mode-toggle>
                        <button type="button" class="active" data-fs-rich-mode="visual">Визуально</button>
                        <button type="button" data-fs-rich-mode="html">HTML</button>
                    </div>
                    <div class="fs-rich-toolbar">
                        <button type="button" class="fs-node-btn" data-fs-rich-cmd="bold" title="Жирный"><strong>B</strong></button>
                        <button type="button" class="fs-node-btn" data-fs-rich-cmd="italic" title="Курсив"><em>I</em></button>
                        <button type="button" class="fs-node-btn" data-fs-rich-cmd="underline" title="Подчёркивание"><u>U</u></button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-block="p">P</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-block="h2">H2</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-block="h3">H3</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-cmd="insertUnorderedList" title="Список">• List</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-cmd="insertOrderedList" title="Нумерованный">1. List</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-link title="Ссылка">Link</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-cmd="unlink" title="Убрать ссылку">Unlink</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-media="image">Image</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-media="gallery">Gallery</button>
                        <button type="button" class="fs-node-btn wide" data-fs-rich-snippet="cta">CTA</button>
                    </div>
                    <div class="fs-rich-panels">
                        <div data-fs-rich-mode-panel="visual" class="active">
                            <div class="fs-rich-editable" contenteditable="true" data-fs-rich-editor></div>
                        </div>
                        <div data-fs-rich-mode-panel="html">
                            ${buildInspectorField('HTML', 'data.html', data.html || '', { type: 'textarea', rows: 10, hint: 'HTML будет очищен при сохранении' }).replace('data-fs-field=\"data.html\"', 'data-fs-field=\"data.html\" data-fs-rich-html-source data-fs-rich-managed')}
                        </div>
                    </div>
                    <div class="fs-rich-status" data-fs-rich-status>Визуальный редактор синхронизирован</div>
                </div>
            `;
        }
        if (type === 'image') {
            return `
                <div class="fs-inline"><button type="button" class="btn btn-small" data-fs-media-pick="image">Выбрать из Assets</button></div>
                ${buildInspectorField('URL', 'data.src', data.src || '', { placeholder: 'https://...' })}
                <div class="fs-grid-2">
                    ${buildInspectorField('Alt', 'data.alt', data.alt || '')}
                    ${buildInspectorField('Подпись', 'data.caption', data.caption || '')}
                </div>
            `;
        }
        if (type === 'gallery') {
            return `
                <div class="fs-inline"><button type="button" class="btn btn-small" data-fs-media-pick="gallery">Добавить из Assets</button></div>
                ${buildInspectorField('Галерея', 'data._gallery_lines', bridge.toGalleryLines ? bridge.toGalleryLines(data.items) : '', { type: 'textarea', rows: 6, hint: 'Строка: URL | Alt' })}
            `;
        }
        if (type === 'video_embed') {
            return buildInspectorField('URL embed', 'data.url', data.url || '', { placeholder: 'https://...' });
        }
        if (type === 'list') {
            return `
                ${buildInspectorField('Нумерованный список', 'data.ordered', !!data.ordered, { type: 'checkbox' })}
                ${buildInspectorField('Пункты', 'data._list_lines', bridge.toListLines ? bridge.toListLines(data.items) : '', { type: 'textarea', rows: 6 })}
            `;
        }
        if (type === 'divider') {
            return '<div class="fs-node-hint">У блока-разделителя нет настроек.</div>';
        }
        if (type === 'cta') {
            return `
                <div class="fs-grid-2">
                    ${buildInspectorField('Текст кнопки', 'data.label', data.label || '')}
                    ${buildInspectorField('URL', 'data.url', data.url || '', { placeholder: '/ru/blog или https://...' })}
                </div>
                <div class="fs-grid-2">
                    ${buildInspectorField('Новый таб', 'data.target_blank', !!data.target_blank, { type: 'checkbox' })}
                    ${buildInspectorField('Nofollow', 'data.nofollow', !!data.nofollow, { type: 'checkbox' })}
                </div>
                ${buildInspectorField('Стиль', 'data.style', data.style || 'primary', { type: 'select', options: [{value:'primary',label:'Primary'},{value:'secondary',label:'Secondary'},{value:'ghost',label:'Ghost'}] })}
            `;
        }
        if (type === 'table') {
            return tableEditorMarkup(data.rows);
        }
        if (type === 'module_widget') {
            return moduleWidgetInspectorMarkup(data);
        }
        if (type === 'custom_code_embed') {
            return `
                ${buildInspectorField('Название embed (опционально)', 'data.label', data.label || '', { placeholder: 'Форма / Календарь / Карта' })}
                ${buildInspectorField('Embed HTML', 'data.html', data.html || '', {
                    type: 'textarea',
                    rows: 9,
                    hint: 'Вставьте iframe/script внешнего сервиса. Код очищается restricted policy; script src разрешён только для доменов из CMS_SAFE_EMBED_DOMAINS.',
                })}
            `;
        }
        if (type === 'html_embed_restricted') {
            return buildInspectorField('Embed HTML', 'data.html', data.html || '', { type: 'textarea', rows: 8 });
        }
        if (type === 'post_listing') {
            return `
                <div class="fs-grid-2">
                    ${buildInspectorField('Slug категории', 'data.category_slug', data.category_slug || '', { placeholder: 'optional' })}
                    ${buildInspectorField('Лимит', 'data.limit', Number(data.limit || 6), { type: 'number', min: 1, max: 100 })}
                </div>
            `;
        }
        if (type === 'faq') {
            return buildInspectorField('FAQ', 'data._faq_text', bridge.toFaqText ? bridge.toFaqText(data.items) : '', { type: 'textarea', rows: 9, hint: 'Q: ... / A: <p>...</p>' });
        }
        return '<div class="fs-node-hint">Для этого блока используйте визуальный конструктор или Advanced JSON.</div>';
    };

    const renderInspector = () => {
        const ref = getSelectedRef();
        if (!ref) {
            refs.inspector.innerHTML = '<div class="fs-node-hint">Выберите элемент на сцене или в структуре.</div>';
            refs.layoutPanel.innerHTML = '<div class="fs-node-hint">Выберите секцию или группу колонок.</div>';
            return;
        }

        const node = ref.node;
        const type = String(node?.type || '');
        const data = (node?.data && typeof node.data === 'object') ? node.data : {};

        refs.inspector.innerHTML = `
            <h4>${esc(bridge.typeLabels?.[type] || layoutLabels[type] || type)}</h4>
            <div class="fs-node-hint">${esc(nodeSummary(node))}</div>
            ${isLeafNode(node) ? leafInspectorMarkup(node) : '<div class="fs-node-hint">Для структурных узлов используйте вкладку «Схема».</div>'}
        `;
        if (type === 'rich_text') {
            initializeRichEditorUi(refs.inspector, node);
        }

        if (type === 'section') {
            refs.layoutPanel.innerHTML = `
                <h4>Секция · схема</h4>
                ${buildInspectorField('Название', 'data.label', data.label || '')}
                <div class="fs-grid-2">
                    ${buildInspectorField('Контейнер', 'data.container', data.container || 'boxed', { type: 'select', options: [{value:'boxed',label:'В рамке'},{value:'wide',label:'Широкий'},{value:'full',label:'Во всю ширину'}] })}
                    ${buildInspectorField('Вертикальные отступы', 'data.padding_y', data.padding_y || 'md', { type: 'select', options: [{value:'none',label:'Нет'},{value:'sm',label:'S'},{value:'md',label:'M'},{value:'lg',label:'L'},{value:'xl',label:'XL'}] })}
                </div>
                <div class="fs-grid-2">
                    ${buildInspectorField('Фон', 'data.background', data.background || 'none', { type: 'select', options: [{value:'none',label:'Нет'},{value:'surface',label:'Поверхность'},{value:'brand-soft',label:'Акцентный мягкий'},{value:'custom',label:'Свой'}] })}
                    ${buildInspectorField('Цвет фона', 'data.background_color', data.background_color || '', { placeholder: '#FFFFFF' })}
                </div>
                ${buildInspectorField('Якорь ID', 'data.anchor_id', data.anchor_id || '', { placeholder: 'hero' })}
                <div class="fs-inline">
                    <button type="button" class="btn btn-small" data-fs-add-child="section:heading">+ Заголовок</button>
                    <button type="button" class="btn btn-small" data-fs-add-child="section:rich_text">+ Текст</button>
                    <button type="button" class="btn btn-small" data-fs-add-child="section:columns-2">+ Колонки</button>
                </div>
            `;
        } else if (type === 'columns') {
            const cols = Array.isArray(data.columns) ? data.columns : [];
            refs.layoutPanel.innerHTML = `
                <h4>Колонки · схема</h4>
                <div class="fs-grid-2">
                    ${buildInspectorField('Интервал', 'data.gap', data.gap || 'md', { type: 'select', options: [{value:'sm',label:'S'},{value:'md',label:'M'},{value:'lg',label:'L'}] })}
                    ${buildInspectorField('Выравнивание по вертикали', 'data.align_y', data.align_y || 'stretch', { type: 'select', options: [{value:'start',label:'Вверх'},{value:'center',label:'Центр'},{value:'end',label:'Вниз'},{value:'stretch',label:'Растянуть'}] })}
                </div>
                <div class="fs-columns-list">
                    ${cols.map((col, idx) => `
                        <div class="fs-columns-item" data-fs-column-index="${idx}">
                            <div class="fs-columns-item-head">
                                <span>Колонка ${idx + 1}</span>
                                <div class="fs-inline">
                                    <button type="button" class="fs-node-btn" data-fs-column-action="up" ${idx === 0 ? 'disabled' : ''}>↑</button>
                                    <button type="button" class="fs-node-btn" data-fs-column-action="down" ${idx === cols.length - 1 ? 'disabled' : ''}>↓</button>
                                    <button type="button" class="fs-node-btn danger" data-fs-column-action="delete" ${cols.length <= 2 ? 'disabled' : ''}>✕</button>
                                </div>
                            </div>
                            <div class="fs-field">
                                <label>Span (1..12)</label>
                                <input type="number" min="1" max="12" value="${esc(String(col?.span || 6))}" data-fs-column-span="${idx}">
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="fs-inline">
                    <button type="button" class="btn btn-small" data-fs-columns-add ${cols.length >= 4 ? 'disabled' : ''}>Добавить колонку</button>
                    <button type="button" class="btn btn-small" data-fs-columns-rebalance>Выровнять (сумма=12)</button>
                </div>
            `;
        } else {
            refs.layoutPanel.innerHTML = '<div class="fs-node-hint">Layout-настройки доступны для section и columns.</div>';
        }
    };

    const renderSeoPanel = () => {
        const locale = getCurrentLocale();
        const f = getLocaleFields(locale);
        if (!f.metaTitle || !f.canonical || !f.metaDescription) {
            refs.seoPanel.innerHTML = '<div class="fs-node-hint">SEO поля текущей локали недоступны.</div>';
            return;
        }
        refs.seoPanel.innerHTML = `
            <h4>SEO · ${esc(locale.toUpperCase())}</h4>
            ${buildInspectorField('Meta title', 'seo.meta_title', f.metaTitle.value || '')}
            ${buildInspectorField('Canonical URL', 'seo.canonical_url', f.canonical.value || '', { placeholder: 'https://...' })}
            ${buildInspectorField('Meta description', 'seo.meta_description', f.metaDescription.value || '', { type: 'textarea', rows: 4 })}
            ${buildInspectorField('Custom <head> code', 'seo.custom_head_html', f.customHead?.value || '', { type: 'textarea', rows: 6, hint: 'Advanced only' })}
            <div class="fs-inline"><button type="button" class="btn btn-small" data-fs-refresh-seo-preview>Обновить SEO preview</button></div>
            <div data-fs-seo-preview-slot></div>
        `;
        const sourcePreview = document.querySelector(`[data-page-seo-preview="${locale}"]`);
        const slot = refs.seoPanel.querySelector('[data-fs-seo-preview-slot]');
        if (slot && sourcePreview) {
            slot.innerHTML = sourcePreview.outerHTML;
        }
    };

    const renderPublishPanel = () => {
        const statusField = getPageStatusField();
        const typeField = getPageTypeField();
        refs.publishPanel.innerHTML = `
            <h4>Публикация страницы</h4>
            ${statusField ? buildInspectorField('Статус', 'publish.status', statusField.value || 'draft', { type: 'select', options: Array.from(statusField.options).map((o) => ({ value: o.value, label: o.textContent || o.value })) }) : ''}
            ${typeField ? buildInspectorField('Тип страницы', 'publish.page_type', typeField.value || 'landing') : ''}
            <div class="fs-inline">
                <button type="button" class="btn" data-page-fullscreen-save-inline>Сохранить</button>
                <button type="button" class="btn btn-primary" data-page-fullscreen-save-publish-inline>Сохранить и опубликовать</button>
            </div>
            <div class="fs-node-hint">Preview tokens, schedule и delete остаются в обычном режиме редактирования.</div>
        `;
    };

    const renderAll = () => {
        state.stageSelectedMarkerId = state.selectedNodeId ? String(state.selectedNodeId) : null;
        refreshTopMeta();
        renderCanvas();
        renderStructure();
        renderInspector();
        renderSeoPanel();
        renderPublishPanel();
        setLeftTab(state.leftTab);
        setRightTab(state.rightTab);
        setDevice(state.device);
        setCenterMode(state.centerMode);
        if (state.centerMode === 'stage') {
            scheduleStageRender(120, 'render-all');
            window.requestAnimationFrame(recomputeStageOverlayRects);
        }
    };

    const loadLocaleNodes = (locale) => {
        const builder = bridge.getBuilder?.(locale);
        if (!builder) return ensureStructuredNodes([]);
        const nodes = builder.getNodes ? builder.getNodes() : [];
        return ensureStructuredNodes(Array.isArray(nodes) ? clone(nodes) : []);
    };

    const setLocale = (locale) => {
        flushCanvasRichLateCommit('Rich text синхронизирован перед сменой локали');
        flushStageRichLateCommit('Stage rich text синхронизирован перед сменой локали');
        flushTableInspectorLateCommit('Таблица синхронизирована перед сменой локали');
        closeStageInlineEditor({ commit: false });
        state.locale = String(locale || state.locale || bridge.getActiveLocale?.() || 'ru');
        state.nodes = loadLocaleNodes(state.locale);
        state.selectedNodeId = null;
        state.canvasRichEditingNodeId = null;
        state.canvasRichHasUncommittedChanges = false;
        if (state.canvasRichCommitTimer) {
            window.clearTimeout(state.canvasRichCommitTimer);
            state.canvasRichCommitTimer = null;
        }
        refs.localeTabs?.querySelectorAll('[data-page-fullscreen-locale]').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-page-fullscreen-locale') === state.locale));
        renderAll();
        scheduleStageRender(40, 'locale');
    };

    const insertIntoList = (listPath, nodeFactoryOrNode, index = null) => {
        const list = resolveListRef(state.nodes, listPath);
        if (!Array.isArray(list)) return;
        const node = typeof nodeFactoryOrNode === 'function' ? nodeFactoryOrNode() : clone(nodeFactoryOrNode);
        ensureNodeIds([node]);
        if (!canPlaceNodeInList(node, listPath)) {
            dialogs.alert('Этот тип элемента нельзя вставить в выбранную область.');
            return;
        }
        const at = index === null ? list.length : Math.max(0, Math.min(list.length, Number(index)));
        list.splice(at, 0, node);
        state.selectedNodeId = node.id;
        syncNodesToBuilder();
    };

    const createPaletteNode = (rawType) => {
        const type = String(rawType || '');
        if (type === 'section') return makeSection('Новая секция');
        if (type === 'columns-2') return makeColumns(2);
        if (type === 'columns-3') return makeColumns(3);
        return makeLeaf(type);
    };

    const firstCompatibleListPathForNode = (node) => {
        let found = null;
        if (canPlaceNodeInList(node, 'root')) return 'root';
        walkNodes(state.nodes, (candidate, nodePath) => {
            if (found) return;
            if (candidate?.type === 'section') {
                const p = `${nodePath}.children`;
                if (canPlaceNodeInList(node, p)) found = p;
                return;
            }
            if (candidate?.type === 'columns') {
                const cols = Array.isArray(candidate?.data?.columns) ? candidate.data.columns : [];
                for (let i = 0; i < cols.length; i += 1) {
                    const p = `${nodePath}.data.columns.${i}.children`;
                    if (canPlaceNodeInList(node, p)) {
                        found = p;
                        break;
                    }
                }
            }
        });
        return found;
    };

    const inferInsertListPathForNode = (node) => {
        const selectedRef = getSelectedRef();
        if (selectedRef) {
            const selectedNode = selectedRef.node;
            const selectedNodePath = selectedNode?.id ? findNodePathById(state.nodes, selectedNode.id) : null;

            if (isLeafNode(selectedNode)) {
                const parentPath = selectedRef.parentPath;
                if (canPlaceNodeInList(node, parentPath)) return parentPath;
            }

            if (selectedNode?.type === 'section' && selectedNodePath) {
                const p = `${selectedNodePath}.children`;
                if (canPlaceNodeInList(node, p)) return p;
            }

            if (selectedNode?.type === 'columns' && selectedNodePath) {
                const cols = Array.isArray(selectedNode?.data?.columns) ? selectedNode.data.columns : [];
                for (let i = 0; i < cols.length; i += 1) {
                    const p = `${selectedNodePath}.data.columns.${i}.children`;
                    if (canPlaceNodeInList(node, p)) return p;
                }
            }
        }

        return firstCompatibleListPathForNode(node);
    };

    const findColumnChildListPathByIds = (ownerNodeId, columnId) => {
        if (!ownerNodeId || !columnId) return null;
        const ownerPath = findNodePathById(state.nodes, ownerNodeId);
        if (!ownerPath) return null;
        const ownerRef = resolveNodeRef(state.nodes, ownerPath);
        if (!ownerRef || ownerRef.node?.type !== 'columns') return null;
        const cols = Array.isArray(ownerRef.node?.data?.columns) ? ownerRef.node.data.columns : [];
        const idx = cols.findIndex((col) => String(col?.id || '') === String(columnId));
        if (idx < 0) return null;
        return `${ownerPath}.data.columns.${idx}.children`;
    };

    const computeStageDropCandidateForPalette = (paletteType, rawTarget, dragEvent = null) => {
        const type = String(paletteType || '');
        if (!type) return null;
        const node = createPaletteNode(type);
        const stageTarget = resolveStageTarget(rawTarget);
        const defaultListPath = inferInsertListPathForNode(node);
        if (!stageTarget) {
            if (!defaultListPath) return null;
            return {
                listPath: defaultListPath,
                index: null,
                markerKind: state.stageSelectedMarkerId ? 'node' : null,
                nodeId: state.stageSelectedMarkerId || null,
                columnId: null,
                position: 'append',
                label: 'Вставка в выбранный контейнер',
            };
        }

        if (stageTarget.type === 'column') {
            const listPath = findColumnChildListPathByIds(stageTarget.ownerNodeId, stageTarget.columnId);
            if (listPath && canPlaceNodeInList(node, listPath)) {
                const list = resolveListRef(state.nodes, listPath) || [];
                return {
                    listPath,
                    index: Array.isArray(list) ? list.length : null,
                    markerKind: 'column',
                    nodeId: null,
                    columnId: stageTarget.columnId || null,
                    position: 'inside',
                    label: 'Вставка в колонку',
                };
            }
        }

        if (stageTarget.type === 'node') {
            const targetRef = getNodeRefById(stageTarget.nodeId);
            const targetNode = targetRef?.node || null;
            const targetNodePath = targetNode?.id ? findNodePathById(state.nodes, targetNode.id) : null;
            const markerRect = stageTarget.markerEl ? markerRectFromStageEl(stageTarget.markerEl) : null;
            const pointer = normalizeStagePointerToParent(dragEvent);
            const pointerY = pointer ? pointer.y : null;
            const edgeHint = (() => {
                if (!markerRect || pointerY === null) return null;
                const relY = pointerY - markerRect.top;
                if (relY <= markerRect.height * 0.28) return 'before';
                if (relY >= markerRect.height * 0.72) return 'after';
                return 'inside';
            })();

            if (targetRef && canPlaceNodeInList(node, targetRef.parentPath)) {
                if (edgeHint === 'before' || edgeHint === 'after' || isLeafNode(targetNode)) {
                    const insertIndex = edgeHint === 'before'
                        ? Number(targetRef.index)
                        : Number(targetRef.index) + 1;
                    return {
                        listPath: targetRef.parentPath,
                        index: insertIndex,
                        markerKind: 'node',
                        nodeId: targetNode?.id || stageTarget.nodeId,
                        columnId: null,
                        position: edgeHint === 'before' ? 'before' : 'after',
                        label: edgeHint === 'before' ? 'Вставка перед элементом' : 'Вставка после элемента',
                    };
                }
            }

            if (targetNode?.type === 'section' && targetNodePath) {
                const p = `${targetNodePath}.children`;
                if (canPlaceNodeInList(node, p)) {
                    const list = resolveListRef(state.nodes, p) || [];
                    return {
                        listPath: p,
                        index: Array.isArray(list) ? list.length : null,
                        markerKind: 'node',
                        nodeId: targetNode.id,
                        columnId: null,
                        position: 'inside',
                        label: 'Вставка в секцию',
                    };
                }
            }

            if (targetNode?.type === 'columns' && targetNodePath) {
                const cols = Array.isArray(targetNode?.data?.columns) ? targetNode.data.columns : [];
                for (let i = 0; i < cols.length; i += 1) {
                    const p = `${targetNodePath}.data.columns.${i}.children`;
                    if (canPlaceNodeInList(node, p)) {
                        const list = resolveListRef(state.nodes, p) || [];
                        return {
                            listPath: p,
                            index: Array.isArray(list) ? list.length : null,
                            markerKind: 'node',
                            nodeId: targetNode.id,
                            columnId: null,
                            position: 'inside',
                            label: `Вставка в группу колонок (${i + 1})`,
                        };
                    }
                }
            }
        }

        if (!defaultListPath) return null;
        return {
            listPath: defaultListPath,
            index: null,
            markerKind: stageTarget.type === 'node' ? 'node' : (stageTarget.type === 'column' ? 'column' : null),
            nodeId: stageTarget.nodeId || stageTarget.ownerNodeId || null,
            columnId: stageTarget.columnId || null,
            position: 'append',
            label: 'Вставка в совместимый контейнер',
        };
    };

    const addElementByType = (rawType) => {
        const type = String(rawType || '');
        const node = createPaletteNode(type);
        const listPath = inferInsertListPathForNode(node);
        if (!listPath) {
            dialogs.alert('Не удалось определить область вставки. Выберите секцию или колонку в структуре.');
            return;
        }
        insertIntoList(listPath, node);
    };

    const applyPreset = (presetKey) => {
        const factory = sectionPresetFactories[String(presetKey || '')];
        if (!factory) return;
        const nodes = ensureNodeIds(factory());
        nodes.forEach((node) => {
            if (node.type !== 'section') {
                const wrap = makeSection('Секция');
                wrap.children.push(node);
                insertIntoList('root', wrap);
            } else {
                insertIntoList('root', node);
            }
        });
    };

    const moveNodeByPaths = (sourceNodePath, targetListPath, targetIndex) => {
        if (!sourceNodePath || !targetListPath) return;
        if (pathStartsWith(targetListPath, sourceNodePath)) return;
        const source = resolveNodeRef(state.nodes, sourceNodePath);
        const targetList = resolveListRef(state.nodes, targetListPath);
        if (!source || !Array.isArray(targetList)) return;
        if (!canPlaceNodeInList(source.node, targetListPath)) return;
        const originalNode = source.node;
        source.parentList.splice(source.index, 1);
        let insertIndex = Math.max(0, Math.min(targetList.length, Number(targetIndex)));
        if (source.parentList === targetList && source.index < insertIndex) insertIndex -= 1;
        targetList.splice(insertIndex, 0, originalNode);
        state.selectedNodeId = originalNode.id;
        syncNodesToBuilder();
    };

    const nodeAction = (action, nodePath) => {
        const ref = resolveNodeRef(state.nodes, nodePath);
        if (!ref) return;
        if (action === 'delete') {
            const deletedNodeId = String(ref.node?.id || '');
            ref.parentList.splice(ref.index, 1);
            if (state.selectedNodeId === ref.node?.id) {
                state.selectedNodeId = null;
                state.stageSelectedMarkerId = null;
                state.stageHoverMarkerId = (state.stageHoverMarkerId === deletedNodeId) ? null : state.stageHoverMarkerId;
                closeStageInlineEditor({ commit: false });
                hideStageOverlaySelectionUi();
            }
            syncNodesToBuilder();
            recomputeStageOverlayRects();
            return;
        }
        if (action === 'duplicate') {
            const copy = clone(ref.node);
            ensureNodeIds([copy]);
            ref.parentList.splice(ref.index + 1, 0, copy);
            state.selectedNodeId = copy.id;
            syncNodesToBuilder();
            return;
        }
        if (action === 'up' && ref.index > 0) {
            [ref.parentList[ref.index - 1], ref.parentList[ref.index]] = [ref.parentList[ref.index], ref.parentList[ref.index - 1]];
            syncNodesToBuilder();
            return;
        }
        if (action === 'down' && ref.index < ref.parentList.length - 1) {
            [ref.parentList[ref.index + 1], ref.parentList[ref.index]] = [ref.parentList[ref.index], ref.parentList[ref.index + 1]];
            syncNodesToBuilder();
            return;
        }
    };

    const applyInspectorField = (name, value, inputEl) => {
        const ref = getSelectedRef();
        if (!ref) return;
        const node = ref.node;
        if (!node || typeof node !== 'object') return;
        node.data = (node.data && typeof node.data === 'object') ? node.data : {};
        if (!String(name || '').startsWith('data.')) return;
        const key = String(name).slice(5);

        if (node.type === 'section') {
            if (['label','container','padding_y','background','background_color','anchor_id'].includes(key)) node.data[key] = value;
            syncNodesToBuilder();
            return;
        }

        if (node.type === 'columns') {
            if (key === 'gap' || key === 'align_y') node.data[key] = value;
            syncNodesToBuilder();
            return;
        }

        if (key === 'level') {
            node.data.level = Math.min(6, Math.max(1, Number(value || 2)));
        } else if (key === 'ordered') {
            node.data.ordered = !!(inputEl && inputEl.checked);
        } else if (key === 'target_blank' || key === 'nofollow') {
            node.data[key] = !!(inputEl && inputEl.checked);
        } else if (key === '_module') {
            const widgets = typeof bridge.widgetsForModule === 'function' ? bridge.widgetsForModule(value) : [];
            const nextDefinition = widgets[0] || null;
            node.data.module = String(value || '');
            node.data.widget = String(nextDefinition?.widget || '');
            node.data.config = typeof bridge.moduleWidgetDefaultConfig === 'function'
                ? bridge.moduleWidgetDefaultConfig(nextDefinition)
                : {};
        } else if (key === '_widget') {
            const nextDefinition = typeof bridge.findWidgetDefinition === 'function'
                ? bridge.findWidgetDefinition(node.data.module, value)
                : null;
            node.data.widget = String(value || '');
            node.data.config = nextDefinition && typeof bridge.moduleWidgetDefaultConfig === 'function'
                ? Object.assign(bridge.moduleWidgetDefaultConfig(nextDefinition), (node.data.config && typeof node.data.config === 'object') ? node.data.config : {})
                : ((node.data.config && typeof node.data.config === 'object') ? node.data.config : {});
        } else if (key === '_config_json') {
            try {
                const parsed = JSON.parse(String(value || '{}'));
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    node.data.config = parsed;
                }
            } catch (_) {}
        } else if (key === 'limit') {
            node.data.limit = Math.max(1, Math.min(100, Number(value || 10)));
        } else if (key.startsWith('config.')) {
            const configKey = key.slice('config.'.length);
            node.data.config = (node.data.config && typeof node.data.config === 'object') ? node.data.config : {};
            node.data.config[configKey] = inputEl?.type === 'checkbox' ? !!inputEl.checked : value;
        } else if (key === '_gallery_lines') {
            node.data.items = bridge.fromGalleryLines ? bridge.fromGalleryLines(value) : [];
        } else if (key === '_list_lines') {
            node.data.items = bridge.fromListLines ? bridge.fromListLines(value) : [];
        } else if (key === '_table_lines') {
            node.data.rows = bridge.fromTableLines ? bridge.fromTableLines(value) : [];
        } else if (key === '_faq_text') {
            node.data.items = bridge.fromFaqText ? bridge.fromFaqText(value) : [];
        } else {
            node.data[key] = value;
        }
        syncNodesToBuilder();
    };

    const applySeoField = (field, value) => {
        const locale = getCurrentLocale();
        const f = getLocaleFields(locale);
        if (field === 'meta_title' && f.metaTitle) f.metaTitle.value = value;
        if (field === 'canonical_url' && f.canonical) f.canonical.value = value;
        if (field === 'meta_description' && f.metaDescription) f.metaDescription.value = value;
        if (field === 'custom_head_html' && f.customHead) f.customHead.value = value;
        try {
            if (field === 'meta_title' && f.metaTitle) f.metaTitle.dispatchEvent(new Event('input', { bubbles: true }));
            if (field === 'canonical_url' && f.canonical) f.canonical.dispatchEvent(new Event('input', { bubbles: true }));
            if (field === 'meta_description' && f.metaDescription) f.metaDescription.dispatchEvent(new Event('input', { bubbles: true }));
            if (field === 'custom_head_html' && f.customHead) f.customHead.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (_) {}
        bridge.updateSeoPreview?.(locale);
        renderSeoPanel();
        setSyncState('SEO синхронизировано с формой');
        scheduleStageRender(650, 'seo');
    };

    const applyPublishField = (field, value) => {
        const status = getPageStatusField();
        const pageType = getPageTypeField();
        if (field === 'status' && status) {
            status.value = value;
            try { status.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
        }
        if (field === 'page_type' && pageType) {
            pageType.value = value;
            try { pageType.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
        }
        setSyncState('Публикация синхронизирована с формой');
        scheduleStageRender(450, 'publish');
    };

    const open = (requestedLocale = null) => {
        state.locale = String(requestedLocale || bridge.getActiveLocale?.() || state.locale || 'ru');
        state.nodes = loadLocaleNodes(state.locale);
        state.selectedNodeId = null;
        state.canvasRichEditingNodeId = null;
        state.localDirty = false;
        state.stageSelectedMarkerId = null;
        state.stageHoverMarkerId = null;
        state.tableInspectorHasUncommittedChanges = false;
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        state.open = true;
        bridge.uiWrite?.(KEYS.open, 'open');
        renderAll();
        setSyncState('Синхронизировано с формой');
        scheduleStageRender(20, 'open');
    };

    const close = () => {
        if (state.localDirty && !dialogs.confirm('Закрыть fullscreen builder? Локальные изменения будут синхронизированы с формой, но страница может быть ещё не сохранена на сервере.')) {
            return;
        }
        flushCanvasRichLateCommit('Rich text синхронизирован перед закрытием fullscreen');
        flushStageRichLateCommit('Stage rich text синхронизирован перед закрытием');
        flushTableInspectorLateCommit('Таблица синхронизирована перед закрытием fullscreen');
        closeStageInlineEditor({ commit: false });
        flushStagePendingRender();
        if (state.stageRenderAbort) {
            try { state.stageRenderAbort.abort(); } catch (_) {}
            state.stageRenderAbort = null;
        }
        if (state.canvasRichCommitTimer) {
            window.clearTimeout(state.canvasRichCommitTimer);
            state.canvasRichCommitTimer = null;
        }
        state.canvasRichHasUncommittedChanges = false;
        state.canvasRichEditingNodeId = null;
        state.stageRichInlineNodeId = null;
        state.stageRichHasUncommittedChanges = false;
        state.tableInspectorHasUncommittedChanges = false;
        state.stageDropCandidate = null;
        hideResizeOverlay();
        hideStageBanner();
        hideStageDropHintUi();
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        state.open = false;
        bridge.uiWrite?.(KEYS.open, 'closed');
    };

    const submitPageForm = (publish = false) => {
        flushCanvasRichLateCommit('Rich text синхронизирован перед сохранением');
        flushStageRichLateCommit('Stage rich text синхронизирован перед сохранением');
        flushTableInspectorLateCommit('Таблица синхронизирована перед сохранением');
        flushStagePendingRender();
        bridge.syncBuilders?.();
        const statusField = getPageStatusField();
        if (publish && statusField) {
            statusField.value = 'published';
            try { statusField.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
        }
        setSyncState('Отправка формы…', 'saving');
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    refs.closeBtns.forEach((btn) => btn.addEventListener('click', close));
    refs.saveBtn?.addEventListener('click', () => submitPageForm(false));
    refs.savePublishBtn?.addEventListener('click', () => submitPageForm(true));
    refs.localeTabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page-fullscreen-locale]');
        if (!btn) return;
        setLocale(btn.getAttribute('data-page-fullscreen-locale'));
    });
    refs.leftTabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fs-left-tab]');
        if (!btn) return;
        setLeftTab(btn.getAttribute('data-fs-left-tab'));
    });
    refs.rightTabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fs-right-tab]');
        if (!btn) return;
        setRightTab(btn.getAttribute('data-fs-right-tab'));
    });
    refs.deviceToggle?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fs-device]');
        if (!btn) return;
        setDevice(btn.getAttribute('data-fs-device'));
    });
    refs.centerToggle?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fs-center-mode]');
        if (!btn) return;
        setCenterMode(btn.getAttribute('data-fs-center-mode'));
    });

    refs.stageIframe?.addEventListener('load', () => {
        try {
            attachStageFrameBridge();
        } catch (error) {
            console.error('[FullscreenStage] attach bridge error', error);
            showStageBanner('Stage загружен с ошибкой bridge. Используйте Structure Canvas.', true);
        }
        window.requestAnimationFrame(recomputeStageOverlayRects);
    });

    refs.elements?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fs-element]');
        if (!btn) return;
        addElementByType(btn.getAttribute('data-fs-element'));
    });
    refs.elements?.addEventListener('dragstart', (e) => {
        const btn = e.target.closest('[data-fs-element]');
        if (!btn) return;
        state.dragPaletteType = String(btn.getAttribute('data-fs-element') || '');
        if (!state.dragPaletteType) return;
        try {
            e.dataTransfer.effectAllowed = 'copyMove';
            e.dataTransfer.setData('text/plain', `palette:${state.dragPaletteType}`);
        } catch (_) {}
        btn.classList.add('dragging');
    });
    refs.elements?.addEventListener('dragend', (e) => {
        const btn = e.target.closest('[data-fs-element]');
        if (btn) btn.classList.remove('dragging');
        state.dragPaletteType = null;
        state.stageDropCandidate = null;
        hideStageDropHintUi();
        refs.canvas?.querySelectorAll('[data-drop-zone]').forEach((el) => el.classList.remove('is-over'));
    });
    refs.presets?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fs-preset]');
        if (!btn) return;
        applyPreset(btn.getAttribute('data-fs-preset'));
    });
    refs.structure?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fs-select-node]');
        if (!btn) return;
        state.selectedNodeId = btn.getAttribute('data-fs-select-node');
        state.stageSelectedMarkerId = state.selectedNodeId;
        renderCanvas();
        renderStructure();
        renderInspector();
        recomputeStageOverlayRects();
    });

    refs.canvas?.addEventListener('click', (e) => {
        const selectBtn = e.target.closest('[data-fs-select-node]');
        if (selectBtn) {
            state.selectedNodeId = selectBtn.getAttribute('data-fs-select-node');
            state.stageSelectedMarkerId = state.selectedNodeId;
            renderCanvas();
            renderStructure();
            renderInspector();
            recomputeStageOverlayRects();
            return;
        }

        const actionBtn = e.target.closest('[data-node-action]');
        if (actionBtn) {
            const nodeEl = e.target.closest('[data-node-path]');
            if (!nodeEl) return;
            nodeAction(actionBtn.getAttribute('data-node-action'), nodeEl.getAttribute('data-node-path'));
            recomputeStageOverlayRects();
            return;
        }

        const quickAddBtn = e.target.closest('[data-fs-quick-add]');
        if (quickAddBtn) {
            const emptyBox = e.target.closest('[data-empty-list-path]');
            const listPath = emptyBox?.getAttribute('data-empty-list-path') || 'root';
            const kind = quickAddBtn.getAttribute('data-fs-quick-add');
            let node = null;
            if (kind === 'section') node = makeSection('Новая секция');
            else if (kind === 'columns-2') node = makeColumns(2);
            else if (kind === 'columns-3') node = makeColumns(3);
            else node = makeLeaf(String(kind));
            insertIntoList(listPath, node);
            return;
        }

        const dz = e.target.closest('[data-drop-zone]');
        if (dz && state.dragSourceNodePath) {
            moveNodeByPaths(state.dragSourceNodePath, dz.getAttribute('data-drop-list-path'), Number(dz.getAttribute('data-drop-index')));
            return;
        }
    });

    refs.canvas?.addEventListener('dragstart', (e) => {
        const colGrip = e.target.closest('.fs-column-drag');
        const colEl = colGrip ? colGrip.closest('[data-fs-column-card]') : null;
        if (colEl) {
            const ownerNodePath = String(colEl.getAttribute('data-fs-columns-node-path') || '');
            const index = Number(colEl.getAttribute('data-fs-column-index'));
            if (!ownerNodePath || !Number.isInteger(index)) return;
            state.dragColumn = { ownerNodePath, index };
            state.dragSourceNodePath = null;
            colEl.classList.add('dragging-col');
            try {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', `column:${ownerNodePath}:${index}`);
            } catch (_) {}
            return;
        }

        const nodeEl = e.target.closest('[data-node-path]');
        if (!nodeEl) return;
        state.dragSourceNodePath = nodeEl.getAttribute('data-node-path');
        nodeEl.classList.add('dragging');
        try {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', state.dragSourceNodePath || '');
        } catch (_) {}
    });
    refs.canvas?.addEventListener('dragend', (e) => {
        if (state.dragColumn) {
            const colEl = e.target.closest('[data-fs-column-card]');
            if (colEl) colEl.classList.remove('dragging-col');
            refs.canvas.querySelectorAll('[data-fs-column-card]').forEach((el) => el.classList.remove('is-drag-over', 'dragging-col'));
            state.dragColumn = null;
            return;
        }

        const nodeEl = e.target.closest('[data-node-path]');
        if (nodeEl) nodeEl.classList.remove('dragging');
        state.dragSourceNodePath = null;
        refs.canvas.querySelectorAll('[data-drop-zone]').forEach((el) => el.classList.remove('is-over'));
    });
    refs.canvas?.addEventListener('dragover', (e) => {
        if (state.dragPaletteType) {
            const dz = e.target.closest('[data-drop-zone]');
            if (!dz) return;
            const probeNode = createPaletteNode(state.dragPaletteType);
            const listPath = dz.getAttribute('data-drop-list-path');
            if (!canPlaceNodeInList(probeNode, listPath)) {
                refs.canvas.querySelectorAll('[data-drop-zone]').forEach((el) => el.classList.remove('is-over'));
                return;
            }
            e.preventDefault();
            refs.canvas.querySelectorAll('[data-drop-zone]').forEach((el) => el.classList.remove('is-over'));
            dz.classList.add('is-over');
            return;
        }
        if (state.dragColumn) {
            const colEl = e.target.closest('[data-fs-column-card]');
            const gridEl = e.target.closest('[data-fs-columns-grid-path]');
            const ownerNodePath = String((colEl?.getAttribute('data-fs-columns-node-path')) || (gridEl?.getAttribute('data-fs-columns-grid-path')) || '');
            const index = colEl ? Number(colEl.getAttribute('data-fs-column-index')) : Number.MAX_SAFE_INTEGER;
            refs.canvas.querySelectorAll('[data-fs-column-card]').forEach((el) => el.classList.remove('is-drag-over'));
            if (ownerNodePath && ownerNodePath !== state.dragColumn.ownerNodePath && gridEl) {
                e.preventDefault();
                gridEl.querySelectorAll('[data-fs-column-card]').forEach((el) => el.classList.add('is-drag-over'));
                return;
            }
            if (colEl && ownerNodePath && Number.isInteger(index) && !(ownerNodePath === state.dragColumn.ownerNodePath && index === state.dragColumn.index)) {
                e.preventDefault();
                colEl.classList.add('is-drag-over');
            }
            return;
        }

        const dz = e.target.closest('[data-drop-zone]');
        if (!dz) return;
        e.preventDefault();
        refs.canvas.querySelectorAll('[data-drop-zone]').forEach((el) => el.classList.remove('is-over'));
        dz.classList.add('is-over');
    });
    refs.canvas?.addEventListener('drop', (e) => {
        if (state.dragPaletteType) {
            const dz = e.target.closest('[data-drop-zone]');
            if (!dz) return;
            e.preventDefault();
            refs.canvas.querySelectorAll('[data-drop-zone]').forEach((el) => el.classList.remove('is-over'));
            const node = createPaletteNode(state.dragPaletteType);
            const listPath = dz.getAttribute('data-drop-list-path');
            if (!canPlaceNodeInList(node, listPath)) {
                const fallbackPath = inferInsertListPathForNode(node);
                if (!fallbackPath) {
                    dialogs.alert('Этот тип элемента нельзя вставить в выбранную область.');
                    state.dragPaletteType = null;
                    return;
                }
                insertIntoList(fallbackPath, node);
            } else {
                insertIntoList(listPath, node, Number(dz.getAttribute('data-drop-index')));
            }
            state.dragPaletteType = null;
            return;
        }
        if (state.dragColumn) {
            e.preventDefault();
            const colEl = e.target.closest('[data-fs-column-card]');
            const gridEl = e.target.closest('[data-fs-columns-grid-path]');
            const ownerNodePath = String((colEl?.getAttribute('data-fs-columns-node-path')) || (gridEl?.getAttribute('data-fs-columns-grid-path')) || '');
            const index = colEl ? Number(colEl.getAttribute('data-fs-column-index')) : null;
            refs.canvas.querySelectorAll('[data-fs-column-card]').forEach((el) => el.classList.remove('is-drag-over', 'dragging-col'));
            const active = state.dragColumn;
            state.dragColumn = null;
            if (!ownerNodePath) return;
            if (ownerNodePath === active.ownerNodePath && Number.isInteger(index)) {
                moveColumn(active.ownerNodePath, active.index, index);
                return;
            }
            if (ownerNodePath !== active.ownerNodePath) {
                const targetRef = resolveColumnsNodeRefByPath(ownerNodePath);
                const targetCols = Array.isArray(targetRef?.node?.data?.columns) ? targetRef.node.data.columns : [];
                const targetIndex = Number.isInteger(index) ? index : targetCols.length;
                moveColumnBetweenNodes(active.ownerNodePath, active.index, ownerNodePath, targetIndex);
            }
            return;
        }

        const dz = e.target.closest('[data-drop-zone]');
        if (!dz) return;
        e.preventDefault();
        moveNodeByPaths(state.dragSourceNodePath, dz.getAttribute('data-drop-list-path'), Number(dz.getAttribute('data-drop-index')));
    });

    refs.stageWrap?.addEventListener('dragover', (e) => {
        if (!state.dragPaletteType) return;
        e.preventDefault();
        state.stageDropCandidate = computeStageDropCandidateForPalette(state.dragPaletteType, e.target, e);
        recomputeStageOverlayRects();
    });
    refs.stageWrap?.addEventListener('dragleave', (e) => {
        if (!state.dragPaletteType) return;
        if (e.target !== refs.stageWrap) return;
        state.stageDropCandidate = null;
        hideStageDropHintUi();
    });
    refs.stageWrap?.addEventListener('drop', (e) => {
        if (!state.dragPaletteType) return;
        e.preventDefault();
        const node = createPaletteNode(state.dragPaletteType);
        const candidate = state.stageDropCandidate || computeStageDropCandidateForPalette(state.dragPaletteType, e.target, e);
        const listPath = candidate?.listPath || null;
        if (!listPath) {
            state.stageDropCandidate = null;
            hideStageDropHintUi();
            dialogs.alert('Выберите секцию/колонку, чтобы вставить элемент на Stage.');
            return;
        }
        insertIntoList(listPath, node, Number.isInteger(candidate?.index) ? candidate.index : null);
        state.dragPaletteType = null;
        state.stageDropCandidate = null;
        hideStageDropHintUi();
    });

    refs.canvas?.addEventListener('dblclick', (e) => {
        const preview = e.target.closest('[data-fs-rich-preview-node]');
        if (!preview) return;
        const nodeId = preview.getAttribute('data-fs-rich-preview-node');
        if (!nodeId) return;
        state.selectedNodeId = nodeId;
        state.stageSelectedMarkerId = nodeId;
        setRightTab('content');
        renderStructure();
        renderInspector();
        setCanvasRichEditing(nodeId);
    });

    refs.canvas?.addEventListener('pointerdown', (e) => {
        const handle = e.target.closest('[data-fs-col-resize-handle]');
        if (!handle) return;
        const ownerNodePath = String(handle.getAttribute('data-fs-columns-node-path') || '');
        const leftIndex = Number(handle.getAttribute('data-fs-col-left-index'));
        const ref = resolveColumnsNodeRefByPath(ownerNodePath);
        const cols = Array.isArray(ref?.node?.data?.columns) ? ref.node.data.columns : [];
        if (!ref || !Number.isInteger(leftIndex) || !cols[leftIndex] || !cols[leftIndex + 1]) return;
        const grid = handle.closest('[data-fs-columns-grid-path]');
        const rect = grid?.getBoundingClientRect?.();
        const gridWidth = Math.max(1, Number(rect?.width || 0));
        state.columnResize = {
            ownerNodePath,
            leftIndex,
            startX: Number(e.clientX || 0),
            leftStartSpan: Number(cols[leftIndex].span || 1),
            rightStartSpan: Number(cols[leftIndex + 1].span || 1),
            gridWidth,
            pairTotal: Number(cols[leftIndex].span || 1) + Number(cols[leftIndex + 1].span || 1),
        };
        showResizeOverlay(e, ownerNodePath, leftIndex);
        e.preventDefault();
    });

    window.addEventListener('pointermove', (e) => {
        const resize = state.columnResize;
        if (!resize) return;
        const colUnit = Math.max(1, resize.gridWidth / 12);
        const dx = Number(e.clientX || 0) - resize.startX;
        const deltaCols = Math.round(dx / colUnit);
        const nextLeftSpan = Math.max(1, Math.min((resize.pairTotal || 12) - 1, resize.leftStartSpan + deltaCols));
        if (!applyColumnSpansPair(resize.ownerNodePath, resize.leftIndex, nextLeftSpan)) return;
        showResizeOverlay(e, resize.ownerNodePath, resize.leftIndex);
        scheduleSyncNodesToBuilder('Изменение ширины колонок…');
    });

    const finishColumnResize = () => {
        if (!state.columnResize) return;
        state.columnResize = null;
        hideResizeOverlay();
        syncNodesToBuilder();
        setSyncState('Ширина колонок обновлена');
    };
    window.addEventListener('pointerup', finishColumnResize);
    window.addEventListener('pointercancel', finishColumnResize);

    refs.canvas?.addEventListener('pointerdown', (e) => {
        const toolbarBtn = e.target.closest('[data-fs-canvas-rich-toolbar] .fs-node-btn');
        if (toolbarBtn) {
            // Keep contenteditable selection alive when clicking toolbar controls.
            e.preventDefault();
        }
    });

    refs.canvas?.addEventListener('click', (e) => {
        const toolbar = e.target.closest('[data-fs-canvas-rich-toolbar]');
        if (!toolbar) return;
        const nodeId = toolbar.getAttribute('data-fs-rich-preview-node');
        const shell = toolbar.closest('[data-fs-rich-preview-node]');
        const editor = shell?.querySelector('[data-fs-rich-inline-editor]');
        if (!nodeId || !editor) return;

        const closeBtn = e.target.closest('[data-fs-canvas-rich-close]');
        if (closeBtn) {
            syncCanvasRichInlineToState(editor, { immediate: true });
            state.canvasRichEditingNodeId = null;
            renderCanvas();
            return;
        }

        const inspectorBtn = e.target.closest('[data-fs-canvas-rich-open-inspector]');
        if (inspectorBtn) {
            state.selectedNodeId = nodeId;
            setRightTab('content');
            renderStructure();
            renderInspector();
            window.requestAnimationFrame(() => refs.inspector?.querySelector('[data-fs-rich-editor]')?.focus());
            return;
        }

        const cmdBtn = e.target.closest('[data-fs-canvas-rich-cmd]');
        if (cmdBtn) {
            editor.focus();
            execRichCommand(cmdBtn.getAttribute('data-fs-canvas-rich-cmd'));
            syncCanvasRichInlineToState(editor);
            return;
        }

        const blockBtn = e.target.closest('[data-fs-canvas-rich-block]');
        if (blockBtn) {
            editor.focus();
            execRichCommand('formatBlock', blockBtn.getAttribute('data-fs-canvas-rich-block'));
            syncCanvasRichInlineToState(editor);
            return;
        }

        if (e.target.closest('[data-fs-canvas-rich-link]')) {
            editor.focus();
            const url = window.prompt('URL ссылки', 'https://');
            if (!url) return;
            execRichCommand('createLink', String(url).trim());
            syncCanvasRichInlineToState(editor);
            return;
        }

        const snippetBtn = e.target.closest('[data-fs-canvas-rich-snippet]');
        if (snippetBtn) {
            editor.focus();
            if (snippetBtn.getAttribute('data-fs-canvas-rich-snippet') === 'cta') {
                const label = window.prompt('Текст CTA', 'Кнопка CTA');
                if (label === null) return;
                const url = window.prompt('URL CTA', '/ru/blog');
                if (url === null) return;
                insertHtmlAtSelection(`<p><a class="cms-cta" href="${esc(String(url).trim() || '#')}">${esc(String(label).trim() || 'CTA')}</a></p>`);
                syncCanvasRichInlineToState(editor);
            }
            return;
        }

        const mediaBtn = e.target.closest('[data-fs-canvas-rich-media]');
        if (mediaBtn) {
            if (!bridge.openMediaPicker) {
                dialogs.alert('Медиатека недоступна.');
                return;
            }
            editor.focus();
            const mode = mediaBtn.getAttribute('data-fs-canvas-rich-media');
            if (mode === 'image') {
                bridge.openMediaPicker({ accept: 'image', multiple: false, title: 'Вставка изображения', subtitle: 'Выберите изображение из Assets' })
                    .then((asset) => {
                        if (!asset?.public_url) return;
                        const src = esc(asset.public_url || '');
                        const alt = esc(asset.alt || asset.title || '');
                        const caption = asset.caption ? `<figcaption>${esc(asset.caption)}</figcaption>` : '';
                        insertHtmlAtSelection(`<figure><img src="${src}" alt="${alt}" loading="lazy">${caption}</figure>`);
                        syncCanvasRichInlineToState(editor);
                    })
                    .catch(() => {});
                return;
            }
            if (mode === 'gallery') {
                bridge.openMediaPicker({ accept: 'image', multiple: true, title: 'Вставка галереи', subtitle: 'Выберите несколько изображений' })
                    .then((assets) => {
                        const selected = Array.isArray(assets) ? assets : (assets ? [assets] : []);
                        const valid = selected.filter((asset) => asset?.public_url);
                        if (valid.length === 0) return;
                        const html = `<div class="cms-gallery">${valid.map((asset) => `<img src="${esc(asset.public_url || '')}" alt="${esc(asset.alt || asset.title || '')}" loading="lazy">`).join('')}</div>`;
                        insertHtmlAtSelection(html);
                        syncCanvasRichInlineToState(editor);
                    })
                    .catch(() => {});
            }
        }
    });

    refs.canvas?.addEventListener('input', (e) => {
        const editor = e.target.closest('[data-fs-rich-inline-editor]');
        if (!editor) return;
        syncCanvasRichInlineToState(editor);
    });

    refs.canvas?.addEventListener('blur', (e) => {
        const editor = e.target.closest('[data-fs-rich-inline-editor]');
        if (!editor) return;
        // Commit on blur as a final sync without forcing exit from inline mode.
        window.setTimeout(() => syncCanvasRichInlineToState(editor, { immediate: true }), 0);
    }, true);

    refs.stageOverlay?.addEventListener('pointerdown', (e) => {
        if (e.target.closest('[data-fs-stage-rich-toolbar] .fs-node-btn')) {
            e.preventDefault();
        }
    });

    refs.stageOverlay?.addEventListener('click', (e) => {
        const actionBtn = e.target.closest('[data-fs-stage-action]');
        if (actionBtn) {
            const nodeId = String(state.stageSelectedMarkerId || state.selectedNodeId || '');
            if (!nodeId) return;
            if (actionBtn.getAttribute('data-fs-stage-action') === 'edit') {
                openStageInlineEditor(nodeId);
                return;
            }
            const path = findNodePathById(state.nodes, nodeId);
            if (!path) return;
            const stageAction = actionBtn.getAttribute('data-fs-stage-action');
            closeStageInlineEditor({ commit: true });
            nodeAction(stageAction, path);
            state.stageSelectedMarkerId = state.selectedNodeId ? String(state.selectedNodeId) : null;
            if (!state.stageSelectedMarkerId || stageAction === 'delete') {
                hideStageOverlaySelectionUi();
            }
            recomputeStageOverlayRects();
            scheduleStageRender(120, 'stage-action');
            return;
        }

        const panel = e.target.closest('[data-fs-stage-inline-panel]');
        if (!panel) return;

        if (e.target.closest('[data-fs-stage-inline-close]')) {
            closeStageInlineEditor({ commit: true });
            return;
        }

        if (e.target.closest('[data-fs-stage-open-inspector]')) {
            const nodeId = panel.getAttribute('data-fs-stage-inline-panel');
            if (nodeId) {
                state.selectedNodeId = nodeId;
                state.stageSelectedMarkerId = nodeId;
                setRightTab('content');
                renderStructure();
                renderInspector();
                closeStageInlineEditor({ commit: true });
                window.requestAnimationFrame(() => refs.inspector?.querySelector('input,textarea,[contenteditable="true"]')?.focus());
            }
            return;
        }

        const richEditor = panel.querySelector('[data-fs-stage-rich-editor]');
        if (!richEditor) return;

        if (e.target.closest('[data-fs-stage-rich-toolbar] .fs-node-btn')) {
            e.preventDefault();
        }

        const cmdBtn = e.target.closest('[data-fs-stage-rich-cmd]');
        if (cmdBtn) {
            richEditor.focus();
            execRichCommand(cmdBtn.getAttribute('data-fs-stage-rich-cmd'));
            richEditor.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        const blockBtn = e.target.closest('[data-fs-stage-rich-block]');
        if (blockBtn) {
            richEditor.focus();
            execRichCommand('formatBlock', blockBtn.getAttribute('data-fs-stage-rich-block'));
            richEditor.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        if (e.target.closest('[data-fs-stage-rich-link]')) {
            richEditor.focus();
            const url = window.prompt('URL ссылки', 'https://');
            if (!url) return;
            execRichCommand('createLink', String(url).trim());
            richEditor.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        const snippetBtn = e.target.closest('[data-fs-stage-rich-snippet]');
        if (snippetBtn) {
            richEditor.focus();
            if (snippetBtn.getAttribute('data-fs-stage-rich-snippet') === 'cta') {
                const label = window.prompt('Текст CTA', 'Кнопка CTA');
                if (label === null) return;
                const url = window.prompt('URL CTA', '/ru/blog');
                if (url === null) return;
                insertHtmlAtSelection(`<p><a class="cms-cta" href="${esc(String(url).trim() || '#')}">${esc(String(label).trim() || 'CTA')}</a></p>`);
                richEditor.dispatchEvent(new Event('input', { bubbles: true }));
            }
            return;
        }
        const mediaBtn = e.target.closest('[data-fs-stage-rich-media]');
        if (mediaBtn) {
            if (!bridge.openMediaPicker) return;
            richEditor.focus();
            const mode = mediaBtn.getAttribute('data-fs-stage-rich-media');
            if (mode === 'image') {
                bridge.openMediaPicker({ accept: 'image', multiple: false, title: 'Вставка изображения', subtitle: 'Выберите изображение из Assets' })
                    .then((asset) => {
                        if (!asset?.public_url) return;
                        const src = esc(asset.public_url || '');
                        const alt = esc(asset.alt || asset.title || '');
                        const caption = asset.caption ? `<figcaption>${esc(asset.caption)}</figcaption>` : '';
                        insertHtmlAtSelection(`<figure><img src="${src}" alt="${alt}" loading="lazy">${caption}</figure>`);
                        richEditor.dispatchEvent(new Event('input', { bubbles: true }));
                    })
                    .catch(() => {});
                return;
            }
            if (mode === 'gallery') {
                bridge.openMediaPicker({ accept: 'image', multiple: true, title: 'Вставка галереи', subtitle: 'Выберите несколько изображений' })
                    .then((assets) => {
                        const selected = Array.isArray(assets) ? assets : (assets ? [assets] : []);
                        const valid = selected.filter((asset) => asset?.public_url);
                        if (valid.length === 0) return;
                        insertHtmlAtSelection(`<div class="cms-gallery">${valid.map((asset) => `<img src="${esc(asset.public_url || '')}" alt="${esc(asset.alt || asset.title || '')}" loading="lazy">`).join('')}</div>`);
                        richEditor.dispatchEvent(new Event('input', { bubbles: true }));
                    })
                    .catch(() => {});
            }
        }
    });

    refs.stageOverlay?.addEventListener('input', (e) => {
        const panel = e.target.closest('[data-fs-stage-inline-panel]');
        if (!panel) return;
        const nodeId = panel.getAttribute('data-fs-stage-inline-panel');
        const ref = getNodeRefById(nodeId);
        if (!ref || !ref.node) return;
        const node = ref.node;
        const type = String(node.type || '');
        node.data = (node.data && typeof node.data === 'object') ? node.data : {};

        const heading = e.target.closest('[data-fs-stage-heading-input]');
        if (heading && type === 'heading') {
            node.data.text = heading.value;
            state.stageRichHasUncommittedChanges = true;
            setDirtyOnly('Stage heading изменён…');
            patchStageMarkerContentFromNode(nodeId);
            stageIdleRefreshAfterTextEdit();
            return;
        }

        const faqQ = e.target.closest('[data-fs-stage-faq-question]');
        if (faqQ && type === 'faq') {
            const items = Array.isArray(node.data.items) ? node.data.items : [];
            if (!items[0] || typeof items[0] !== 'object') items[0] = { question: '', answer: '<p></p>' };
            items[0].question = faqQ.value;
            node.data.items = items;
            state.stageRichHasUncommittedChanges = true;
            setDirtyOnly('FAQ вопрос изменён…');
            patchStageMarkerContentFromNode(nodeId);
            stageIdleRefreshAfterTextEdit();
            return;
        }

        const ctaLabel = e.target.closest('[data-fs-stage-cta-label]');
        const ctaUrl = e.target.closest('[data-fs-stage-cta-url]');
        if ((ctaLabel || ctaUrl) && type === 'cta') {
            if (ctaLabel) node.data.label = ctaLabel.value;
            if (ctaUrl) node.data.url = ctaUrl.value;
            state.stageRichHasUncommittedChanges = true;
            setDirtyOnly('CTA изменён…');
            patchStageMarkerContentFromNode(nodeId);
            stageIdleRefreshAfterTextEdit();
            return;
        }

        const rich = e.target.closest('[data-fs-stage-rich-editor]');
        if (rich && type === 'rich_text') {
            node.data.html = rich.innerHTML;
            state.stageRichHasUncommittedChanges = true;
            setDirtyOnly('Stage rich text изменён…');
            patchStageMarkerContentFromNode(nodeId);
            stageIdleRefreshAfterTextEdit();
            return;
        }
    });

    refs.stageOverlay?.addEventListener('change', (e) => {
        const panel = e.target.closest('[data-fs-stage-inline-panel]');
        if (!panel) return;
        const nodeId = panel.getAttribute('data-fs-stage-inline-panel');
        const ref = getNodeRefById(nodeId);
        if (!ref || !ref.node || ref.node.type !== 'cta') return;
        const ctaTarget = e.target.closest('[data-fs-stage-cta-target]');
        const ctaNofollow = e.target.closest('[data-fs-stage-cta-nofollow]');
        if (!ctaTarget && !ctaNofollow) return;
        ref.node.data = (ref.node.data && typeof ref.node.data === 'object') ? ref.node.data : {};
        if (ctaTarget) ref.node.data.target_blank = !!ctaTarget.checked;
        if (ctaNofollow) ref.node.data.nofollow = !!ctaNofollow.checked;
        state.stageRichHasUncommittedChanges = true;
        setDirtyOnly('CTA настройки изменены…');
        patchStageMarkerContentFromNode(nodeId);
        stageIdleRefreshAfterTextEdit();
    });

    refs.stageOverlay?.addEventListener('blur', (e) => {
        if (!e.target.closest('[data-fs-stage-inline-panel]')) return;
        window.setTimeout(() => {
            const active = document.activeElement;
            if (active && active.closest && active.closest('[data-fs-stage-inline-panel]')) return;
            flushStageRichLateCommit('Stage inline изменения синхронизированы');
            renderInspector();
        }, 0);
    }, true);

    const inspectorDelegator = (container) => {
        container?.addEventListener('input', (e) => {
            const input = e.target.closest('[data-fs-field]');
            if (!input) return;
            if (input.hasAttribute('data-fs-rich-managed')) return;
            if (input.hasAttribute('data-fs-table-managed')) return;
            const field = input.getAttribute('data-fs-field');
            const value = input.type === 'checkbox' ? input.checked : input.value;
            if (String(field).startsWith('seo.')) {
                applySeoField(String(field).slice(4), value);
                return;
            }
            if (String(field).startsWith('publish.')) {
                applyPublishField(String(field).slice(8), value);
                renderPublishPanel();
                return;
            }
            applyInspectorField(field, value, input);
        });
        container?.addEventListener('change', (e) => {
            const input = e.target.closest('[data-fs-field]');
            if (!input) return;
            if (input.hasAttribute('data-fs-rich-managed')) return;
            if (input.hasAttribute('data-fs-table-managed')) return;
            const field = input.getAttribute('data-fs-field');
            const value = input.type === 'checkbox' ? input.checked : input.value;
            if (String(field).startsWith('seo.')) {
                applySeoField(String(field).slice(4), value);
                return;
            }
            if (String(field).startsWith('publish.')) {
                applyPublishField(String(field).slice(8), value);
                renderPublishPanel();
                return;
            }
            applyInspectorField(field, value, input);
        });
    };
    inspectorDelegator(refs.inspector);
    inspectorDelegator(refs.layoutPanel);
    inspectorDelegator(refs.seoPanel);
    inspectorDelegator(refs.publishPanel);

    refs.inspector?.addEventListener('click', (e) => {
        const tableActionBtn = e.target.closest('[data-fs-table-action]');
        if (tableActionBtn) {
            const rows = getSelectedTableRows();
            if (!rows) return;
            const action = String(tableActionBtn.getAttribute('data-fs-table-action') || '');
            if (action === 'add-row') {
                rows.push(Array.from({ length: rows[0]?.length || 1 }, () => ''));
            } else if (action === 'add-col') {
                if ((rows[0]?.length || 0) >= 12) return;
                rows.forEach((row) => row.push(''));
            } else if (action === 'normalize') {
                const normalized = normalizeTableRows(rows, { minRows: 1, minCols: 1, maxCols: 12 });
                rows.splice(0, rows.length, ...normalized);
            } else {
                return;
            }
            state.tableInspectorHasUncommittedChanges = false;
            syncNodesToBuilder();
            return;
        }

        const tableRowRemoveBtn = e.target.closest('[data-fs-table-row-remove]');
        if (tableRowRemoveBtn) {
            const rows = getSelectedTableRows();
            if (!rows || rows.length <= 1) return;
            const rowIndex = Number(tableRowRemoveBtn.getAttribute('data-fs-table-row-remove'));
            if (!Number.isInteger(rowIndex) || !rows[rowIndex]) return;
            rows.splice(rowIndex, 1);
            state.tableInspectorHasUncommittedChanges = false;
            syncNodesToBuilder();
            return;
        }

        const tableColRemoveBtn = e.target.closest('[data-fs-table-col-remove]');
        if (tableColRemoveBtn) {
            const rows = getSelectedTableRows();
            if (!rows) return;
            const colCount = rows[0]?.length || 0;
            if (colCount <= 1) return;
            const colIndex = Number(tableColRemoveBtn.getAttribute('data-fs-table-col-remove'));
            if (!Number.isInteger(colIndex) || colIndex < 0 || colIndex >= colCount) return;
            rows.forEach((row) => row.splice(colIndex, 1));
            state.tableInspectorHasUncommittedChanges = false;
            syncNodesToBuilder();
            return;
        }

        const modeBtn = e.target.closest('[data-fs-rich-mode]');
        if (modeBtn) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            setRichMode(modeBtn.getAttribute('data-fs-rich-mode'), shell);
            if (modeBtn.getAttribute('data-fs-rich-mode') === 'visual') {
                shell.querySelector('[data-fs-rich-editor]')?.focus();
            }
            return;
        }

        const cmdBtn = e.target.closest('[data-fs-rich-cmd]');
        if (cmdBtn) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            const editor = shell.querySelector('[data-fs-rich-editor]');
            if (!editor) return;
            editor.focus();
            execRichCommand(cmdBtn.getAttribute('data-fs-rich-cmd'));
            syncRichVisualToTextarea(shell);
            return;
        }

        const blockBtn = e.target.closest('[data-fs-rich-block]');
        if (blockBtn) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            const editor = shell.querySelector('[data-fs-rich-editor]');
            if (!editor) return;
            editor.focus();
            execRichCommand('formatBlock', blockBtn.getAttribute('data-fs-rich-block'));
            syncRichVisualToTextarea(shell);
            return;
        }

        const linkBtn = e.target.closest('[data-fs-rich-link]');
        if (linkBtn) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            const editor = shell.querySelector('[data-fs-rich-editor]');
            if (!editor) return;
            editor.focus();
            const url = window.prompt('URL ссылки', 'https://');
            if (!url) return;
            execRichCommand('createLink', url.trim());
            syncRichVisualToTextarea(shell);
            return;
        }

        const snippetBtn = e.target.closest('[data-fs-rich-snippet]');
        if (snippetBtn) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            const editor = shell.querySelector('[data-fs-rich-editor]');
            if (!editor) return;
            editor.focus();
            const key = snippetBtn.getAttribute('data-fs-rich-snippet');
            if (key === 'cta') {
                const label = window.prompt('Текст CTA', 'Кнопка CTA');
                if (label === null) return;
                const url = window.prompt('URL CTA', '/ru/blog');
                if (url === null) return;
                insertHtmlAtSelection(`<p><a class="cms-cta" href="${esc(String(url).trim() || '#')}">${esc(String(label).trim() || 'CTA')}</a></p>`);
                syncRichVisualToTextarea(shell);
            }
            return;
        }

        const richMediaBtn = e.target.closest('[data-fs-rich-media]');
        if (richMediaBtn) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            const editor = shell.querySelector('[data-fs-rich-editor]');
            if (!editor || !bridge.openMediaPicker) return;
            editor.focus();
            const mode = richMediaBtn.getAttribute('data-fs-rich-media');
            if (mode === 'image') {
                bridge.openMediaPicker({ accept: 'image', multiple: false, title: 'Вставка изображения', subtitle: 'Выберите изображение из Assets' })
                    .then((asset) => {
                        if (!asset?.public_url) return;
                        const src = esc(asset.public_url || '');
                        const alt = esc(asset.alt || asset.title || '');
                        const caption = asset.caption ? `<figcaption>${esc(asset.caption)}</figcaption>` : '';
                        insertHtmlAtSelection(`<figure><img src="${src}" alt="${alt}" loading="lazy">${caption}</figure>`);
                        syncRichVisualToTextarea(shell);
                    })
                    .catch(() => {});
                return;
            }
            if (mode === 'gallery') {
                bridge.openMediaPicker({ accept: 'image', multiple: true, title: 'Вставка галереи', subtitle: 'Выберите несколько изображений' })
                    .then((assets) => {
                        const selected = Array.isArray(assets) ? assets : (assets ? [assets] : []);
                        const valid = selected.filter((asset) => asset?.public_url);
                        if (valid.length === 0) return;
                        const html = `<div class="cms-gallery">${valid.map((asset) => `<img src="${esc(asset.public_url || '')}" alt="${esc(asset.alt || asset.title || '')}" loading="lazy">`).join('')}</div>`;
                        insertHtmlAtSelection(html);
                        syncRichVisualToTextarea(shell);
                    })
                    .catch(() => {});
            }
            return;
        }
    });

    refs.inspector?.addEventListener('input', (e) => {
        const tableCell = e.target.closest('[data-fs-table-cell]');
        if (tableCell) {
            const rows = getSelectedTableRows();
            if (!rows) return;
            const rowIndex = Number(tableCell.getAttribute('data-fs-table-row'));
            const colIndex = Number(tableCell.getAttribute('data-fs-table-col'));
            if (!Number.isInteger(rowIndex) || !Number.isInteger(colIndex) || !rows[rowIndex] || typeof rows[rowIndex][colIndex] === 'undefined') return;
            rows[rowIndex][colIndex] = String(tableCell.value || '');
            state.tableInspectorHasUncommittedChanges = true;
            setDirtyOnly('Таблица изменена…');
            const selectedId = String(state.selectedNodeId || '');
            if (selectedId) {
                patchStageMarkerContentFromNode(selectedId);
                stageIdleRefreshAfterTextEdit();
            }
            return;
        }

        const visual = e.target.closest('[data-fs-rich-editor]');
        if (visual) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            syncRichVisualToTextarea(shell);
            return;
        }
        const source = e.target.closest('[data-fs-rich-html-source]');
        if (source) {
            const shell = e.target.closest('[data-fs-rich-shell]') || refs.inspector;
            const visualEl = shell.querySelector('[data-fs-rich-editor]');
            if (visualEl && document.activeElement !== visualEl) {
                visualEl.innerHTML = source.value;
            }
            setDirtyOnly('Rich text HTML изменён…');
            if (state.richCommitTimer) window.clearTimeout(state.richCommitTimer);
            state.richCommitTimer = window.setTimeout(() => {
                applyInspectorField('data.html', source.value, source);
                state.richCommitTimer = null;
            }, 350);
            const status = shell.querySelector('[data-fs-rich-status]');
            if (status) status.textContent = 'HTML-режим: синхронизация…';
            return;
        }
    });

    refs.inspector?.addEventListener('blur', (e) => {
        if (!e.target.closest('[data-fs-table-cell]')) return;
        window.setTimeout(() => {
            const active = document.activeElement;
            if (active && active.closest && active.closest('[data-fs-table-editor]')) return;
            flushTableInspectorLateCommit('Таблица синхронизирована с формой');
        }, 0);
    }, true);

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
    });

    refs.layoutPanel?.addEventListener('click', (e) => {
        const addChild = e.target.closest('[data-fs-add-child]');
        if (addChild) {
            const ref = getSelectedRef();
            if (!ref || ref.node?.type !== 'section') return;
            const nodePath = findNodePathById(state.nodes, ref.node.id);
            if (!nodePath) return;
            const listPath = `${nodePath}.children`;
            const kind = addChild.getAttribute('data-fs-add-child')?.split(':')[1] || 'heading';
            if (kind === 'columns-2') insertIntoList(listPath, makeColumns(2));
            else if (kind === 'columns-3') insertIntoList(listPath, makeColumns(3));
            else insertIntoList(listPath, makeLeaf(kind));
            return;
        }

        const colActionBtn = e.target.closest('[data-fs-column-action]');
        const colItem = e.target.closest('[data-fs-column-index]');
        if (colActionBtn && colItem) {
            const ref = getSelectedRef();
            if (!ref || ref.node?.type !== 'columns') return;
            const cols = Array.isArray(ref.node?.data?.columns) ? ref.node.data.columns : [];
            const idx = Number(colItem.getAttribute('data-fs-column-index'));
            const action = colActionBtn.getAttribute('data-fs-column-action');
            if (!Number.isInteger(idx) || !cols[idx]) return;
            if (action === 'delete' && cols.length > 2) {
                cols.splice(idx, 1);
                rebalanceColumns(ref.node);
                syncNodesToBuilder();
                return;
            }
            if (action === 'up' && idx > 0) {
                [cols[idx - 1], cols[idx]] = [cols[idx], cols[idx - 1]];
                syncNodesToBuilder();
                return;
            }
            if (action === 'down' && idx < cols.length - 1) {
                [cols[idx + 1], cols[idx]] = [cols[idx], cols[idx + 1]];
                syncNodesToBuilder();
                return;
            }
        }

        if (e.target.closest('[data-fs-columns-add]')) {
            const ref = getSelectedRef();
            if (!ref || ref.node?.type !== 'columns') return;
            const cols = Array.isArray(ref.node?.data?.columns) ? ref.node.data.columns : [];
            if (cols.length >= 4) return;
            cols.push({ id: makeId('col'), span: 3, children: [] });
            rebalanceColumns(ref.node);
            syncNodesToBuilder();
            return;
        }
        if (e.target.closest('[data-fs-columns-rebalance]')) {
            const ref = getSelectedRef();
            if (!ref || ref.node?.type !== 'columns') return;
            rebalanceColumns(ref.node);
            syncNodesToBuilder();
            return;
        }
    });

    refs.layoutPanel?.addEventListener('input', (e) => {
        const spanInput = e.target.closest('[data-fs-column-span]');
        if (!spanInput) return;
        const ref = getSelectedRef();
        if (!ref || ref.node?.type !== 'columns') return;
        const cols = Array.isArray(ref.node?.data?.columns) ? ref.node.data.columns : [];
        const idx = Number(spanInput.getAttribute('data-fs-column-span'));
        if (!Number.isInteger(idx) || !cols[idx]) return;
        cols[idx].span = Math.max(1, Math.min(12, Number(spanInput.value || 1)));
        syncNodesToBuilder();
    });

    refs.inspector?.addEventListener('click', (e) => {
        const mediaBtn = e.target.closest('[data-fs-media-pick]');
        if (!mediaBtn) return;
        const ref = getSelectedRef();
        if (!ref || !isLeafNode(ref.node)) return;
        const mode = mediaBtn.getAttribute('data-fs-media-pick');
        if (!bridge.openMediaPicker) {
            dialogs.alert('Медиатека недоступна.');
            return;
        }
        if (mode === 'image' && ref.node.type === 'image') {
            bridge.openMediaPicker({ accept: 'image', multiple: false, title: 'Выбор изображения', subtitle: 'Выберите изображение из Assets' })
                .then((asset) => {
                    if (!asset?.public_url) return;
                    ref.node.data.src = asset.public_url;
                    if (!String(ref.node.data.alt || '').trim()) ref.node.data.alt = asset.alt || asset.title || '';
                    if (!String(ref.node.data.caption || '').trim() && asset.caption) ref.node.data.caption = asset.caption;
                    syncNodesToBuilder();
                })
                .catch(() => {});
            return;
        }
        if (mode === 'gallery' && ref.node.type === 'gallery') {
            bridge.openMediaPicker({ accept: 'image', multiple: true, title: 'Галерея', subtitle: 'Выберите изображения из Assets' })
                .then((assets) => {
                    const selected = Array.isArray(assets) ? assets : (assets ? [assets] : []);
                    if (selected.length === 0) return;
                    const current = Array.isArray(ref.node.data.items) ? ref.node.data.items : [];
                    ref.node.data.items = current.concat(selected.filter((a) => a?.public_url).map((a) => ({ src: a.public_url, alt: a.alt || a.title || '' })));
                    syncNodesToBuilder();
                })
                .catch(() => {});
            return;
        }
    });

    refs.seoPanel?.addEventListener('click', (e) => {
        if (e.target.closest('[data-fs-refresh-seo-preview]')) {
            bridge.updateSeoPreview?.(getCurrentLocale());
            renderSeoPanel();
        }
    });

    refs.publishPanel?.addEventListener('click', (e) => {
        if (e.target.closest('[data-page-fullscreen-save-inline]')) {
            submitPageForm(false);
            return;
        }
        if (e.target.closest('[data-page-fullscreen-save-publish-inline]')) {
            submitPageForm(true);
        }
    });

    form.addEventListener('testocms:page-fullscreen-open-request', (e) => {
        open(e?.detail?.locale || bridge.getActiveLocale?.());
    });
    form.addEventListener('testocms:locale-changed', () => {
        if (!state.open) return;
        const locale = bridge.getActiveLocale?.();
        if (locale) setLocale(locale);
    });

    form.addEventListener('input', (e) => {
        if (!state.open) return;
        if (e.target.matches('[data-page-title], [data-page-slug]')) {
            refreshTopMeta();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && state.open) {
            e.preventDefault();
            close();
            return;
        }
        if ((e.key === 'F' || e.key === 'f') && e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey) {
            const active = document.activeElement;
            if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable)) return;
            e.preventDefault();
            if (state.open) {
                close();
            } else {
                open(bridge.getActiveLocale?.());
            }
        }
    });

    refs.localeTabs?.querySelectorAll('[data-page-fullscreen-locale]').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-page-fullscreen-locale') === getCurrentLocale()));
    renderElements();
    renderPresets();
    setLeftTab(state.leftTab);
    setRightTab(state.rightTab);
    setDevice(state.device);
    setCenterMode(state.centerMode);
    window.requestAnimationFrame(() => {
        if (!state.open && shouldAutoOpenFullscreen()) {
            open(bridge.getActiveLocale?.());
        }
    });
    window.addEventListener('resize', () => {
        if (!state.open) return;
        recomputeStageOverlayRects();
    });
})();
