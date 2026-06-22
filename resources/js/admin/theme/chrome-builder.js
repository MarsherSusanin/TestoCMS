(() => {
    const shared = window.TestoCmsEditorShared || {};
    const boot = typeof shared.readBootPayload === 'function'
        ? shared.readBootPayload('testocms-theme-editor-boot')
        : {};
    const dialogs = shared.dialogService || window;

    const form = document.getElementById('site-chrome-form');
    const payloadInput = document.getElementById('chrome-payload-input');
    if (!form || !payloadInput) return;

    const supportedLocales = Array.isArray(boot.supportedLocales) ? boot.supportedLocales : ['ru', 'en'];
    const savedChrome = boot.savedChrome || {};
    const chromeLinkTargets = Array.isArray(boot.chromeLinkTargets) ? boot.chromeLinkTargets : [];
    const initialChrome = (() => {
        if (boot.initialChrome && typeof boot.initialChrome === 'object') return boot.initialChrome;
        try {
            const parsed = JSON.parse(payloadInput.value || '{}');
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (_) {
            return {};
        }
    })();

    const clone = (value) => {
        if (typeof window.structuredClone === 'function') return window.structuredClone(value);
        return JSON.parse(JSON.stringify(value ?? {}));
    };
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const getPath = (obj, path) => String(path || '').split('.').reduce((acc, key) => (acc && typeof acc === 'object') ? acc[key] : undefined, obj);
    const setPath = (obj, path, value) => {
        const parts = String(path || '').split('.');
        let cursor = obj;
        parts.forEach((part, idx) => {
            if (idx === parts.length - 1) {
                cursor[part] = value;
                return;
            }
            if (!cursor[part] || typeof cursor[part] !== 'object') {
                cursor[part] = {};
            }
            cursor = cursor[part];
        });
    };
    const ensureArrayPath = (obj, path) => {
        const current = getPath(obj, path);
        if (Array.isArray(current)) return current;
        setPath(obj, path, []);
        return getPath(obj, path);
    };
    const listCaps = {
        'header.nav_items': 8,
        'header.cta_buttons': 2,
        'footer.links': 12,
        'footer.social_links': 6,
        'footer.legal_links': 6,
    };
    const listCap = (path) => /^header\.nav_items\.\d+\.children$/.test(String(path || '')) ? 6 : (listCaps[path] || 99);
    const listHasStyle = (path) => path === 'header.cta_buttons';
    const listSupportsTargetPicker = (path) => path !== 'footer.social_links';
    const chromeLinkTargetMap = new Map(
        chromeLinkTargets
            .filter((item) => item && typeof item === 'object' && item.key)
            .map((item) => [String(item.key), item])
    );
    const targetKeyForItem = (item) => {
        const target = (item && typeof item.link_target === 'object' && item.link_target) ? item.link_target : null;
        const type = String(target?.type || '').toLowerCase();
        const id = Number(target?.id || 0);
        return (type && Number.isFinite(id) && id > 0) ? `${type}:${id}` : '';
    };
    const targetStatusLabel = (target) => {
        const type = String(target?.type || '');
        const status = String(target?.status || '');
        if (!status) return '';
        if (type === 'category') return status === 'active' ? 'активна' : 'неактивна';
        if (status === 'published') return 'опубликовано';
        if (status === 'draft') return 'черновик';
        if (status === 'scheduled') return 'запланировано';
        if (status === 'archived') return 'архив';
        return status;
    };
    const renderTargetOptions = (selectedKey) => {
        const groups = { page: [], post: [], category: [] };
        chromeLinkTargetMap.forEach((target) => {
            const type = String(target?.type || '');
            if (!Object.prototype.hasOwnProperty.call(groups, type)) return;
            groups[type].push(target);
        });
        const typeLabels = {
            page: 'Страницы',
            post: 'Посты',
            category: 'Категории',
        };

        return `
            <option value="">Ручной URL</option>
            ${Object.entries(groups)
                .filter(([, list]) => list.length > 0)
                .map(([type, list]) => `
                    <optgroup label="${escapeHtml(typeLabels[type] || type)}">
                        ${list.map((target) => {
                            const key = String(target.key || '');
                            const selected = key === selectedKey ? ' selected' : '';
                            const statusText = targetStatusLabel(target);
                            const suffix = statusText ? ` · ${statusText}` : '';
                            return `<option value="${escapeHtml(key)}"${selected}>${escapeHtml(String(target.label || key) + suffix)}</option>`;
                        }).join('')}
                    </optgroup>
                `).join('')}
        `;
    };
    const ensureLabelTranslations = (item) => {
        if (!item || typeof item !== 'object') return {};
        if (!item.label_translations || typeof item.label_translations !== 'object' || Array.isArray(item.label_translations)) {
            item.label_translations = {};
        }
        supportedLocales.forEach((locale) => {
            if (typeof item.label_translations[locale] !== 'string') item.label_translations[locale] = '';
        });
        return item.label_translations;
    };
    const makeBaseLinkItem = () => ({
        id: `item_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 6)}`,
        enabled: true,
        url: '',
        new_tab: false,
        nofollow: false,
        link_target: null,
        label_translations: Object.fromEntries(supportedLocales.map((locale) => [locale, ''])),
    });
    const newListItem = (path) => {
        if (path === 'header.nav_items') {
            return { ...makeBaseLinkItem(), children: [] };
        }
        if (/^header\.nav_items\.\d+\.children$/.test(String(path || ''))) {
            return makeBaseLinkItem();
        }
        const item = makeBaseLinkItem();
        if (listHasStyle(path)) item.style = 'primary';
        return item;
    };
    const labelForLocale = (labels, locale) => {
        const dict = (labels && typeof labels === 'object') ? labels : {};
        if (typeof dict[locale] === 'string' && dict[locale].trim() !== '') return dict[locale].trim();
        for (const code of supportedLocales) {
            if (typeof dict[code] === 'string' && dict[code].trim() !== '') return dict[code].trim();
        }
        return '';
    };
    const ensureHeaderState = () => {
        const logo = getPath(state, 'header.logo');
        if (!logo || typeof logo !== 'object' || Array.isArray(logo)) {
            setPath(state, 'header.logo', { src: '', alt: '' });
        }
        if (typeof getPath(state, 'header.logo.src') !== 'string') setPath(state, 'header.logo.src', '');
        if (typeof getPath(state, 'header.logo.alt') !== 'string') setPath(state, 'header.logo.alt', '');
        const menuPosition = String(getPath(state, 'header.menu_position') || 'right');
        if (!['left', 'center', 'right'].includes(menuPosition)) {
            setPath(state, 'header.menu_position', 'right');
        }
        const navItems = ensureArrayPath(state, 'header.nav_items');
        navItems.forEach((item) => {
            ensureLabelTranslations(item);
            if (!Array.isArray(item.children)) item.children = [];
            item.children.forEach((child) => ensureLabelTranslations(child));
        });
    };

    let state = clone(initialChrome);

    const syncPayload = () => {
        payloadInput.value = JSON.stringify(state);
    };

    const applyScalarInputsFromState = () => {
        form.querySelectorAll('[data-chrome-input]').forEach((input) => {
            const path = input.getAttribute('data-chrome-input');
            const value = getPath(state, path);
            if (input instanceof HTMLInputElement && input.type === 'checkbox') {
                input.checked = !!value;
            } else if (input instanceof HTMLInputElement && input.type === 'number') {
                input.value = value === undefined || value === null ? '' : String(value);
            } else {
                input.value = value === undefined || value === null ? '' : String(value);
            }
        });
    };

    const renderItemForm = (path, index, item, options = {}) => {
        const selectedTargetKey = targetKeyForItem(item);
        const selectedTarget = selectedTargetKey ? (chromeLinkTargetMap.get(selectedTargetKey) || null) : null;
        const labels = ensureLabelTranslations(item);
        const title = labelForLocale(labels, 'ru') || labelForLocale(labels, supportedLocales[0] || 'ru') || item?.id || 'Элемент';
        const localeInputs = supportedLocales.map((locale) => `
            <div class="chrome-kv">
                <label>Подпись (${escapeHtml(locale.toUpperCase())})</label>
                <input type="text" value="${escapeHtml(labels[locale] || '')}" data-list-field="label_translations.${escapeHtml(locale)}" data-list-path="${escapeHtml(path)}" data-list-index="${index}">
            </div>
        `).join('');
        const targetPickerField = listSupportsTargetPicker(path)
            ? `<div class="chrome-item-row">
                    <div class="chrome-kv" style="grid-column: span 2;">
                        <label>Связать с существующей страницей / постом / категорией</label>
                        <select data-list-target-select data-list-path="${escapeHtml(path)}" data-list-index="${index}">
                            ${renderTargetOptions(selectedTargetKey)}
                        </select>
                        <div class="muted" style="font-size:12px; margin-top:4px;">
                            ${selectedTarget
                                ? `Источник: ${escapeHtml(String(selectedTarget.label || selectedTargetKey))}${targetStatusLabel(selectedTarget) ? ` · ${escapeHtml(targetStatusLabel(selectedTarget))}` : ''}${selectedTarget.preview_path ? ` · ${escapeHtml(String(selectedTarget.preview_path))}` : ''}`
                                : 'Можно выбрать существующую сущность для автозаполнения URL и локализованных подписей.'}
                        </div>
                   </div>
               </div>`
            : '';
        const styleField = listHasStyle(path)
            ? `<div class="chrome-kv">
                    <label>Стиль</label>
                    <select data-list-field="style" data-list-path="${escapeHtml(path)}" data-list-index="${index}">
                        ${['primary', 'secondary', 'ghost'].map((opt) => `<option value="${opt}"${String(item?.style || 'primary') === opt ? ' selected' : ''}>${opt}</option>`).join('')}
                    </select>
               </div>`
            : '';
        const actionPrefix = options.child ? 'Подпункт' : 'Элемент';
        const urlHint = options.hasChildren
            ? 'URL родителя игнорируется, если есть вложенные пункты.'
            : (selectedTarget ? 'Используется как fallback, если связанная сущность недоступна.' : '');

        return `
            <div class="chrome-item-card ${options.child ? 'chrome-item-card-child' : ''}">
                <div class="chrome-item-actions">
                    <strong>${escapeHtml(title)}</strong>
                    <div class="chrome-item-tools">
                        <button type="button" class="btn btn-small" data-list-action="up" data-list-path="${escapeHtml(path)}" data-list-index="${index}" ${index === 0 ? 'disabled' : ''}>↑</button>
                        <button type="button" class="btn btn-small" data-list-action="down" data-list-path="${escapeHtml(path)}" data-list-index="${index}" ${index >= (ensureArrayPath(state, path).length - 1) ? 'disabled' : ''}>↓</button>
                        <button type="button" class="btn btn-small btn-danger" data-list-action="remove" data-list-path="${escapeHtml(path)}" data-list-index="${index}">Удалить</button>
                    </div>
                </div>
                <div class="chrome-item-row cols-3">
                    <div class="chrome-kv">
                        <label>ID</label>
                        <input type="text" value="${escapeHtml(item?.id || '')}" data-list-field="id" data-list-path="${escapeHtml(path)}" data-list-index="${index}">
                    </div>
                    ${localeInputs}
                </div>
                ${targetPickerField}
                <div class="chrome-item-row ${listHasStyle(path) ? 'cols-3' : ''}">
                    <div class="chrome-kv" style="grid-column: span 2;">
                        <label>${actionPrefix} URL</label>
                        <input type="text" value="${escapeHtml(item?.url || '')}" placeholder="/{locale}/blog или https://..." data-list-field="url" data-list-path="${escapeHtml(path)}" data-list-index="${index}">
                        ${urlHint ? `<div class="muted" style="font-size:12px; margin-top:4px;">${escapeHtml(urlHint)}</div>` : ''}
                    </div>
                    ${styleField}
                </div>
                <div class="chrome-check-grid">
                    <label class="checkbox"><input type="checkbox" ${item?.enabled ? 'checked' : ''} data-list-field="enabled" data-list-path="${escapeHtml(path)}" data-list-index="${index}"> Включено</label>
                    <label class="checkbox"><input type="checkbox" ${item?.new_tab ? 'checked' : ''} data-list-field="new_tab" data-list-path="${escapeHtml(path)}" data-list-index="${index}"> Новая вкладка</label>
                    <label class="checkbox"><input type="checkbox" ${item?.nofollow ? 'checked' : ''} data-list-field="nofollow" data-list-path="${escapeHtml(path)}" data-list-index="${index}"> Nofollow</label>
                    <div class="muted" style="font-size:12px;">${ensureArrayPath(state, path).length}/${listCap(path)}</div>
                </div>
            </div>
        `;
    };

    const renderGenericList = (path) => {
        const container = form.querySelector(`[data-chrome-list="${path}"]`);
        if (!container) return;
        const items = ensureArrayPath(state, path);
        if (!Array.isArray(items) || items.length === 0) {
            container.innerHTML = '<div class="muted" style="font-size:13px;">Список пуст. Нажмите «Добавить».</div>';
            return;
        }

        container.innerHTML = items.map((item, index) => renderItemForm(path, index, item)).join('');
    };

    const renderHeaderNavList = () => {
        const path = 'header.nav_items';
        const container = form.querySelector('[data-chrome-list="header.nav_items"]');
        if (!container) return;
        const items = ensureArrayPath(state, path);
        if (!Array.isArray(items) || items.length === 0) {
            container.innerHTML = '<div class="muted" style="font-size:13px;">Список пуст. Нажмите «Добавить пункт».</div>';
            return;
        }

        container.innerHTML = items.map((item, index) => {
            ensureLabelTranslations(item);
            if (!Array.isArray(item.children)) item.children = [];
            item.children.forEach((child) => ensureLabelTranslations(child));
            const childPath = `${path}.${index}.children`;
            const children = ensureArrayPath(state, childPath);
            return `
                <div class="chrome-list-shell">
                    <div class="chrome-list-header">
                        <h3>Пункт #${index + 1}</h3>
                        <button type="button" class="btn btn-small" data-nav-child-add="${index}" ${children.length >= listCap(childPath) ? 'disabled' : ''}>Добавить подпункт</button>
                    </div>
                    <div class="chrome-list-items">
                        ${renderItemForm(path, index, item, { hasChildren: children.length > 0 })}
                        <div class="chrome-child-shell">
                            <div class="muted" style="font-size:12px;">Вложенность: 1 уровень. Если у пункта есть children, он становится dropdown/disclosure и сам не ведёт по URL.</div>
                            <div class="chrome-child-list">
                                ${children.length > 0
                                    ? children.map((child, childIndex) => renderItemForm(childPath, childIndex, child, { child: true })).join('')
                                    : '<div class="muted" style="font-size:13px;">Подпунктов пока нет.</div>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderAllLists = () => {
        renderHeaderNavList();
        ['header.cta_buttons', 'footer.links', 'footer.social_links', 'footer.legal_links'].forEach(renderGenericList);
    };

    const renderPreview = () => {
        const preview = document.getElementById('chrome-builder-preview');
        if (!preview) return;

        const header = state?.header || {};
        const footer = state?.footer || {};
        const search = state?.search || {};
        const previewLocale = supportedLocales[0] || 'ru';
        const menuPosition = ['left', 'center', 'right'].includes(String(header.menu_position || ''))
            ? String(header.menu_position)
            : 'right';

        const topbar = preview.querySelector('[data-chrome-preview-topbar]');
        const headerNav = preview.querySelector('[data-chrome-preview-header-nav]');
        const footerLinks = preview.querySelector('[data-chrome-preview-footer-links]');
        const searchBox = preview.querySelector('[data-chrome-preview-search]');
        const footerTagline = preview.querySelector('[data-chrome-preview-footer-tagline]');
        const headerTagline = preview.querySelector('[data-chrome-preview-tagline]');
        const footerBrand = preview.querySelector('[data-chrome-preview-footer-brand]');
        const brandMark = preview.querySelector('[data-chrome-preview-brand-mark]');

        const navItems = Array.isArray(header.nav_items) ? header.nav_items.filter((item) => item && item.enabled) : [];
        const ctaItems = Array.isArray(header.cta_buttons) ? header.cta_buttons.filter((item) => item && item.enabled) : [];
        if (headerNav) {
            headerNav.className = `chrome-preview-nav pos-${menuPosition}`;
            headerNav.innerHTML = [
                ...navItems.slice(0, 5).map((item) => {
                    const label = labelForLocale(item.label_translations, previewLocale) || 'Ссылка';
                    const childCount = Array.isArray(item.children) ? item.children.filter((child) => child && child.enabled).length : 0;
                    const childPreview = childCount > 0
                        ? (Array.isArray(item.children) ? item.children.filter((child) => child && child.enabled).slice(0, 2).map((child) => `<span class="chrome-preview-pill">${escapeHtml(labelForLocale(child.label_translations, previewLocale) || 'Подпункт')}</span>`).join('') : '')
                        : '';
                    return `<span class="chrome-preview-pill ${childCount > 0 ? 'has-children' : ''}">${escapeHtml(label)}</span>${childPreview}`;
                }),
                ...ctaItems.slice(0, 2).map((item) => `<span class="chrome-preview-pill ${escapeHtml(item.style || 'primary')}">${escapeHtml(labelForLocale(item.label_translations, previewLocale) || 'Кнопка')}</span>`),
            ].join('');
            headerNav.style.display = header.enabled === false ? 'none' : 'flex';
        }

        if (topbar) {
            topbar.style.display = header.enabled === false ? 'none' : 'flex';
        }

        if (brandMark) {
            const logoSrc = String(header.logo?.src || '').trim();
            if (logoSrc !== '') {
                brandMark.classList.add('has-image');
                brandMark.innerHTML = `<img src="${escapeHtml(logoSrc)}" alt="${escapeHtml(String(header.logo?.alt || 'Logo'))}">`;
            } else {
                brandMark.classList.remove('has-image');
                brandMark.innerHTML = '';
            }
        }

        const canShowSearch = search.enabled !== false
            && header.show_search !== false
            && ['header', 'both'].includes(String(header.search_placement || 'header'));
        if (searchBox) {
            searchBox.style.display = canShowSearch ? 'block' : 'none';
            searchBox.textContent = String(search.placeholder_translations?.[previewLocale] || 'Поиск по сайту');
        }

        if (footerTagline) {
            const text = String(footer.tagline_translations?.[previewLocale] || '');
            footerTagline.textContent = text || 'SEO-first CMS на Laravel';
            footerTagline.style.display = footer.show_tagline === false ? 'none' : 'block';
        }
        if (headerTagline) {
            const text = String(footer.tagline_translations?.[previewLocale] || '');
            headerTagline.textContent = text || 'SEO-first CMS на Laravel';
            headerTagline.style.display = header.show_brand_subtitle === false ? 'none' : 'block';
        }
        if (footerBrand) {
            footerBrand.style.display = footer.show_brand === false ? 'none' : 'inline';
        }
        if (footerLinks) {
            const list = Array.isArray(footer.links) ? footer.links.filter((item) => item && item.enabled) : [];
            footerLinks.innerHTML = list.slice(0, 8).map((item) => `<span class="chrome-preview-pill">${escapeHtml(labelForLocale(item.label_translations, previewLocale) || 'Ссылка')}</span>`).join('');
            footerLinks.style.display = footer.enabled === false ? 'none' : 'flex';
        }
    };

    const rerender = () => {
        ensureHeaderState();
        syncPayload();
        applyScalarInputsFromState();
        renderAllLists();
        renderPreview();
    };

    const updateListField = (path, index, field, rawValue, inputType = 'text') => {
        const items = ensureArrayPath(state, path);
        const item = items[index];
        if (!item || typeof item !== 'object') return;
        const value = inputType === 'checkbox' ? !!rawValue : rawValue;
        if (field.includes('.')) {
            setPath(item, field, value);
        } else {
            item[field] = value;
        }
        syncPayload();
        renderPreview();
    };

    const applySelectedTarget = (path, index, selectedKey) => {
        const items = ensureArrayPath(state, path);
        const item = items[index];
        if (!item || typeof item !== 'object') return;

        if (selectedKey === '') {
            delete item.link_target;
            rerender();
            return;
        }

        const target = chromeLinkTargetMap.get(selectedKey);
        if (!target) {
            dialogs.alert('Выбранная сущность не найдена в каталоге.');
            return;
        }

        item.link_target = {
            type: String(target.type || ''),
            id: Number(target.entity_id || 0),
        };

        const urlTemplate = String(target.url_template || '').trim();
        if (urlTemplate !== '') {
            item.url = urlTemplate;
        }

        const labels = ensureLabelTranslations(item);
        const targetTitles = (target && typeof target.titles === 'object') ? target.titles : {};
        supportedLocales.forEach((locale) => {
            const current = String(labels[locale] || '').trim();
            const suggested = String(targetTitles[locale] || '').trim();
            if (current === '' && suggested !== '') {
                labels[locale] = suggested;
            }
        });

        rerender();
    };

    form.addEventListener('click', (e) => {
        const tabBtn = e.target.closest('[data-chrome-tab]');
        if (tabBtn) {
            const key = tabBtn.getAttribute('data-chrome-tab');
            form.querySelectorAll('[data-chrome-tab]').forEach((btn) => btn.classList.toggle('active', btn === tabBtn));
            form.querySelectorAll('[data-chrome-panel]').forEach((panel) => {
                panel.classList.toggle('active', panel.getAttribute('data-chrome-panel') === key);
            });
            return;
        }

        const addBtn = e.target.closest('[data-chrome-add]');
        if (addBtn) {
            const path = addBtn.getAttribute('data-chrome-add');
            const items = ensureArrayPath(state, path);
            const cap = listCap(path);
            if (items.length >= cap) {
                dialogs.alert(`Лимит элементов для ${path}: ${cap}`);
                return;
            }
            items.push(newListItem(path));
            rerender();
            return;
        }

        const childAddBtn = e.target.closest('[data-nav-child-add]');
        if (childAddBtn) {
            const parentIndex = Number(childAddBtn.getAttribute('data-nav-child-add'));
            if (!Number.isInteger(parentIndex)) return;
            const path = `header.nav_items.${parentIndex}.children`;
            const items = ensureArrayPath(state, path);
            if (items.length >= listCap(path)) {
                dialogs.alert(`Лимит подпунктов: ${listCap(path)}`);
                return;
            }
            items.push(newListItem(path));
            rerender();
            return;
        }

        const logoPickBtn = e.target.closest('[data-chrome-logo-pick]');
        if (logoPickBtn) {
            if (!window.TestoCmsMediaPicker?.open) {
                dialogs.alert('Media picker недоступен.');
                return;
            }
            window.TestoCmsMediaPicker.open({
                accept: 'image',
                multiple: false,
                title: 'Выбор логотипа',
                subtitle: 'Выберите изображение из Assets для шапки сайта',
            }).then((asset) => {
                if (!asset?.public_url) return;
                setPath(state, 'header.logo.src', asset.public_url);
                if (String(getPath(state, 'header.logo.alt') || '').trim() === '') {
                    setPath(state, 'header.logo.alt', asset.alt || asset.title || 'Logo');
                }
                rerender();
            }).catch(() => {});
            return;
        }

        const logoClearBtn = e.target.closest('[data-chrome-logo-clear]');
        if (logoClearBtn) {
            setPath(state, 'header.logo.src', '');
            rerender();
            return;
        }

        const actionBtn = e.target.closest('[data-list-action]');
        if (actionBtn) {
            const path = actionBtn.getAttribute('data-list-path');
            const action = actionBtn.getAttribute('data-list-action');
            const index = Number(actionBtn.getAttribute('data-list-index'));
            const items = ensureArrayPath(state, path);
            if (!Array.isArray(items) || !Number.isInteger(index) || index < 0 || index >= items.length) return;

            if (action === 'remove') {
                items.splice(index, 1);
            } else if (action === 'up' && index > 0) {
                [items[index - 1], items[index]] = [items[index], items[index - 1]];
            } else if (action === 'down' && index < items.length - 1) {
                [items[index + 1], items[index]] = [items[index], items[index + 1]];
            }
            rerender();
        }
    });

    form.addEventListener('input', (e) => {
        const scalarInput = e.target.closest('[data-chrome-input]');
        if (scalarInput) {
            const path = scalarInput.getAttribute('data-chrome-input');
            let value;
            if (scalarInput instanceof HTMLInputElement && scalarInput.type === 'checkbox') {
                value = scalarInput.checked;
            } else if (scalarInput instanceof HTMLInputElement && scalarInput.type === 'number') {
                value = scalarInput.value === '' ? null : Number(scalarInput.value);
            } else {
                value = scalarInput.value;
            }
            setPath(state, path, value);
            syncPayload();
            renderPreview();
            return;
        }

        const listInput = e.target.closest('[data-list-field]');
        if (listInput) {
            const path = listInput.getAttribute('data-list-path');
            const field = listInput.getAttribute('data-list-field');
            const index = Number(listInput.getAttribute('data-list-index'));
            const isCheckbox = listInput instanceof HTMLInputElement && listInput.type === 'checkbox';
            const raw = isCheckbox ? listInput.checked : listInput.value;
            updateListField(path, index, field, raw, isCheckbox ? 'checkbox' : 'text');
        }
    });

    form.addEventListener('change', (e) => {
        const targetSelect = e.target.closest('[data-list-target-select]');
        if (!targetSelect) return;
        const path = targetSelect.getAttribute('data-list-path');
        const index = Number(targetSelect.getAttribute('data-list-index'));
        applySelectedTarget(path, index, String(targetSelect.value || '').trim());
    });

    const resetBtn = document.getElementById('chrome-builder-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            state = clone(savedChrome);
            rerender();
        });
    }

    form.addEventListener('submit', () => {
        syncPayload();
    });

    [
        'header.nav_items',
        'header.cta_buttons',
        'footer.links',
        'footer.social_links',
        'footer.legal_links',
    ].forEach((path) => ensureArrayPath(state, path));
    [
        'footer.tagline_translations',
        'search.placeholder_translations',
    ].forEach((path) => {
        const current = getPath(state, path);
        if (!current || typeof current !== 'object' || Array.isArray(current)) setPath(state, path, {});
        supportedLocales.forEach((locale) => {
            const value = getPath(state, `${path}.${locale}`);
            if (typeof value !== 'string') setPath(state, `${path}.${locale}`, '');
        });
    });

    ensureHeaderState();
    rerender();
})();
