(() => {
    const shared = window.TestoCmsEditorShared;
    const form = document.getElementById('page-form');
    if (!form || !shared) return;
    const boot = shared.readBootPayload('testocms-page-editor-boot');
    const supportedLocales = Array.isArray(boot.supportedLocales) ? boot.supportedLocales : [];
    const templateSourceName = String(boot.templateSourceName || '');
    const allowedTypes = Array.isArray(boot.allowedBlockTypes) ? boot.allowedBlockTypes : [];
    const moduleWidgetCatalog = Array.isArray(boot.moduleWidgetCatalog) ? boot.moduleWidgetCatalog : [];
    const isEditMode = !!boot.isEditMode;
    const uiStorage = shared.createUiStorage('__testocms_page_ui_probe__');
    const uiRead = (key, fallback = null) => shared.uiRead(uiStorage, key, fallback);
    const uiWrite = (key, value) => shared.uiWrite(uiStorage, key, value);
    const dialogs = shared.dialogService;
    const openModal = shared.openModal;
    const closeModal = shared.closeModal;
            const PAGE_BUILDER_VIEW_KEY = 'testocms:admin:page-builder:view';
            const PAGE_SIDEBAR_KEY = 'testocms:admin:page-builder:sidebar';
            const createTemplateModal = document.querySelector('[data-create-template-modal]');
            const saveTemplateModal = document.querySelector('[data-save-template-modal]');
            const saveTemplateForm = document.querySelector('[data-save-template-form]');
            const typeLabels = {
                heading: 'Заголовок',
                rich_text: 'Текст (HTML)',
                image: 'Изображение',
                video_embed: 'Видео',
                gallery: 'Галерея',
                list: 'Список',
                divider: 'Разделитель',
                cta: 'CTA',
                table: 'Таблица',
                module_widget: 'Виджет модуля',
                custom_code_embed: 'Кастомный код',
                html_embed_restricted: 'Ограниченный embed',
                post_listing: 'Список постов',
                faq: 'FAQ',
            };

            const moduleWidgetFieldOptions = (options) => (Array.isArray(options) ? options : []).map((option) => ({
                value: String(option?.value ?? ''),
                label: String(option?.label ?? option?.value ?? ''),
            }));
            const findWidgetDefinition = (moduleKey, widgetKey) => moduleWidgetCatalog.find((item) => (
                String(item?.module || '') === String(moduleKey || '')
                && String(item?.widget || '') === String(widgetKey || '')
            )) || null;
            const firstWidgetDefinition = () => moduleWidgetCatalog[0] || null;
            const widgetsForModule = (moduleKey) => moduleWidgetCatalog.filter((item) => String(item?.module || '') === String(moduleKey || ''));
            const moduleOptions = () => {
                const seen = new Set();
                return moduleWidgetCatalog.filter((item) => {
                    const key = String(item?.module || '');
                    if (!key || seen.has(key)) return false;
                    seen.add(key);
                    return true;
                }).map((item) => ({
                    value: String(item.module || ''),
                    label: String(item.module_label || item.module || ''),
                }));
            };
            const moduleWidgetDefaultConfig = (definition) => {
                const config = {};
                (Array.isArray(definition?.config_fields) ? definition.config_fields : []).forEach((field) => {
                    if (!field || typeof field !== 'object') return;
                    const name = String(field.name || '').trim();
                    if (!name) return;
                    const type = String(field.type || 'text');
                    config[name] = Object.prototype.hasOwnProperty.call(field, 'default')
                        ? field.default
                        : (type === 'checkbox' ? false : '');
                });
                return config;
            };
            const moduleWidgetSummary = (data) => {
                const moduleKey = String(data?.module || '').trim();
                const widgetKey = String(data?.widget || '').trim();
                if (!moduleKey || !widgetKey) return 'Виджет модуля';
                const definition = findWidgetDefinition(moduleKey, widgetKey);
                const label = String(definition?.label || widgetKey);
                const moduleLabel = String(definition?.module_label || moduleKey);
                return `${moduleLabel} · ${label}`;
            };

            const cyrillicToLatinMap = {
                а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y',
                к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f',
                х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
            };
            const transliterateForSlug = (value) => String(value || '')
                .split('')
                .map((char) => {
                    const lower = char.toLowerCase();
                    return Object.prototype.hasOwnProperty.call(cyrillicToLatinMap, lower)
                        ? cyrillicToLatinMap[lower]
                        : char;
                })
                .join('');
            const slugify = (value) => transliterateForSlug(value)
                .toLowerCase()
                .trim()
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\\s-]/gi, '')
                .replace(/[\\s_]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            const escapeSelectorName = (value) => (window.CSS && typeof window.CSS.escape === 'function')
                ? window.CSS.escape(value)
                : String(value).replace(/["\\\\]/g, '\\\\$&');
            const openMediaPicker = (options) => {
                if (!window.TestoCmsMediaPicker?.open) {
                    dialogs.alert('Медиатека недоступна. Загрузите файл в Assets или вставьте URL вручную.');
                    return Promise.reject(new Error('Media picker unavailable'));
                }
                return window.TestoCmsMediaPicker.open(options);
            };

            const wireLocaleTabs = () => {
                document.querySelectorAll('[data-locale-tab]').forEach((tab) => {
                    tab.addEventListener('click', () => {
                        const locale = tab.getAttribute('data-locale-tab');
                        document.querySelectorAll('[data-locale-tab]').forEach((t) => t.classList.toggle('active', t === tab));
                        document.querySelectorAll('[data-locale-pane]').forEach((pane) => {
                            pane.classList.toggle('active', pane.getAttribute('data-locale-pane') === locale);
                        });
                        try {
                            form.dispatchEvent(new CustomEvent('testocms:locale-changed', { detail: { locale } }));
                        } catch (_) {}
                    });
                });
            };

            const setupComposerSidebar = () => {
                const shell = form.querySelector('[data-composer-shell="page"]');
                const sidebar = form.querySelector('[data-composer-sidebar="page"]');
                if (!shell || !sidebar) return;

                const tabButtons = Array.from(sidebar.querySelectorAll('[data-composer-sidebar-tab]'));
                const sections = Array.from(sidebar.querySelectorAll('[data-composer-sidebar-section]'));
                const toggleButtons = Array.from(document.querySelectorAll('[data-composer-sidebar-toggle]'));
                const seoHost = sidebar.querySelector('[data-page-seo-sidebar-host]');
                const statusSelect = form.querySelector('#page-status');
                const statusBadge = sidebar.querySelector('[data-composer-status-badge]');
                const advancedSummary = sidebar.querySelector('[data-page-advanced-summary]');
                const advancedOpenBtn = sidebar.querySelector('[data-page-advanced-open]');
                const advancedFormatBtn = sidebar.querySelector('[data-page-advanced-format]');
                const seoLocaleMounts = {};

                const getActiveLocale = () => form.querySelector('[data-locale-tab].active')?.getAttribute('data-locale-tab')
                    || form.querySelector('[data-locale-tab]')?.getAttribute('data-locale-tab')
                    || null;

                if (seoHost) {
                    form.querySelectorAll('[data-page-locale-seo-panel]').forEach((panel) => {
                        const locale = panel.getAttribute('data-page-locale-seo-panel');
                        if (!locale) return;
                        const mount = document.createElement('div');
                        mount.setAttribute('data-page-seo-locale-mount', locale);
                        mount.style.display = 'none';
                        seoHost.appendChild(mount);
                        mount.appendChild(panel);
                        panel.classList.add('sidebar-mounted-panel');
                        if (panel.tagName === 'DETAILS') {
                            panel.open = true;
                        }
                        seoLocaleMounts[locale] = mount;
                    });
                }

                const syncSeoLocale = () => {
                    const activeLocale = getActiveLocale();
                    Object.entries(seoLocaleMounts).forEach(([locale, mount]) => {
                        mount.style.display = locale === activeLocale ? 'block' : 'none';
                    });
                };

                const setActiveTab = (tabKey) => {
                    const normalized = sections.some((s) => s.getAttribute('data-composer-sidebar-section') === tabKey)
                        ? tabKey
                        : 'publish';
                    tabButtons.forEach((btn) => {
                        const active = btn.getAttribute('data-composer-sidebar-tab') === normalized;
                        btn.classList.toggle('active', active);
                        btn.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    sections.forEach((section) => {
                        section.classList.toggle('active', section.getAttribute('data-composer-sidebar-section') === normalized);
                    });
                };

                const setCollapsed = (collapsed) => {
                    const shouldCollapse = !!collapsed && window.innerWidth > 1024;
                    shell.classList.toggle('is-sidebar-collapsed', shouldCollapse);
                    toggleButtons.forEach((btn) => btn.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true'));
                    uiWrite(PAGE_SIDEBAR_KEY, shouldCollapse ? 'collapsed' : 'expanded');
                };

                const syncStatusBadge = () => {
                    if (!statusBadge || !statusSelect) return;
                    const value = String(statusSelect.value || 'draft').trim() || 'draft';
                    statusBadge.textContent = value;
                    statusBadge.className = `status-pill status-${value}`;
                };

                const syncAdvancedSummary = () => {
                    if (!advancedSummary) return;
                    const locale = getActiveLocale();
                    if (!locale) {
                        advancedSummary.textContent = 'Выберите локаль.';
                        return;
                    }
                    const blocksJson = form.querySelector(`[data-locale-pane="${locale}"] [data-blocks-json]`);
                    const richHtml = form.querySelector(`[data-locale-pane="${locale}"] [data-rich-html-fallback]`);
                    const parsed = (() => {
                        try { return JSON.parse(blocksJson?.value || '[]'); } catch (_) { return null; }
                    })();
                    const blockCount = Array.isArray(parsed) ? parsed.length : 0;
                    const rawState = (blocksJson?.value || '').trim() !== '' ? 'есть' : 'пусто';
                    const fallbackState = (richHtml?.value || '').trim() !== '' ? 'есть' : 'пусто';
                    advancedSummary.textContent = `Локаль ${String(locale).toUpperCase()}: блоков ${blockCount}, JSON ${rawState}, fallback HTML ${fallbackState}.`;
                };

                if (advancedOpenBtn) {
                    advancedOpenBtn.addEventListener('click', () => {
                        const locale = getActiveLocale();
                        const panel = locale ? form.querySelector(`[data-page-locale-advanced-panel="${locale}"]`) : null;
                        if (!panel) return;
                        const details = panel.querySelector('details');
                        if (details) details.open = true;
                        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }
                if (advancedFormatBtn) {
                    advancedFormatBtn.addEventListener('click', () => {
                        const locale = getActiveLocale();
                        const btn = locale ? form.querySelector(`[data-locale-pane="${locale}"] [data-format-raw-json]`) : null;
                        if (btn) btn.click();
                    });
                }

                tabButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        setActiveTab(btn.getAttribute('data-composer-sidebar-tab'));
                        if (shell.classList.contains('is-sidebar-collapsed') && window.innerWidth > 1024) {
                            setCollapsed(false);
                        }
                    });
                });
                toggleButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        setCollapsed(!shell.classList.contains('is-sidebar-collapsed'));
                    });
                });
                window.addEventListener('resize', () => {
                    if (window.innerWidth <= 1024) {
                        shell.classList.remove('is-sidebar-collapsed');
                        toggleButtons.forEach((btn) => btn.setAttribute('aria-expanded', 'true'));
                    }
                });

                form.addEventListener('testocms:locale-changed', () => {
                    syncSeoLocale();
                    syncAdvancedSummary();
                });
                statusSelect?.addEventListener('change', syncStatusBadge);
                form.addEventListener('input', (e) => {
                    if (e.target.closest('[data-blocks-json], [data-rich-html-fallback]')) {
                        syncAdvancedSummary();
                    }
                });

                syncSeoLocale();
                syncAdvancedSummary();
                syncStatusBadge();
                const firstErrorTab = tabButtons.find((btn) => btn.classList.contains('has-error'))?.getAttribute('data-composer-sidebar-tab');
                setActiveTab(firstErrorTab || 'publish');
                setCollapsed(uiRead(PAGE_SIDEBAR_KEY, 'expanded') === 'collapsed');
            };

            const parseJson = (text) => {
                try {
                    const parsed = JSON.parse(text);
                    return Array.isArray(parsed) ? parsed : null;
                } catch (e) {
                    return null;
                }
            };

            const toGalleryLines = (items) => (Array.isArray(items) ? items : [])
                .map((item) => `${item?.src || ''} | ${item?.alt || ''}`)
                .join('\\n');
            const fromGalleryLines = (text) => String(text || '')
                .split('\\n')
                .map((line) => line.trim())
                .filter(Boolean)
                .map((line) => {
                    const [src, ...rest] = line.split('|');
                    return { src: (src || '').trim(), alt: rest.join('|').trim() };
                });

            const toListLines = (items) => (Array.isArray(items) ? items : []).map((v) => String(v ?? '')).join('\\n');
            const fromListLines = (text) => String(text || '').split('\\n').map((v) => v.trim()).filter(Boolean);

            const toTableLines = (rows) => (Array.isArray(rows) ? rows : [])
                .map((row) => (Array.isArray(row) ? row : []).map((cell) => String(cell ?? '')).join(' | '))
                .join('\\n');
            const fromTableLines = (text) => String(text || '')
                .split('\\n')
                .map((line) => line.trim())
                .filter(Boolean)
                .map((line) => line.split('|').map((cell) => cell.trim()));

            const toFaqText = (items) => (Array.isArray(items) ? items : [])
                .map((item) => `Q: ${item?.question || ''}\\nA: ${String(item?.answer || '').replace(/\\n/g, ' ')}`)
                .join('\\n\\n');
            const fromFaqText = (text) => String(text || '')
                .split(/\\n\\s*\\n/g)
                .map((chunk) => chunk.trim())
                .filter(Boolean)
                .map((chunk) => {
                    const lines = chunk.split('\\n');
                    let question = '';
                    let answer = '';
                    lines.forEach((line) => {
                        if (/^Q:/i.test(line)) question = line.replace(/^Q:/i, '').trim();
                        if (/^A:/i.test(line)) answer = line.replace(/^A:/i, '').trim();
                    });
                    return { question, answer: answer || '<p>Ответ</p>' };
                })
                .filter((item) => item.question || item.answer);

            const defaultBlock = (type) => {
                switch (type) {
                    case 'heading':
                        return { type, data: { level: 2, text: 'Новый заголовок' } };
                    case 'rich_text':
                        return { type, data: { html: '<p>Новый текстовый блок.</p>' } };
                    case 'image':
                        return { type, data: { src: 'https://picsum.photos/1200/700', alt: 'Изображение', caption: '' } };
                    case 'video_embed':
                        return { type, data: { url: 'https://www.youtube.com/embed/dQw4w9WgXcQ' } };
                    case 'gallery':
                        return { type, data: { items: [{ src: 'https://picsum.photos/600/400?1', alt: 'Элемент галереи 1' }, { src: 'https://picsum.photos/600/400?2', alt: 'Элемент галереи 2' }] } };
                    case 'list':
                        return { type, data: { ordered: false, items: ['Пункт 1', 'Пункт 2', 'Пункт 3'] } };
                    case 'divider':
                        return { type, data: {} };
                    case 'cta':
                        return { type, data: { label: 'Открыть раздел', url: '/ru/blog', target_blank: false, nofollow: false } };
                    case 'table':
                        return { type, data: { rows: [['Колонка 1', 'Колонка 2'], ['Значение', 'Значение']] } };
                    case 'module_widget': {
                        const definition = firstWidgetDefinition();
                        if (!definition) {
                            return { type, data: { module: '', widget: '', config: {} } };
                        }
                        return {
                            type,
                            data: {
                                module: String(definition.module || ''),
                                widget: String(definition.widget || ''),
                                config: moduleWidgetDefaultConfig(definition),
                            },
                        };
                    }
                    case 'custom_code_embed':
                        return { type, data: { label: 'Внешний виджет', html: '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" loading="lazy" allowfullscreen></iframe>' } };
                    case 'html_embed_restricted':
                        return { type, data: { html: '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" loading="lazy" allowfullscreen></iframe>' } };
                    case 'post_listing':
                        return { type, data: { category_slug: '', limit: 6 } };
                    case 'faq':
                        return { type, data: { items: [{ question: 'Вопрос?', answer: '<p>Ответ с форматированием.</p>' }] } };
                    default:
                        return { type, data: {} };
                }
            };

            const presetBlocks = {
                hero() {
                    return [
                        { type: 'rich_text', data: { html: `
                            <section class="tc-hero">
                                <p class="tc-kicker">НОВАЯ КОЛЛЕКЦИЯ</p>
                                <h1>Главный оффер страницы</h1>
                                <p class="tc-lead">Короткое описание предложения, преимуществ и следующего шага для пользователя. В стиле лендинга, с акцентом на конверсию.</p>
                                <p><a class="cms-cta" href="/ru/blog">Начать</a></p>
                            </section>
                        `.trim() } },
                        { type: 'image', data: { src: 'https://picsum.photos/1200/700?hero', alt: 'Hero image', caption: '' } },
                    ];
                },
                'text-section'() {
                    return [
                        { type: 'heading', data: { level: 2, text: 'Секция с текстом' } },
                        { type: 'rich_text', data: { html: '<p>Текстовый контент, который можно редактировать в визуальном конструкторе.</p>' } },
                        { type: 'divider', data: {} },
                    ];
                },
                features() {
                    return [
                        { type: 'heading', data: { level: 2, text: 'Почему выбирают нас' } },
                        { type: 'rich_text', data: { html: `
                            <section class="tc-features-grid">
                                <article class="tc-feature"><h3>Быстрый запуск</h3><p>Поднимается в Docker и готов к публикации без внешних сервисов.</p></article>
                                <article class="tc-feature"><h3>SEO-first</h3><p>Весь критичный контент рендерится на сервере и индексируется без JS.</p></article>
                                <article class="tc-feature"><h3>Гибкий редактор</h3><p>Посты и страницы собираются визуально, но остаются контролируемыми в структуре.</p></article>
                            </section>
                        `.trim() } },
                    ];
                },
                pricing() {
                    return [
                        { type: 'heading', data: { level: 2, text: 'Тарифы' } },
                        { type: 'rich_text', data: { html: `
                            <section class="tc-pricing-grid">
                                <article class="tc-price-card">
                                    <h3>Start</h3>
                                    <div class="tc-price">990₽ <small>/ мес</small></div>
                                    <ul><li>1 редактор</li><li>Базовые страницы</li><li>Email поддержка</li></ul>
                                    <p><a class="cms-cta" href="#start">Выбрать</a></p>
                                </article>
                                <article class="tc-price-card featured">
                                    <h3>Growth</h3>
                                    <div class="tc-price">2 990₽ <small>/ мес</small></div>
                                    <ul><li>5 редакторов</li><li>SEO инструменты</li><li>LLM-драфты</li></ul>
                                    <p><a class="cms-cta" href="#growth">Популярный</a></p>
                                </article>
                                <article class="tc-price-card">
                                    <h3>Scale</h3>
                                    <div class="tc-price">6 990₽ <small>/ мес</small></div>
                                    <ul><li>Команда</li><li>Расширенные права</li><li>Приоритетная поддержка</li></ul>
                                    <p><a class="cms-cta" href="#scale">Обсудить</a></p>
                                </article>
                            </section>
                        `.trim() } },
                    ];
                },
                cards() {
                    return [
                        { type: 'heading', data: { level: 2, text: 'Кейсы / Сервисы' } },
                        { type: 'rich_text', data: { html: `
                            <section class="tc-cards-grid">
                                <article class="tc-card"><h3>Корпоративный сайт</h3><p>Маркетинговые страницы с сильным SEO и редакторским workflow.</p><a href="/ru/blog">Подробнее</a></article>
                                <article class="tc-card"><h3>Медиа-портал</h3><p>Категории, блог, мультиязычность и редакция без фронтенд-разработки.</p><a href="/ru/blog">Смотреть</a></article>
                                <article class="tc-card"><h3>Landing factory</h3><p>Сборка лендингов из шаблонных секций в стиле Tilda/Elementor.</p><a href="/ru/blog">Открыть</a></article>
                            </section>
                        `.trim() } },
                    ];
                },
                faq() {
                    return [
                        { type: 'heading', data: { level: 2, text: 'Частые вопросы' } },
                        { type: 'faq', data: { items: [
                            { question: 'Сколько занимает запуск?', answer: '<p>Базовый запуск в Docker занимает несколько минут.</p>' },
                            { question: 'Можно ли редактировать без кода?', answer: '<p>Да, используйте админку и визуальный редактор.</p>' },
                        ] } },
                    ];
                },
                blog() {
                    return [
                        { type: 'heading', data: { level: 1, text: 'Блог компании' } },
                        { type: 'rich_text', data: { html: '<p>Новости, анонсы и статьи. Блок ниже подгружает список публикаций.</p>' } },
                        { type: 'post_listing', data: { category_slug: '', limit: 6 } },
                    ];
                }
            };

            const blockTitle = (block) => {
                const type = block?.type || 'unknown';
                const data = block?.data || {};
                if (type === 'heading') return data.text || 'Заголовок';
                if (type === 'cta') {
                    const label = String(data.label || 'CTA').trim();
                    const url = String(data.url || '#').trim();
                    return `${label}${url ? ` -> ${url}` : ''}`;
                }
                if (type === 'module_widget') return moduleWidgetSummary(data);
                if (type === 'rich_text') return (String(data.html || '').replace(/<[^>]+>/g, '').trim().slice(0, 36) || 'Текст (HTML)');
                if (type === 'image') return data.alt || data.src || 'Изображение';
                if (type === 'faq') return `FAQ (${Array.isArray(data.items) ? data.items.length : 0})`;
                if (type === 'gallery') return `Галерея (${Array.isArray(data.items) ? data.items.length : 0})`;
                return typeLabels[type] || type;
            };

            const normalizeBlock = (block) => {
                const type = String(block?.type || '');
                if (!allowedTypes.includes(type)) return null;
                const data = (block && typeof block.data === 'object' && block.data) ? block.data : {};
                return { type, data };
            };

            const cloneJson = (value) => {
                if (typeof structuredClone === 'function') {
                    try { return structuredClone(value); } catch (_) {}
                }
                return JSON.parse(JSON.stringify(value));
            };

            const isStructuredLayoutNode = (node) => {
                if (!node || typeof node !== 'object') return false;
                const type = String(node.type || '');
                return type === 'section' || type === 'columns';
            };

            const containsStructuredLayout = (nodes) => {
                if (!Array.isArray(nodes)) return false;
                const walk = (list) => {
                    for (const item of list) {
                        if (!item || typeof item !== 'object') continue;
                        if (isStructuredLayoutNode(item)) return true;
                        if (Array.isArray(item.children) && walk(item.children)) return true;
                        const cols = item?.data?.columns;
                        if (Array.isArray(cols)) {
                            for (const col of cols) {
                                if (Array.isArray(col?.children) && walk(col.children)) return true;
                            }
                        }
                    }
                    return false;
                };
                return walk(nodes);
            };

            const createStructuredSection = (children = []) => ({
                type: 'section',
                data: {
                    label: 'Секция',
                    container: 'boxed',
                    padding_y: 'md',
                    background: 'none',
                },
                children: Array.isArray(children) ? children : [],
            });

            const normalizeLayoutNodes = (nodes, options = {}) => {
                const fallbackHtml = String(options.fallbackHtml || '').trim();
                let source = Array.isArray(nodes) ? cloneJson(nodes) : [];
                if (source.length === 0 && fallbackHtml !== '') {
                    source = [{ type: 'rich_text', data: { html: fallbackHtml } }];
                }
                if (containsStructuredLayout(source)) {
                    return source.length > 0 ? source : [createStructuredSection()];
                }
                const normalizedLeafs = source.map(normalizeBlock).filter(Boolean);
                if (normalizedLeafs.length === 0) {
                    return [createStructuredSection()];
                }
                return [createStructuredSection(normalizedLeafs)];
            };

            const walkNodes = (nodes, cb) => {
                if (!Array.isArray(nodes) || typeof cb !== 'function') return;
                nodes.forEach((node) => {
                    cb(node);
                    if (!node || typeof node !== 'object') return;
                    if (Array.isArray(node.children)) {
                        walkNodes(node.children, cb);
                    }
                    const columns = node?.data?.columns;
                    if (Array.isArray(columns)) {
                        columns.forEach((column) => {
                            if (Array.isArray(column?.children)) {
                                walkNodes(column.children, cb);
                            }
                        });
                    }
                });
            };

            const previewRenderBlock = (block) => {
                const type = block.type;
                const data = block.data || {};
                if (type === 'heading') {
                    const level = Math.min(6, Math.max(1, Number(data.level || 2)));
                    return `<h${level}>${escapeHtml(data.text || '')}</h${level}>`;
                }
                if (type === 'rich_text') {
                    return String(data.html || '');
                }
                if (type === 'image') {
                    const src = escapeHtml(data.src || '');
                    if (!src) return '';
                    const alt = escapeHtml(data.alt || '');
                    const caption = data.caption ? `<figcaption>${escapeHtml(data.caption)}</figcaption>` : '';
                    return `<figure><img src="${src}" alt="${alt}" loading="lazy">${caption}</figure>`;
                }
                if (type === 'video_embed') {
                    const url = escapeHtml(data.url || '');
                    return url ? `<div class="cms-video"><iframe src="${url}" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe></div>` : '';
                }
                if (type === 'gallery') {
                    const items = Array.isArray(data.items) ? data.items : [];
                    const images = items.map((item) => `<img src="${escapeHtml(item?.src || '')}" alt="${escapeHtml(item?.alt || '')}" loading="lazy">`).join('');
                    return `<div class="cms-gallery">${images}</div>`;
                }
                if (type === 'list') {
                    const tag = data.ordered ? 'ol' : 'ul';
                    const items = Array.isArray(data.items) ? data.items : [];
                    return `<${tag}>${items.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</${tag}>`;
                }
                if (type === 'divider') {
                    return '<hr class="cms-divider">';
                }
                if (type === 'cta') {
                    const label = escapeHtml(data.label || 'CTA');
                    const url = escapeHtml(data.url || '#');
                    const targetAttr = data.target_blank ? ' target="_blank"' : '';
                    const relParts = [];
                    if (data.target_blank) relParts.push('noopener', 'noreferrer');
                    if (data.nofollow) relParts.push('nofollow');
                    const relAttr = relParts.length ? ` rel="${escapeHtml(Array.from(new Set(relParts)).join(' '))}"` : '';
                    return `<p><a class="cms-cta" href="${url}"${targetAttr}${relAttr}>${label}</a></p>`;
                }
                if (type === 'table') {
                    const rows = Array.isArray(data.rows) ? data.rows : [];
                    return `<table><tbody>${rows.map((row) => `<tr>${(Array.isArray(row) ? row : []).map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
                }
                if (type === 'module_widget') {
                    const summary = moduleWidgetSummary(data);
                    const config = (data.config && typeof data.config === 'object') ? data.config : {};
                    const configEntries = Object.entries(config).filter(([, value]) => String(value ?? '').trim() !== '');
                    return `
                        <div class="cms-module-widget-preview">
                            <strong>${escapeHtml(summary)}</strong>
                            <div class="block-subtle">SSR-виджет активного модуля. На публичной странице загрузит живой фрагмент.</div>
                            ${configEntries.length ? `<div class="block-subtle">${configEntries.map(([key, value]) => `${escapeHtml(key)}: ${escapeHtml(String(value))}`).join(' · ')}</div>` : ''}
                        </div>
                    `;
                }
                if (type === 'custom_code_embed') {
                    const label = String(data.label || '').trim();
                    const labelHtml = label ? `<div class="cms-embed-label">${escapeHtml(label)}</div>` : '';
                    const htmlWithoutScripts = String(data.html || '').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
                    return `<div class="cms-custom-embed">${labelHtml}${htmlWithoutScripts}</div>`;
                }
                if (type === 'html_embed_restricted') {
                    const htmlWithoutScripts = String(data.html || '').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
                    return `<div>${htmlWithoutScripts}</div>`;
                }
                if (type === 'post_listing') {
                    return `<div class="cms-post-listing">Динамический список постов · категория: ${escapeHtml(data.category_slug || 'all')} · лимит: ${escapeHtml(String(data.limit || 10))}</div>`;
                }
                if (type === 'faq') {
                    const items = Array.isArray(data.items) ? data.items : [];
                    return `<section class="cms-faq">${items.map((item) => `<details><summary>${escapeHtml(item?.question || '')}</summary><div>${String(item?.answer || '')}</div></details>`).join('')}</section>`;
                }
                return '';
            };

            const previewRenderNode = (node) => {
                if (!node || typeof node !== 'object') return '';
                const type = String(node.type || '');
                const data = (node && typeof node.data === 'object' && node.data) ? node.data : {};

                if (type === 'section') {
                    const children = Array.isArray(node.children) ? node.children : [];
                    const container = ['boxed', 'wide', 'full'].includes(String(data.container || '')) ? String(data.container) : 'boxed';
                    const padding = ['none', 'sm', 'md', 'lg', 'xl'].includes(String(data.padding_y || '')) ? String(data.padding_y) : 'md';
                    const bg = ['none', 'surface', 'brand-soft', 'custom'].includes(String(data.background || '')) ? String(data.background) : 'none';
                    const style = (bg === 'custom' && String(data.background_color || '').trim())
                        ? ` style="--cms-section-bg:${escapeHtml(String(data.background_color).trim())};"`
                        : '';
                    return `<section class="cms-section cms-section--${escapeHtml(container)} cms-section--py-${escapeHtml(padding)} cms-section--bg-${escapeHtml(bg)}"${style}><div class="cms-container cms-container--${escapeHtml(container)}">${previewRenderNodes(children)}</div></section>`;
                }

                if (type === 'columns') {
                    const cols = Array.isArray(data.columns) ? data.columns : [];
                    const gap = ['sm', 'md', 'lg'].includes(String(data.gap || '')) ? String(data.gap) : 'md';
                    const align = ['start', 'center', 'end', 'stretch'].includes(String(data.align_y || '')) ? String(data.align_y) : 'stretch';
                    const colsHtml = cols.map((col) => {
                        const span = Math.min(12, Math.max(1, Number(col?.span || 6)));
                        const children = Array.isArray(col?.children) ? col.children : [];
                        return `<div class="cms-col" style="grid-column: span ${span};">${previewRenderNodes(children)}</div>`;
                    }).join('');
                    return `<div class="cms-columns cms-columns--gap-${escapeHtml(gap)} cms-columns--align-${escapeHtml(align)}">${colsHtml}</div>`;
                }

                return previewRenderBlock({ type, data });
            };

            const previewRenderNodes = (nodes) => (Array.isArray(nodes) ? nodes : [])
                .map((node) => previewRenderNode(node))
                .filter(Boolean)
                .join('\n');

            const buildField = (label, name, value, options = {}) => {
                const type = options.type || 'text';
                const placeholder = options.placeholder ? ` placeholder="${escapeHtml(options.placeholder)}"` : '';
                const rows = options.rows || 3;
                const hint = options.hint ? `<div class="block-subtle">${escapeHtml(options.hint)}</div>` : '';
                const attrs = ` data-block-input="${escapeHtml(name)}"`;
                let control = '';

                if (type === 'textarea') {
                    control = `<textarea rows="${rows}"${attrs}${placeholder}>${escapeHtml(value || '')}</textarea>`;
                } else if (type === 'select') {
                    const optionsHtml = (options.options || []).map((opt) => {
                        const selected = String(opt.value) === String(value) ? ' selected' : '';
                        return `<option value="${escapeHtml(opt.value)}"${selected}>${escapeHtml(opt.label)}</option>`;
                    }).join('');
                    control = `<select${attrs}>${optionsHtml}</select>`;
                } else if (type === 'checkbox') {
                    const checked = value ? ' checked' : '';
                    control = `<label class="checkbox"><input type="checkbox"${attrs}${checked}> ${escapeHtml(options.checkboxLabel || '')}</label>`;
                } else if (type === 'number') {
                    control = `<input type="number"${attrs} value="${escapeHtml(String(value ?? ''))}" min="${options.min ?? 0}" max="${options.max ?? 999}" step="${options.step ?? 1}">`;
                } else {
                    control = `<input type="text"${attrs}${placeholder} value="${escapeHtml(value || '')}">`;
                }

                return `<div class="field"><label>${escapeHtml(label)}</label>${control}${hint}</div>`;
            };

            const buildModuleWidgetFields = (data) => {
                const moduleKey = String(data.module || '').trim();
                const widgetKey = String(data.widget || '').trim();
                const definition = findWidgetDefinition(moduleKey, widgetKey) || widgetsForModule(moduleKey)[0] || firstWidgetDefinition();
                const config = (data.config && typeof data.config === 'object') ? data.config : {};
                const dynamicFields = (Array.isArray(definition?.config_fields) ? definition.config_fields : []).map((field) => {
                    const name = String(field.name || '').trim();
                    if (!name) return '';
                    const type = String(field.type || 'text');
                    const currentValue = Object.prototype.hasOwnProperty.call(config, name)
                        ? config[name]
                        : (Object.prototype.hasOwnProperty.call(field, 'default') ? field.default : (type === 'checkbox' ? false : ''));
                    if (type === 'select') {
                        return buildField(String(field.label || name), `data.config.${name}`, currentValue, {
                            type: 'select',
                            options: moduleWidgetFieldOptions(field.options),
                            hint: field.hint || '',
                        });
                    }
                    if (type === 'checkbox') {
                        return buildField(String(field.label || name), `data.config.${name}`, !!currentValue, {
                            type: 'checkbox',
                            checkboxLabel: String(field.checkbox_label || field.hint || field.label || name),
                        });
                    }
                    return buildField(String(field.label || name), `data.config.${name}`, currentValue ?? '', {
                        type: type === 'textarea' ? 'textarea' : type,
                        rows: field.rows || 4,
                        placeholder: field.placeholder || '',
                        hint: field.hint || '',
                    });
                }).join('');

                return `
                    <div class="block-subtle">${escapeHtml(moduleWidgetSummary(data))}</div>
                    <div class="block-fields-grid">
                        ${buildField('Модуль', 'data._module', moduleKey, { type: 'select', options: moduleOptions() })}
                        ${buildField('Виджет', 'data._widget', widgetKey, { type: 'select', options: widgetsForModule(moduleKey).map((item) => ({ value: String(item.widget || ''), label: String(item.label || item.widget || '') })) })}
                    </div>
                    ${dynamicFields || '<div class="block-subtle">Для этого виджета нет дополнительных настроек.</div>'}
                    ${buildField('Конфиг JSON (advanced)', 'data._config_json', JSON.stringify(config, null, 2), {
                        type: 'textarea',
                        rows: 6,
                        hint: 'Продвинутый режим. Невалидный JSON не применяется.',
                    })}
                `;
            };

            const blockFieldsMarkup = (block) => {
                const type = block.type;
                const data = block.data || {};

                if (type === 'heading') {
                    return `
                        <div class="block-fields-grid">
                            ${buildField('Уровень', 'data.level', Number(data.level || 2), { type: 'select', options: [1,2,3,4,5,6].map((v) => ({ value: v, label: `H${v}` })) })}
                            ${buildField('Текст', 'data.text', data.text || '', { placeholder: 'Текст заголовка' })}
                        </div>
                    `;
                }

                if (type === 'rich_text') {
                    return `
                        <div class="mini-toolbar">
                            <button type="button" class="icon-btn" data-rich-snippet="paragraph">P</button>
                            <button type="button" class="icon-btn" data-rich-snippet="h2">H2</button>
                            <button type="button" class="icon-btn" data-rich-snippet="image">Изобр.</button>
                            <button type="button" class="icon-btn" data-rich-snippet="gallery">Галерея</button>
                            <button type="button" class="icon-btn" data-rich-snippet="cta">CTA</button>
                            <button type="button" class="icon-btn" data-rich-snippet="list">Список</button>
                        </div>
                        ${buildField('HTML', 'data.html', data.html || '', { type: 'textarea', rows: 8, hint: 'Поддерживает форматированный HTML. На сохранении будет sanitation.' })}
                    `;
                }

                if (type === 'image') {
                    return `
                        <div class="inline" style="margin-bottom:8px;">
                            <button type="button" class="btn btn-small" data-block-media-pick="image">Выбрать из Assets</button>
                        </div>
                        ${buildField('URL изображения', 'data.src', data.src || '', { placeholder: 'https://...' })}
                        <div class="block-fields-grid">
                            ${buildField('Alt-текст', 'data.alt', data.alt || '')}
                            ${buildField('Подпись', 'data.caption', data.caption || '')}
                        </div>
                    `;
                }

                if (type === 'video_embed') {
                    return buildField('URL embed', 'data.url', data.url || '', { placeholder: 'https://www.youtube.com/embed/...' });
                }

                if (type === 'gallery') {
                    return `
                        <div class="inline" style="margin-bottom:8px;">
                            <button type="button" class="btn btn-small" data-block-media-pick="gallery">Добавить изображения из Assets</button>
                        </div>
                        ${buildField('Элементы галереи', 'data._gallery_lines', toGalleryLines(data.items), {
                            type: 'textarea',
                            rows: 5,
                            hint: 'Каждая строка: URL | Alt',
                        })}
                    `;
                }

                if (type === 'list') {
                    return `
                        ${buildField('Ordered list', 'data.ordered', !!data.ordered, { type: 'checkbox', checkboxLabel: 'Render as <ol>' })}
                        ${buildField('Items', 'data._list_lines', toListLines(data.items), { type: 'textarea', rows: 5, hint: 'По одному пункту на строку' })}
                    `;
                }

                if (type === 'divider') {
                    return `<div class="block-subtle">У блока-разделителя нет настроек.</div>`;
                }

                if (type === 'cta') {
                    return `
                        <div class="block-subtle">Превью CTA</div>
                        <div style="margin-top:-4px; margin-bottom:6px;"><a class="cms-cta" href="${escapeHtml(data.url || '#')}">${escapeHtml(data.label || 'CTA')}</a></div>
                        <div class="block-fields-grid">
                            ${buildField('Текст кнопки', 'data.label', data.label || '')}
                            ${buildField('URL назначения', 'data.url', data.url || '', { placeholder: '/ru/blog или https://...' })}
                        </div>
                        <div class="block-fields-grid">
                            ${buildField('Открыть в новой вкладке', 'data.target_blank', !!data.target_blank, { type: 'checkbox', checkboxLabel: 'target=_blank + noopener/noreferrer' })}
                            ${buildField('Nofollow', 'data.nofollow', !!data.nofollow, { type: 'checkbox', checkboxLabel: 'Добавить rel=nofollow' })}
                        </div>
                    `;
                }

                if (type === 'table') {
                    return buildField('Строки', 'data._table_lines', toTableLines(data.rows), {
                        type: 'textarea',
                        rows: 6,
                        hint: 'Каждая строка = строка таблицы, ячейки через |',
                    });
                }

                if (type === 'module_widget') {
                    return buildModuleWidgetFields(data);
                }

                if (type === 'custom_code_embed') {
                    return `
                        <div class="block-fields-grid">
                            ${buildField('Название embed (опционально)', 'data.label', data.label || '', { placeholder: 'Например: форма заявки / карта' })}
                        </div>
                        ${buildField('Кастомный embed HTML', 'data.html', data.html || '', {
                            type: 'textarea',
                            rows: 9,
                            hint: 'Вставьте iframe/script-код внешнего сервиса. HTML очищается restricted policy; script src разрешён только для доменов из CMS_SAFE_EMBED_DOMAINS.',
                        })}
                    `;
                }

                if (type === 'html_embed_restricted') {
                    return buildField('Ограниченный HTML embed', 'data.html', data.html || '', { type: 'textarea', rows: 7, hint: 'Будет очищено restricted policy на сохранении' });
                }

                if (type === 'post_listing') {
                    return `
                        <div class="block-fields-grid">
                            ${buildField('Slug категории', 'data.category_slug', data.category_slug || '', { placeholder: 'необязательно' })}
                            ${buildField('Лимит', 'data.limit', Number(data.limit || 10), { type: 'number', min: 1, max: 100 })}
                        </div>
                    `;
                }

                if (type === 'faq') {
                    return buildField('Элементы FAQ', 'data._faq_text', toFaqText(data.items), {
                        type: 'textarea',
                        rows: 7,
                        hint: 'Формат: Q: вопрос / A: <p>ответ</p> (между блоками пустая строка)',
                    });
                }

                return `<div class="block-subtle">Для этого типа блока редактор недоступен.</div>`;
            };

            const createBuilder = (root) => {
                const locale = root.getAttribute('data-page-builder');
                const blocksJsonField = root.querySelector('[data-blocks-json]');
                const richHtmlField = root.querySelector('[data-rich-html-fallback]');
                const blocksList = root.querySelector('[data-blocks-list]');
                const preview = root.querySelector('[data-builder-preview]');
                const previewFrame = root.querySelector('[data-builder-preview-frame]');
                const layout = root.querySelector('[data-builder-layout]');
                const paneBuilder = root.querySelector('[data-builder-pane="builder"]');
                const panePreview = root.querySelector('[data-builder-pane="preview"]');
                const viewToggle = root.querySelector('[data-builder-view-toggle]');
                const deviceToggle = root.querySelector('[data-builder-device-toggle]');
                const subtabButtons = Array.from(root.querySelectorAll('[data-builder-subtab]'));
                const subpanels = Array.from(root.querySelectorAll('[data-builder-subpanel]'));
                const visualBuilderOpenBtn = root.querySelector('[data-builder-open-fullscreen]');

                if (!blocksJsonField || !blocksList || !preview || !previewFrame || !layout || !paneBuilder || !panePreview || !viewToggle || !deviceToggle) {
                    return null;
                }

                const parsedBlocks = parseJson(blocksJsonField.value);
                const initialNodes = normalizeLayoutNodes(parsedBlocks, {
                    fallbackHtml: richHtmlField ? richHtmlField.value : '',
                });

                let state = {
                    rawNodes: initialNodes,
                    isStructured: true,
                    collapsed: {},
                };

                const syncJson = () => {
                    blocksJsonField.value = JSON.stringify(state.rawNodes, null, 2);
                    try {
                        blocksJsonField.dispatchEvent(new Event('input', { bubbles: true }));
                    } catch (_) {}
                };

                const renderPreview = () => {
                    const nodes = state.rawNodes;
                    if (!Array.isArray(nodes) || nodes.length === 0) {
                        preview.innerHTML = '<div class="builder-preview-placeholder">Добавьте блоки слева, чтобы увидеть страницу.</div>';
                        return;
                    }
                    preview.innerHTML = previewRenderNodes(nodes);
                };

                const syncStructuredUiState = () => {
                    root.classList.toggle('is-structured', true);
                };

                const setBuilderSubtab = (name) => {
                    const activeName = ['blocks', 'add', 'presets'].includes(String(name)) ? String(name) : 'blocks';
                    subtabButtons.forEach((btn) => {
                        btn.classList.toggle('active', btn.getAttribute('data-builder-subtab') === activeName);
                    });
                    subpanels.forEach((panel) => {
                        panel.classList.toggle('active', panel.getAttribute('data-builder-subpanel') === activeName);
                    });
                };

                const setView = (mode) => {
                    viewToggle.querySelectorAll('button').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-builder-view') === mode));
                    deviceToggle.style.display = (mode === 'split' || mode === 'preview') ? '' : 'none';
                    uiWrite(PAGE_BUILDER_VIEW_KEY, mode);
                    if (mode === 'builder') {
                        layout.style.gridTemplateColumns = '1fr';
                        paneBuilder.style.display = 'block';
                        panePreview.style.display = 'none';
                    } else if (mode === 'preview') {
                        layout.style.gridTemplateColumns = '1fr';
                        paneBuilder.style.display = 'none';
                        panePreview.style.display = 'flex';
                    } else {
                        layout.style.gridTemplateColumns = '';
                        paneBuilder.style.display = 'block';
                        panePreview.style.display = 'flex';
                    }
                };

                const setDevice = (device) => {
                    deviceToggle.querySelectorAll('button').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-builder-device') === device));
                    previewFrame.classList.remove('device-desktop', 'device-tablet', 'device-mobile');
                    previewFrame.classList.add(`device-${device}`);
                };

                const renderBlocks = () => {
                    syncStructuredUiState();
                    const rootCount = Array.isArray(state.rawNodes) ? state.rawNodes.length : 0;
                    const stats = [];
                    walkNodes(state.rawNodes, (node) => {
                        const type = String(node?.type || '');
                        if (type === 'section') stats.push('section');
                        else if (type === 'columns') stats.push('columns');
                        else stats.push('leaf');
                    });
                    const sectionsCount = stats.filter((type) => type === 'section').length;
                    const columnsCount = stats.filter((type) => type === 'columns').length;
                    const blocksCount = stats.filter((type) => type === 'leaf').length;

                    blocksList.innerHTML = `
                        <div class="builder-structured-summary">
                            <div class="builder-structured-summary-head">
                                <div>
                                    <strong>Структура страницы</strong>
                                    <p>Основное редактирование выполняется во встроенном визуальном конструкторе со сценой, секциями и колонками.</p>
                                </div>
                                <div class="builder-structured-summary-stats">
                                    <span class="builder-structured-summary-stat">Секций: ${sectionsCount}</span>
                                    <span class="builder-structured-summary-stat">Групп колонок: ${columnsCount}</span>
                                    <span class="builder-structured-summary-stat">Блоков: ${blocksCount}</span>
                                    <span class="builder-structured-summary-stat">Корневых узлов: ${rootCount}</span>
                                </div>
                            </div>
                            <div class="builder-structured-summary-actions">
                                <button type="button" class="btn btn-primary" data-builder-open-fullscreen-inline>Открыть визуальный конструктор</button>
                            </div>
                            <div class="builder-structured-summary-note">
                                Предпросмотр справа показывает текущую структуру. JSON и fallback HTML ниже остаются доступными как продвинутый режим.
                            </div>
                        </div>
                    `;
                    syncJson();
                    renderPreview();
                };

                const applyBlockField = (block, fieldPath, rawValue, inputEl) => {
                    if (!fieldPath.startsWith('data.')) return;
                    const key = fieldPath.slice(5);
                    const data = block.data || (block.data = {});

                    if (key === 'level') {
                        data.level = Math.min(6, Math.max(1, Number(rawValue || 2)));
                        return;
                    }
                    if (key === 'ordered') {
                        data.ordered = !!(inputEl && inputEl.checked);
                        return;
                    }
                    if (key === 'target_blank' || key === 'nofollow') {
                        data[key] = !!(inputEl && inputEl.checked);
                        return;
                    }
                    if (key === '_module') {
                        const widgets = widgetsForModule(rawValue);
                        const nextDefinition = widgets[0] || null;
                        data.module = String(rawValue || '');
                        data.widget = String(nextDefinition?.widget || '');
                        data.config = nextDefinition ? moduleWidgetDefaultConfig(nextDefinition) : {};
                        return;
                    }
                    if (key === '_widget') {
                        const nextDefinition = findWidgetDefinition(data.module, rawValue);
                        data.widget = String(rawValue || '');
                        data.config = nextDefinition
                            ? Object.assign(moduleWidgetDefaultConfig(nextDefinition), (data.config && typeof data.config === 'object') ? data.config : {})
                            : ((data.config && typeof data.config === 'object') ? data.config : {});
                        return;
                    }
                    if (key === '_config_json') {
                        try {
                            const parsed = JSON.parse(String(rawValue || '{}'));
                            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                                data.config = parsed;
                            }
                        } catch (_) {}
                        return;
                    }
                    if (key === 'limit') {
                        data.limit = Math.max(1, Math.min(100, Number(rawValue || 10)));
                        return;
                    }
                    if (key.startsWith('config.')) {
                        const configKey = key.slice('config.'.length);
                        data.config = (data.config && typeof data.config === 'object') ? data.config : {};
                        data.config[configKey] = inputEl?.type === 'checkbox' ? !!inputEl.checked : rawValue;
                        return;
                    }
                    if (key === '_gallery_lines') {
                        data.items = fromGalleryLines(rawValue);
                        return;
                    }
                    if (key === '_list_lines') {
                        data.items = fromListLines(rawValue);
                        return;
                    }
                    if (key === '_table_lines') {
                        data.rows = fromTableLines(rawValue);
                        return;
                    }
                    if (key === '_faq_text') {
                        data.items = fromFaqText(rawValue);
                        return;
                    }

                    data[key] = rawValue;
                };

                const addBlock = (type) => {
                    if (state.isStructured) return;
                    if (!allowedTypes.includes(type)) return;
                    state.blocks.push(defaultBlock(type));
                    setBuilderSubtab('blocks');
                    renderBlocks();
                };

                const addPreset = (key) => {
                    if (state.isStructured) return;
                    const factory = presetBlocks[key];
                    if (!factory) return;
                    const blocks = factory().filter((b) => allowedTypes.includes(b.type));
                    state.blocks = state.blocks.concat(blocks);
                    setBuilderSubtab('blocks');
                    renderBlocks();
                };

                let dragIndex = null;

                blocksList.addEventListener('dragstart', (e) => {
                    if (state.isStructured) return;
                    const card = e.target.closest('.block-card');
                    if (!card) return;
                    dragIndex = Number(card.getAttribute('data-block-index'));
                    card.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    try { e.dataTransfer.setData('text/plain', String(dragIndex)); } catch (_) {}
                });

                blocksList.addEventListener('dragend', (e) => {
                    if (state.isStructured) return;
                    const card = e.target.closest('.block-card');
                    if (card) card.classList.remove('dragging');
                    dragIndex = null;
                    blocksList.querySelectorAll('.block-card').forEach((c) => c.classList.remove('drop-target'));
                });

                blocksList.addEventListener('dragover', (e) => {
                    if (state.isStructured) return;
                    const card = e.target.closest('.block-card');
                    if (!card) return;
                    e.preventDefault();
                    blocksList.querySelectorAll('.block-card').forEach((c) => c.classList.remove('drop-target'));
                    card.classList.add('drop-target');
                });

                blocksList.addEventListener('drop', (e) => {
                    if (state.isStructured) return;
                    const card = e.target.closest('.block-card');
                    if (!card) return;
                    e.preventDefault();
                    const targetIndex = Number(card.getAttribute('data-block-index'));
                    if (!Number.isInteger(dragIndex) || dragIndex === targetIndex) return;
                    const [moved] = state.blocks.splice(dragIndex, 1);
                    state.blocks.splice(targetIndex, 0, moved);
                    state.collapsed = {};
                    renderBlocks();
                });

                blocksList.addEventListener('click', (e) => {
                    if (state.isStructured) {
                        const openBtn = e.target.closest('[data-builder-open-fullscreen], [data-builder-open-fullscreen-inline]');
                        if (openBtn && visualBuilderOpenBtn) visualBuilderOpenBtn.click();
                        return;
                    }
                    const actionBtn = e.target.closest('[data-block-action]');
                    const card = e.target.closest('.block-card');
                    if (actionBtn && card) {
                        const idx = Number(card.getAttribute('data-block-index'));
                        const action = actionBtn.getAttribute('data-block-action');
                        if (!Number.isInteger(idx)) return;

                        if (action === 'delete') {
                            state.blocks.splice(idx, 1);
                            renderBlocks();
                            return;
                        }
                        if (action === 'duplicate') {
                            const cloneBlock = (typeof structuredClone === 'function')
                                ? structuredClone(state.blocks[idx])
                                : JSON.parse(JSON.stringify(state.blocks[idx]));
                            state.blocks.splice(idx + 1, 0, cloneBlock);
                            renderBlocks();
                            return;
                        }
                        if (action === 'up' && idx > 0) {
                            [state.blocks[idx - 1], state.blocks[idx]] = [state.blocks[idx], state.blocks[idx - 1]];
                            renderBlocks();
                            return;
                        }
                        if (action === 'down' && idx < state.blocks.length - 1) {
                            [state.blocks[idx + 1], state.blocks[idx]] = [state.blocks[idx], state.blocks[idx + 1]];
                            renderBlocks();
                            return;
                        }
                        if (action === 'collapse') {
                            state.collapsed[idx] = !state.collapsed[idx];
                            renderBlocks();
                            return;
                        }
                    }

                    const mediaPickBtn = e.target.closest('[data-block-media-pick]');
                    if (mediaPickBtn && card) {
                        const idx = Number(card.getAttribute('data-block-index'));
                        const block = state.blocks[idx];
                        if (!block) return;
                        const mode = mediaPickBtn.getAttribute('data-block-media-pick');

                        if (mode === 'image' && block.type === 'image') {
                            openMediaPicker({
                                accept: 'image',
                                multiple: false,
                                title: 'Выбор изображения',
                                subtitle: 'Выберите изображение из Assets для image-блока',
                            }).then((asset) => {
                                if (!asset || !asset.public_url) return;
                                block.data = block.data || {};
                                block.data.src = asset.public_url;
                                if (!String(block.data.alt || '').trim()) {
                                    block.data.alt = asset.alt || asset.title || '';
                                }
                                if (!String(block.data.caption || '').trim() && asset.caption) {
                                    block.data.caption = asset.caption;
                                }
                                renderBlocks();
                            }).catch(() => {});
                            return;
                        }

                        if (mode === 'gallery' && block.type === 'gallery') {
                            openMediaPicker({
                                accept: 'image',
                                multiple: true,
                                title: 'Галерея: выбрать изображения',
                                subtitle: 'Можно выбрать несколько изображений из Assets',
                            }).then((assets) => {
                                const selected = Array.isArray(assets) ? assets : (assets ? [assets] : []);
                                if (selected.length === 0) return;
                                block.data = block.data || {};
                                const currentItems = Array.isArray(block.data.items) ? block.data.items : [];
                                const appended = selected
                                    .filter((asset) => asset && asset.public_url)
                                    .map((asset) => ({ src: asset.public_url, alt: asset.alt || asset.title || '' }));
                                block.data.items = currentItems.concat(appended);
                                renderBlocks();
                            }).catch(() => {});
                            return;
                        }
                    }

                    const richSnippetBtn = e.target.closest('[data-rich-snippet]');
                    if (richSnippetBtn && card) {
                        const idx = Number(card.getAttribute('data-block-index'));
                        const block = state.blocks[idx];
                        if (!block || block.type !== 'rich_text') return;
                        const textarea = card.querySelector('[data-block-input="data.html"]');
                        if (!textarea) return;
                        const snippetKey = richSnippetBtn.getAttribute('data-rich-snippet');

                        const insertIntoTextarea = (text) => {
                            const value = String(textarea.value || '');
                            const start = Number.isInteger(textarea.selectionStart) ? textarea.selectionStart : value.length;
                            const end = Number.isInteger(textarea.selectionEnd) ? textarea.selectionEnd : value.length;
                            const prefix = value.slice(0, start);
                            const suffix = value.slice(end);
                            const needsLeadingNl = prefix.length > 0 && !prefix.endsWith('\n');
                            const needsTrailingNl = suffix.length > 0 && !String(text).endsWith('\n');
                            const insertion = `${needsLeadingNl ? '\n' : ''}${text}${needsTrailingNl ? '\n' : ''}`;
                            textarea.value = `${prefix}${insertion}${suffix}`;
                            const cursor = (prefix + insertion).length;
                            try {
                                textarea.focus();
                                textarea.setSelectionRange(cursor, cursor);
                            } catch (_) {}
                        };

                        if (snippetKey === 'image') {
                            openMediaPicker({
                                accept: 'image',
                                multiple: false,
                                title: 'Вставка изображения в rich text',
                                subtitle: 'Выберите изображение из Assets для HTML-блока',
                            }).then((asset) => {
                                if (!asset || !asset.public_url) return;
                                const url = escapeHtml(asset.public_url || '');
                                const alt = escapeHtml(asset.alt || asset.title || '');
                                const caption = asset.caption ? `<figcaption>${escapeHtml(asset.caption)}</figcaption>` : '';
                                insertIntoTextarea(`<figure><img src="${url}" alt="${alt}" loading="lazy">${caption}</figure>`);
                                applyBlockField(block, 'data.html', textarea.value, textarea);
                                renderBlocks();
                            }).catch(() => {});
                            return;
                        }
                        if (snippetKey === 'gallery') {
                            openMediaPicker({
                                accept: 'image',
                                multiple: true,
                                title: 'Вставка галереи в rich text',
                                subtitle: 'Выберите несколько изображений из Assets',
                            }).then((assets) => {
                                const selected = Array.isArray(assets) ? assets : (assets ? [assets] : []);
                                const valid = selected.filter((asset) => asset && asset.public_url);
                                if (valid.length === 0) return;
                                const imagesHtml = valid
                                    .map((asset) => {
                                        const src = escapeHtml(asset.public_url || '');
                                        const alt = escapeHtml(asset.alt || asset.title || '');
                                        return `<img src="${src}" alt="${alt}" loading="lazy">`;
                                    })
                                    .join('');
                                insertIntoTextarea(`<div class="cms-gallery">${imagesHtml}</div>`);
                                applyBlockField(block, 'data.html', textarea.value, textarea);
                                renderBlocks();
                            }).catch(() => {});
                            return;
                        }

                        const snippets = {
                            paragraph: '<p>Новый абзац текста.</p>',
                            h2: '<h2>Новый подзаголовок</h2>',
                            cta: '<p><a class="cms-cta" href="/ru/blog">Кнопка CTA</a></p>',
                            list: '<ul><li>Пункт 1</li><li>Пункт 2</li></ul>',
                        };
                        insertIntoTextarea(snippets[snippetKey] || '');
                        applyBlockField(block, 'data.html', textarea.value, textarea);
                        renderBlocks();
                        return;
                    }
                });

                blocksList.addEventListener('input', (e) => {
                    if (state.isStructured) return;
                    const input = e.target.closest('[data-block-input]');
                    const card = e.target.closest('.block-card');
                    if (!input || !card) return;
                    const idx = Number(card.getAttribute('data-block-index'));
                    const block = state.blocks[idx];
                    if (!block) return;
                    applyBlockField(block, input.getAttribute('data-block-input'), input.type === 'checkbox' ? input.checked : input.value, input);
                    syncJson();
                    renderPreview();
                });

                blocksList.addEventListener('change', (e) => {
                    if (state.isStructured) return;
                    const input = e.target.closest('[data-block-input]');
                    const card = e.target.closest('.block-card');
                    if (!input || !card) return;
                    const idx = Number(card.getAttribute('data-block-index'));
                    const block = state.blocks[idx];
                    if (!block) return;
                    applyBlockField(block, input.getAttribute('data-block-input'), input.type === 'checkbox' ? input.checked : input.value, input);
                    renderBlocks();
                });

                root.querySelectorAll('[data-add-block]').forEach((btn) => {
                    btn.addEventListener('click', () => addBlock(btn.getAttribute('data-add-block')));
                });
                root.querySelectorAll('[data-add-preset]').forEach((btn) => {
                    btn.addEventListener('click', () => addPreset(btn.getAttribute('data-add-preset')));
                });
                subtabButtons.forEach((btn) => {
                    btn.addEventListener('click', () => setBuilderSubtab(btn.getAttribute('data-builder-subtab')));
                });

                const applyRawJsonBtn = root.querySelector('[data-apply-raw-json]');
                if (applyRawJsonBtn) {
                    applyRawJsonBtn.addEventListener('click', () => {
                        const parsed = parseJson(blocksJsonField.value);
                        if (!parsed) {
                            dialogs.alert('JSON должен быть массивом блоков.');
                            return;
                        }
                        state.rawNodes = normalizeLayoutNodes(parsed, {
                            fallbackHtml: richHtmlField ? richHtmlField.value : '',
                        });
                        state.collapsed = {};
                        renderBlocks();
                    });
                }

                const formatRawJsonBtn = root.querySelector('[data-format-raw-json]');
                if (formatRawJsonBtn) {
                    formatRawJsonBtn.addEventListener('click', () => {
                        try {
                            const parsed = JSON.parse(blocksJsonField.value || '[]');
                            blocksJsonField.value = JSON.stringify(parsed, null, 2);
                        } catch (e) {
                            dialogs.alert('Невозможно форматировать: JSON невалиден.');
                        }
                    });
                }

                const convertFallbackBtn = root.querySelector('[data-convert-fallback-to-block]');
                if (convertFallbackBtn && richHtmlField) {
                    convertFallbackBtn.addEventListener('click', () => {
                        const html = richHtmlField.value.trim();
                        if (!html) {
                            dialogs.alert('Fallback HTML пустой.');
                            return;
                        }
                        const nextNodes = normalizeLayoutNodes(state.rawNodes, { fallbackHtml: '' });
                        if (Array.isArray(nextNodes[0]?.children)) {
                            nextNodes[0].children.push({ type: 'rich_text', data: { html } });
                        }
                        state.rawNodes = nextNodes;
                        richHtmlField.value = '';
                        renderBlocks();
                    });
                }

                viewToggle.addEventListener('click', (e) => {
                    const btn = e.target.closest('button[data-builder-view]');
                    if (!btn) return;
                    setView(btn.getAttribute('data-builder-view'));
                });
                deviceToggle.addEventListener('click', (e) => {
                    const btn = e.target.closest('button[data-builder-device]');
                    if (!btn) return;
                    setDevice(btn.getAttribute('data-builder-device'));
                });

                renderBlocks();
                setBuilderSubtab('blocks');
                const savedView = uiRead(PAGE_BUILDER_VIEW_KEY, 'builder');
                setView(['builder', 'preview', 'split'].includes(savedView) ? savedView : 'builder');
                setDevice('desktop');

                syncStructuredUiState();

                if (visualBuilderOpenBtn) {
                    visualBuilderOpenBtn.addEventListener('click', () => {
                        try {
                            form.dispatchEvent(new CustomEvent('testocms:page-fullscreen-open-request', { detail: { locale } }));
                        } catch (_) {}
                    });
                }

                return {
                    sync() {
                        syncJson();
                    },
                    getNodes() {
                        return cloneJson(state.rawNodes);
                    },
                    setNodes(nodes) {
                        state.rawNodes = normalizeLayoutNodes(Array.isArray(nodes) ? cloneJson(nodes) : [], {
                            fallbackHtml: richHtmlField ? richHtmlField.value : '',
                        });
                        state.collapsed = {};
                        renderBlocks();
                    },
                    hasStructuredLayout() {
                        return containsStructuredLayout(state.rawNodes);
                    },
                    openFullscreen() {
                        if (visualBuilderOpenBtn) visualBuilderOpenBtn.click();
                    },
                    refreshPreview() {
                        renderPreview();
                    },
                    reloadFromInputs() {
                        const parsed = parseJson(blocksJsonField.value);
                        state.rawNodes = normalizeLayoutNodes(parsed, {
                            fallbackHtml: richHtmlField ? richHtmlField.value : '',
                        });
                        state.collapsed = {};
                        renderBlocks();
                    },
                    root,
                    locale,
                };
            };

            const updateSeoPreview = (locale) => {
                const title = document.querySelector(`[data-page-title="${locale}"]`);
                const slug = document.querySelector(`[data-page-slug="${locale}"]`);
                const metaTitle = document.querySelector(`[data-page-meta-title="${locale}"]`);
                const metaDescription = document.querySelector(`[data-page-meta-description="${locale}"]`);
                const canonical = document.querySelector(`[data-page-canonical="${locale}"]`);
                const box = document.querySelector(`[data-page-seo-preview="${locale}"]`);
                if (!title || !slug || !metaTitle || !metaDescription || !box) return;

                const canonicalPath = (loc, rawSlug) => {
                    const safeLocale = String(loc || '').trim().replace(/^\/+|\/+$/g, '');
                    const safeSlug = String(rawSlug || '').trim().replace(/^\/+|\/+$/g, '');
                    if (!safeLocale) return '/';
                    if (safeSlug !== '' && safeSlug.toLowerCase() === 'home') {
                        return `/${safeLocale}`;
                    }

                    return `/${safeLocale}/${safeSlug || 'slug'}`;
                };
                const canonicalAbsolute = (loc, rawSlug) => `${window.location.origin}${canonicalPath(loc, rawSlug)}`;
                const normalizeComparableUrl = (value) => {
                    const current = String(value || '').trim();
                    if (!current) return '';
                    const stripped = current.replace(/\/+$/, '');

                    return stripped || current;
                };
                const syncSlugFromTitle = (loc) => {
                    const titleField = document.querySelector(`[data-page-title="${loc}"]`);
                    const slugField = document.querySelector(`[data-page-slug="${loc}"]`);
                    if (!titleField || !slugField) return;
                    if (slugField.dataset.userEdited && slugField.value.trim() !== '') return;
                    slugField.value = slugify(titleField.value);
                };
                const syncCanonicalFromSlug = (loc) => {
                    const slugField = document.querySelector(`[data-page-slug="${loc}"]`);
                    const canonicalField = document.querySelector(`[data-page-canonical="${loc}"]`);
                    if (!slugField || !canonicalField) return;
                    const slugValue = slugField.value.trim();
                    if (slugValue === '') return;
                    if (canonicalField.dataset.userEdited && canonicalField.value.trim() !== '') return;
                    canonicalField.value = canonicalAbsolute(loc, slugValue);
                };
                const refreshSeoFieldModes = (loc) => {
                    const titleField = document.querySelector(`[data-page-title="${loc}"]`);
                    const slugField = document.querySelector(`[data-page-slug="${loc}"]`);
                    const canonicalField = document.querySelector(`[data-page-canonical="${loc}"]`);
                    if (slugField) {
                        const suggestedSlug = slugify(titleField ? titleField.value : '');
                        const currentSlug = slugField.value.trim();
                        if (currentSlug === '' || (suggestedSlug !== '' && currentSlug === suggestedSlug)) {
                            delete slugField.dataset.userEdited;
                        } else {
                            slugField.dataset.userEdited = '1';
                        }
                    }
                    if (canonicalField) {
                        const currentCanonical = canonicalField.value.trim();
                        const baseSlug = (slugField?.value || '').trim() || slugify(titleField ? titleField.value : '');
                        const expectedPath = baseSlug ? canonicalPath(loc, baseSlug) : '';
                        const expectedAbsolute = baseSlug ? canonicalAbsolute(loc, baseSlug) : '';
                        const comparableCanonical = normalizeComparableUrl(currentCanonical);
                        if (
                            comparableCanonical === ''
                            || (expectedPath !== '' && comparableCanonical === normalizeComparableUrl(expectedPath))
                            || (expectedAbsolute !== '' && comparableCanonical === normalizeComparableUrl(expectedAbsolute))
                        ) {
                            delete canonicalField.dataset.userEdited;
                        } else {
                            canonicalField.dataset.userEdited = '1';
                        }
                    }
                };
                refreshSeoFieldModes(locale);
                syncSlugFromTitle(locale);
                syncCanonicalFromSlug(locale);

                const titleEl = box.querySelector('[data-seo-preview-title]');
                const urlEl = box.querySelector('[data-seo-preview-url]');
                const descEl = box.querySelector('[data-seo-preview-desc]');
                const displayTitle = (metaTitle.value || title.value || 'Заголовок страницы').trim();
                const displaySlug = (slug.value || 'slug').trim();
                const displayDesc = (metaDescription.value || 'Превью meta description.').trim();
                const displayCanonical = (canonical && canonical.value.trim()) || canonicalAbsolute(locale, displaySlug);

                if (titleEl) titleEl.textContent = displayTitle;
                if (urlEl) urlEl.textContent = displayCanonical;
                if (descEl) descEl.textContent = displayDesc;
            };

            wireLocaleTabs();
            setupComposerSidebar();

            const builders = [];
            document.querySelectorAll('[data-page-builder]').forEach((root) => {
                const instance = createBuilder(root);
                if (instance) builders.push(instance);
            });
            const getActiveLocale = () => document.querySelector('[data-locale-tab].active')?.getAttribute('data-locale-tab')
                || document.querySelector('[data-locale-tab]')?.getAttribute('data-locale-tab')
                || null;
            window.TestoCmsPageBuilderBridge = {
                form,
                builders,
                getActiveLocale,
                getBuilder(locale) {
                    return builders.find((b) => String(b.locale) === String(locale)) || null;
                },
                syncBuilders() {
                    builders.forEach((b) => b.sync && b.sync());
                },
                updateSeoPreview,
                parseJson,
                escapeHtml,
                openMediaPicker,
                slugify,
                cloneJson,
                typeLabels,
                allowedTypes,
                moduleWidgetCatalog,
                findWidgetDefinition,
                widgetsForModule,
                moduleOptions,
                moduleWidgetSummary,
                moduleWidgetDefaultConfig,
                defaultBlock,
                presetBlocks,
                previewRenderBlock,
                previewRenderNode,
                previewRenderNodes,
                isStructuredLayoutNode,
                containsStructuredLayout,
                normalizeLayoutNodes,
                createStructuredSection,
                fromGalleryLines,
                toGalleryLines,
                fromListLines,
                toListLines,
                fromTableLines,
                toTableLines,
                fromFaqText,
                toFaqText,
                uiRead,
                uiWrite,
            };

            document.querySelectorAll('[data-slug-generate]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const locale = btn.getAttribute('data-slug-generate');
                    const title = document.querySelector(`[data-page-title="${locale}"]`);
                    const slug = document.querySelector(`[data-page-slug="${locale}"]`);
                    if (!title || !slug) return;
                    slug.value = slugify(title.value);
                    if (slug.value.trim() === '') {
                        delete slug.dataset.userEdited;
                    }
                    updateSeoPreview(locale);
                });
            });

            document.querySelectorAll('[data-page-title], [data-page-slug], [data-page-meta-title], [data-page-meta-description], [data-page-canonical]').forEach((input) => {
                input.addEventListener('input', () => {
                    const locale = input.getAttribute('data-page-title')
                        || input.getAttribute('data-page-slug')
                        || input.getAttribute('data-page-meta-title')
                        || input.getAttribute('data-page-meta-description')
                        || input.getAttribute('data-page-canonical');
                    if (input.dataset.pageTitle || input.dataset.pageSlug || input.dataset.pageCanonical) {
                        const titleField = document.querySelector(`[data-page-title="${locale}"]`);
                        const slugField = document.querySelector(`[data-page-slug="${locale}"]`);
                        const canonicalField = document.querySelector(`[data-page-canonical="${locale}"]`);

                        if (input.dataset.pageSlug && slugField) {
                            if (slugField.value.trim() === '') {
                                delete slugField.dataset.userEdited;
                            } else {
                                slugField.dataset.userEdited = '1';
                            }
                        }

                        if (input.dataset.pageCanonical && canonicalField) {
                            const current = canonicalField.value.trim();
                            const baseSlug = (slugField?.value || '').trim() || slugify(titleField ? titleField.value : '');
                            const expectedPath = baseSlug
                                ? (baseSlug.toLowerCase() === 'home' ? `/${locale}` : `/${locale}/${baseSlug}`)
                                : '';
                            const expectedAbsolute = expectedPath ? `${window.location.origin}${expectedPath}` : '';
                            const comparable = current ? (current.replace(/\/+$/, '') || current) : '';
                            const pathComparable = expectedPath ? (expectedPath.replace(/\/+$/, '') || expectedPath) : '';
                            const absComparable = expectedAbsolute ? (expectedAbsolute.replace(/\/+$/, '') || expectedAbsolute) : '';
                            if (
                                comparable === ''
                                || (pathComparable !== '' && comparable === pathComparable)
                                || (absComparable !== '' && comparable === absComparable)
                            ) {
                                delete canonicalField.dataset.userEdited;
                            } else {
                                canonicalField.dataset.userEdited = '1';
                            }
                        }
                    }
                    updateSeoPreview(locale);
                });
            });

            document.querySelectorAll('[data-page-slug]').forEach((input) => {
                const locale = input.getAttribute('data-page-slug');
                const titleField = document.querySelector(`[data-page-title="${locale}"]`);
                const currentSlug = input.value.trim();
                const suggestedSlug = slugify(titleField ? titleField.value : '');
                if (currentSlug === '' || (suggestedSlug !== '' && currentSlug === suggestedSlug)) {
                    delete input.dataset.userEdited;
                } else {
                    input.dataset.userEdited = '1';
                }
            });

            document.querySelectorAll('[data-page-canonical]').forEach((input) => {
                const locale = input.getAttribute('data-page-canonical');
                const slugField = document.querySelector(`[data-page-slug="${locale}"]`);
                const titleField = document.querySelector(`[data-page-title="${locale}"]`);
                const baseSlug = (slugField?.value || '').trim() || slugify(titleField ? titleField.value : '');
                const expectedPath = baseSlug
                    ? (baseSlug.toLowerCase() === 'home' ? `/${locale}` : `/${locale}/${baseSlug}`)
                    : '';
                const expectedAbsolute = expectedPath ? `${window.location.origin}${expectedPath}` : '';
                const current = input.value.trim();
                const comparable = current ? (current.replace(/\/+$/, '') || current) : '';
                const pathComparable = expectedPath ? (expectedPath.replace(/\/+$/, '') || expectedPath) : '';
                const absComparable = expectedAbsolute ? (expectedAbsolute.replace(/\/+$/, '') || expectedAbsolute) : '';
                if (
                    comparable === ''
                    || (pathComparable !== '' && comparable === pathComparable)
                    || (absComparable !== '' && comparable === absComparable)
                ) {
                    delete input.dataset.userEdited;
                } else {
                    input.dataset.userEdited = '1';
                }
            });

            document.querySelectorAll('[data-page-seo-preview]').forEach((box) => {
                const locale = box.getAttribute('data-page-seo-preview');
                updateSeoPreview(locale);
            });

            const autosaveChip = document.createElement('div');
            autosaveChip.className = 'autosave-chip';
            autosaveChip.textContent = 'Автосохранение: готово';
            const autosaveSummaryChip = document.createElement('div');
            autosaveSummaryChip.className = 'autosave-summary';
            autosaveSummaryChip.textContent = 'Локали: —';
            const clearAutosaveBtn = document.createElement('button');
            clearAutosaveBtn.type = 'button';
            clearAutosaveBtn.className = 'autosave-action-btn';
            clearAutosaveBtn.textContent = 'Очистить автосохранение';
            const resetAndClearBtn = document.createElement('button');
            resetAndClearBtn.type = 'button';
            resetAndClearBtn.className = 'autosave-action-btn danger';
            resetAndClearBtn.textContent = 'Сбросить форму + очистить автосохранение';
            const pageHeaderActions = document.querySelector('.page-header .actions');
            if (pageHeaderActions) {
                pageHeaderActions.prepend(resetAndClearBtn);
                pageHeaderActions.prepend(clearAutosaveBtn);
                pageHeaderActions.prepend(autosaveSummaryChip);
                pageHeaderActions.prepend(autosaveChip);
            }

            const autosaveStorage = (() => {
                try {
                    const probe = '__testocms_page_autosave_probe__';
                    window.localStorage.setItem(probe, '1');
                    window.localStorage.removeItem(probe);
                    return window.localStorage;
                } catch (_) {
                    return null;
                }
            })();
            const autosaveSession = (() => {
                try {
                    const probe = '__testocms_page_autosave_session_probe__';
                    window.sessionStorage.setItem(probe, '1');
                    window.sessionStorage.removeItem(probe);
                    return window.sessionStorage;
                } catch (_) {
                    return null;
                }
            })();
            const autosaveKey = `testocms:autosave:page:${window.location.pathname}`;
            const autosaveCreateHandoffKey = 'testocms:autosave:page:create-handoff';
            let autosaveTimer = null;
            let lastSavedFingerprint = '';
            let pendingAutosave = false;
            let serverInitialPayload = null;

            const setAutosaveStatus = (text, mode = '') => {
                autosaveChip.textContent = text;
                autosaveChip.classList.remove('saving', 'restored');
                if (mode) autosaveChip.classList.add(mode);
            };

            const summarizeTranslationsPayload = (payload) => {
                const byLocale = {};
                Object.entries(payload || {}).forEach(([name, value]) => {
                    const match = String(name).match(/^translations\[([^\]]+)\]\[([^\]]+)\]$/);
                    if (!match) return;
                    const locale = String(match[1] || '').toLowerCase();
                    const field = String(match[2] || '');
                    const raw = Array.isArray(value) ? value.join(' ') : String(value ?? '');
                    const text = raw.trim();
                    if (!locale || text === '' || text === '[]') return;
                    byLocale[locale] ??= new Set();
                    if (field === 'title' || field === 'slug') byLocale[locale].add(field);
                    else if (field === 'blocks_json') byLocale[locale].add('blocks');
                    else if (field === 'rich_html') byLocale[locale].add('rich');
                    else if (field === 'custom_head_html') byLocale[locale].add('head');
                    else if (field.startsWith('meta_') || field === 'canonical_url') byLocale[locale].add('seo');
                });
                const parts = Object.entries(byLocale).map(([locale, fields]) => `${locale.toUpperCase()}: ${Array.from(fields).sort().join(', ') || 'used'}`);
                return parts.length > 0 ? `Локали: ${parts.join(' · ')}` : 'Локали: нет локального черновика';
            };

            const updateAutosaveSummary = (payload) => {
                autosaveSummaryChip.textContent = summarizeTranslationsPayload(payload || {});
                autosaveSummaryChip.title = autosaveSummaryChip.textContent;
            };

            const serializeForm = () => {
                builders.forEach((builder) => builder.sync());
                const fd = new FormData(form);
                const payload = {};
                for (const [name, value] of fd.entries()) {
                    if (name === '_token') continue;
                    if (value instanceof File) continue;
                    if (Object.prototype.hasOwnProperty.call(payload, name)) {
                        if (!Array.isArray(payload[name])) payload[name] = [payload[name]];
                        payload[name].push(String(value));
                    } else {
                        payload[name] = String(value);
                    }
                }
                return payload;
            };

            const currentFingerprint = () => JSON.stringify(serializeForm());

            const saveAutosave = ({ force = false } = {}) => {
                if (!autosaveStorage) return;
                try {
                    const fingerprint = currentFingerprint();
                    if (!force && fingerprint === lastSavedFingerprint) {
                        pendingAutosave = false;
                        return;
                    }
                    autosaveStorage.setItem(autosaveKey, JSON.stringify({
                        version: 1,
                        savedAt: new Date().toISOString(),
                        path: window.location.pathname,
                        payload: JSON.parse(fingerprint),
                    }));
                    updateAutosaveSummary(JSON.parse(fingerprint));
                    lastSavedFingerprint = fingerprint;
                    pendingAutosave = false;
                    setAutosaveStatus(`Автосохранено: ${new Date().toLocaleTimeString()}`);
                } catch (_) {
                    setAutosaveStatus('Автосохранение недоступно');
                }
            };

            const writeCreateHandoffMarker = () => {
                if (!autosaveSession || isEditMode) return;
                try {
                    autosaveSession.setItem(autosaveCreateHandoffKey, JSON.stringify({
                        key: autosaveKey,
                        path: window.location.pathname,
                        writtenAt: new Date().toISOString(),
                    }));
                } catch (_) {}
            };

            const clearCreateHandoffMarker = () => {
                if (!autosaveSession) return;
                try { autosaveSession.removeItem(autosaveCreateHandoffKey); } catch (_) {}
            };

            const cleanupCreateAutosaveAfterRedirect = () => {
                if (!autosaveSession || !autosaveStorage) return;
                let marker = null;
                try {
                    marker = JSON.parse(autosaveSession.getItem(autosaveCreateHandoffKey) || 'null');
                } catch (_) {
                    marker = null;
                }
                if (!marker || typeof marker !== 'object') return;
                const markerPath = String(marker.path || '');
                const markerKey = String(marker.key || '');
                if (!markerPath || !markerKey) {
                    clearCreateHandoffMarker();
                    return;
                }
                if (window.location.pathname === markerPath) {
                    return;
                }
                try {
                    autosaveStorage.removeItem(markerKey);
                } catch (_) {}
                clearCreateHandoffMarker();
            };

            const scheduleAutosave = () => {
                if (!autosaveStorage) return;
                pendingAutosave = true;
                setAutosaveStatus('Автосохранение…', 'saving');
                if (autosaveTimer) window.clearTimeout(autosaveTimer);
                autosaveTimer = window.setTimeout(() => saveAutosave(), 1200);
            };

            const assignControlValue = (el, value) => {
                if (!el) return;
                if (el instanceof HTMLSelectElement && el.multiple) {
                    const values = Array.isArray(value) ? value.map(String) : [String(value ?? '')];
                    Array.from(el.options).forEach((opt) => { opt.selected = values.includes(opt.value); });
                    return;
                }
                if (el instanceof HTMLInputElement && (el.type === 'checkbox' || el.type === 'radio')) {
                    const values = Array.isArray(value) ? value.map(String) : [String(value ?? '')];
                    el.checked = values.includes(el.value);
                    return;
                }
                el.value = Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '');
            };

            const applyAutosavePayload = (payload) => {
                if (!payload || typeof payload !== 'object') return false;
                Object.entries(payload).forEach(([name, value]) => {
                    const selector = `[name="${escapeSelectorName(name)}"]`;
                    const controls = Array.from(form.querySelectorAll(selector));
                    if (controls.length === 0) return;
                    if (controls.every((node) => node instanceof HTMLInputElement && (node.type === 'checkbox' || node.type === 'radio'))) {
                        controls.forEach((node) => assignControlValue(node, value));
                        return;
                    }
                    assignControlValue(controls[0], value);
                });

                builders.forEach((builder) => builder.reloadFromInputs && builder.reloadFromInputs());
                document.querySelectorAll('[data-page-seo-preview]').forEach((box) => {
                    const locale = box.getAttribute('data-page-seo-preview');
                    updateSeoPreview(locale);
                });
                updateAutosaveSummary(serializeForm());
                return true;
            };

            const recoverAutosaveIfNeeded = () => {
                if (!autosaveStorage) {
                    setAutosaveStatus('Автосохранение: localStorage недоступен');
                    return;
                }
                const raw = autosaveStorage.getItem(autosaveKey);
                if (!raw) {
                    setAutosaveStatus('Автосохранение: нет локального черновика');
                    updateAutosaveSummary({});
                    return;
                }
                try {
                    const snapshot = JSON.parse(raw);
                    if (!snapshot || typeof snapshot !== 'object' || typeof snapshot.payload !== 'object') return;
                    const snapshotFingerprint = JSON.stringify(snapshot.payload);
                    const liveFingerprint = currentFingerprint();
                    lastSavedFingerprint = snapshotFingerprint;

                    if (snapshotFingerprint === liveFingerprint) {
                        const stamp = snapshot.savedAt ? new Date(snapshot.savedAt) : null;
                        updateAutosaveSummary(snapshot.payload);
                        setAutosaveStatus(stamp && !Number.isNaN(stamp.getTime())
                            ? `Автосохранение: синхронизировано (${stamp.toLocaleTimeString()})`
                            : 'Автосохранение: синхронизировано');
                        return;
                    }

                    const stamp = snapshot.savedAt ? new Date(snapshot.savedAt) : null;
                    const stampText = stamp && !Number.isNaN(stamp.getTime()) ? stamp.toLocaleString() : 'неизвестное время';
                    const shouldRestore = templateSourceName
                        ? dialogs.confirm(`Применён шаблон \"${templateSourceName}\". Восстановить локальный черновик (${stampText}) вместо шаблона?`)
                        : dialogs.confirm(`Найден локальный черновик страницы (${stampText}). Восстановить?`);

                    if (!shouldRestore) {
                        setAutosaveStatus('Автосохранение: черновик найден');
                        return;
                    }

                    if (applyAutosavePayload(snapshot.payload)) {
                        pendingAutosave = false;
                        updateAutosaveSummary(snapshot.payload);
                        setAutosaveStatus('Черновик восстановлен', 'restored');
                    }
                } catch (_) {
                    setAutosaveStatus('Автосохранение: ошибка чтения');
                }
            };

            const removeAutosaveSnapshot = () => {
                if (!autosaveStorage) return;
                autosaveStorage.removeItem(autosaveKey);
                lastSavedFingerprint = '';
                pendingAutosave = false;
                updateAutosaveSummary({});
                setAutosaveStatus('Автосохранение очищено');
                if (!isEditMode) {
                    clearCreateHandoffMarker();
                }
            };

            const refreshPageUiAfterReset = () => {
                builders.forEach((builder) => builder.reloadFromInputs && builder.reloadFromInputs());
                document.querySelectorAll('[data-page-seo-preview]').forEach((box) => {
                    const locale = box.getAttribute('data-page-seo-preview');
                    updateSeoPreview(locale);
                });
            };

            clearAutosaveBtn.addEventListener('click', () => {
                removeAutosaveSnapshot();
            });
            resetAndClearBtn.addEventListener('click', () => {
                if (!dialogs.confirm('Сбросить форму к исходному состоянию страницы и очистить локальный autosave?')) {
                    return;
                }
                form.reset();
                if (serverInitialPayload) {
                    applyAutosavePayload(serverInitialPayload);
                } else {
                    refreshPageUiAfterReset();
                    updateAutosaveSummary(serializeForm());
                }
                removeAutosaveSnapshot();
            });

            const templateNameInput = saveTemplateForm ? saveTemplateForm.querySelector('[name=\"name\"]') : null;
            const payloadInput = saveTemplateForm ? saveTemplateForm.querySelector('[data-template-payload-json]') : null;
            const templateDescInput = saveTemplateForm ? saveTemplateForm.querySelector('[name=\"description\"]') : null;

            const readFieldValue = (name) => {
                const selector = `[name=\"${escapeSelectorName(name)}\"]`;
                const control = form.querySelector(selector);
                if (!control) return '';
                if (control instanceof HTMLSelectElement && control.multiple) {
                    return Array.from(control.selectedOptions).map((opt) => opt.value);
                }

                return control.value ?? '';
            };

            const collectTemplatePayload = () => {
                builders.forEach((builder) => builder.sync());

                const payload = {
                    entity: {
                        page_type: String(readFieldValue('page_type') || 'landing').trim() || 'landing',
                    },
                    translations: {},
                };

                supportedLocales.forEach((locale) => {
                    payload.translations[locale] = {
                        title: String(readFieldValue(`translations[${locale}][title]`) || '').trim(),
                        slug: String(readFieldValue(`translations[${locale}][slug]`) || '').trim(),
                        blocks_json: String(readFieldValue(`translations[${locale}][blocks_json]`) || '').trim(),
                        rich_html: String(readFieldValue(`translations[${locale}][rich_html]`) || ''),
                        meta_title: String(readFieldValue(`translations[${locale}][meta_title]`) || '').trim(),
                        meta_description: String(readFieldValue(`translations[${locale}][meta_description]`) || '').trim(),
                        canonical_url: String(readFieldValue(`translations[${locale}][canonical_url]`) || '').trim(),
                        custom_head_html: String(readFieldValue(`translations[${locale}][custom_head_html]`) || '').trim(),
                    };
                });

                return payload;
            };

            document.querySelector('[data-open-create-template-modal]')?.addEventListener('click', () => {
                openModal(createTemplateModal);
            });
            document.querySelector('[data-close-create-template-modal]')?.addEventListener('click', () => {
                closeModal(createTemplateModal);
            });
            createTemplateModal?.addEventListener('click', (event) => {
                if (event.target === createTemplateModal) closeModal(createTemplateModal);
            });

            document.querySelector('[data-open-save-template-modal]')?.addEventListener('click', () => {
                const activeLocale = form.querySelector('[data-locale-tab].active')?.getAttribute('data-locale-tab') || supportedLocales[0] || 'ru';
                const baseName = String(readFieldValue(`translations[${activeLocale}][title]`) || '').trim();
                if (templateNameInput) {
                    templateNameInput.value = baseName !== '' ? `${baseName} · template` : 'Новый шаблон страницы';
                }
                if (templateDescInput && !templateDescInput.value) {
                    templateDescInput.value = 'Шаблон страницы, сохранённый из редактора.';
                }
                if (payloadInput) {
                    payloadInput.value = JSON.stringify(collectTemplatePayload());
                }
                openModal(saveTemplateModal);
            });
            document.querySelector('[data-close-save-template-modal]')?.addEventListener('click', () => {
                closeModal(saveTemplateModal);
            });
            saveTemplateModal?.addEventListener('click', (event) => {
                if (event.target === saveTemplateModal) closeModal(saveTemplateModal);
            });

            saveTemplateForm?.addEventListener('submit', (event) => {
                if (payloadInput) {
                    payloadInput.value = JSON.stringify(collectTemplatePayload());
                    if (!payloadInput.value || payloadInput.value === '{}') {
                        event.preventDefault();
                        dialogs.alert('Не удалось собрать данные шаблона.');
                    }
                }
            });

            form.addEventListener('input', scheduleAutosave);
            form.addEventListener('change', scheduleAutosave);
            window.addEventListener('pagehide', () => {
                if (pendingAutosave) saveAutosave({ force: true });
            });
            window.addEventListener('beforeunload', () => {
                if (pendingAutosave) saveAutosave({ force: true });
            });

            cleanupCreateAutosaveAfterRedirect();
            serverInitialPayload = serializeForm();
            updateAutosaveSummary(serverInitialPayload);
            recoverAutosaveIfNeeded();

            form.addEventListener('submit', () => {
                builders.forEach((builder) => builder.sync());
                writeCreateHandoffMarker();
                saveAutosave({ force: true });
            });
        })();
