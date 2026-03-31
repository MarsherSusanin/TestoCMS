(() => {
    const shared = window.TestoCmsEditorShared;
    const form = document.getElementById('post-form');
    if (!form || !shared) return;
    const boot = shared.readBootPayload('testocms-post-editor-boot');
    const supportedLocales = Array.isArray(boot.supportedLocales) ? boot.supportedLocales : [];
    const templateSourceName = String(boot.templateSourceName || '');
    const markdownPreviewUrl = String(boot.markdownPreviewUrl || '');
    const markdownImportUrl = String(boot.markdownImportUrl || '');
    const postUrlPrefix = String(boot.postUrlPrefix || 'blog').replace(/^\/+|\/+$/g, '');
    const isEditMode = !!boot.isEditMode;
    const uiStorage = shared.createUiStorage('__testocms_post_ui_probe__');
    const uiRead = (key, fallback = null) => shared.uiRead(uiStorage, key, fallback);
    const uiWrite = (key, value) => shared.uiWrite(uiStorage, key, value);
    const dialogs = shared.dialogService;
    const openModal = shared.openModal;
    const closeModal = shared.closeModal;
            const POST_EDITOR_VIEW_KEY = 'testocms:admin:post-editor:view';
            const POST_MARKDOWN_VIEW_KEY = 'testocms:admin:post-markdown:view';
            const POST_SIDEBAR_KEY = 'testocms:admin:post-editor:sidebar';
            const createTemplateModal = document.querySelector('[data-create-template-modal]');
            const saveTemplateModal = document.querySelector('[data-save-template-modal]');
            const saveTemplateForm = document.querySelector('[data-save-template-form]');
            const markdownImportInput = form.querySelector('[data-markdown-import-input]');
    const csrfToken = form.querySelector('input[name="_token"]')?.value || '';

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

            const stripHtml = (html) => {
                const div = document.createElement('div');
                div.innerHTML = html || '';
                return (div.textContent || div.innerText || '').replace(/\\s+/g, ' ').trim();
            };
            const escapeSelectorName = (value) => (window.CSS && typeof window.CSS.escape === 'function')
                ? window.CSS.escape(value)
                : String(value).replace(/["\\\\]/g, '\\\\$&');
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            const safeLinkUrl = (value) => {
                const url = String(value ?? '').trim();
                if (!url) return '#';
                if (url.startsWith('/') || url.startsWith('#') || url.startsWith('?')) return url;
                if (/^(https?:|mailto:|tel:)/i.test(url)) return url;
                return '#';
            };
            const buildCtaAnchorHtml = ({ label, url, targetBlank, nofollow }) => {
                const safeUrl = escapeHtml(safeLinkUrl(url));
                const safeLabel = escapeHtml(String(label || '').trim() || 'CTA');
                const targetAttr = targetBlank ? ' target="_blank"' : '';
                const relParts = [];
                if (targetBlank) relParts.push('noopener', 'noreferrer');
                if (nofollow) relParts.push('nofollow');
                const relAttr = relParts.length ? ` rel="${escapeHtml(Array.from(new Set(relParts)).join(' '))}"` : '';
                return `<a class="cms-cta" href="${safeUrl}"${targetAttr}${relAttr}>${safeLabel}</a>`;
            };
            const insertTextareaSnippet = (textarea, snippet, { replaceSelection = true } = {}) => {
                if (!textarea) return;
                const value = String(textarea.value || '');
                const start = Number.isInteger(textarea.selectionStart) ? textarea.selectionStart : value.length;
                const end = Number.isInteger(textarea.selectionEnd) ? textarea.selectionEnd : value.length;
                const prefix = value.slice(0, start);
                const selected = value.slice(start, end);
                const suffix = value.slice(end);
                const insertion = typeof snippet === 'function' ? snippet(selected) : String(snippet || '');
                const nextValue = `${prefix}${replaceSelection ? insertion : selected + insertion}${suffix}`;
                textarea.value = nextValue;
                const cursor = prefix.length + insertion.length;
                textarea.focus();
                textarea.setSelectionRange(cursor, cursor);
            };

            const ctaModal = (() => {
                const overlay = document.getElementById('post-cta-modal');
                if (!overlay) return null;
                const labelInput = overlay.querySelector('[data-cta-modal-label]');
                const urlInput = overlay.querySelector('[data-cta-modal-url]');
                const targetInput = overlay.querySelector('[data-cta-modal-target]');
                const nofollowInput = overlay.querySelector('[data-cta-modal-nofollow]');
                const subtitle = overlay.querySelector('[data-cta-modal-subtitle]');
                const status = overlay.querySelector('[data-cta-modal-status]');
                const submitBtn = overlay.querySelector('[data-cta-modal-submit]');
                const cancelBtn = overlay.querySelector('[data-cta-modal-cancel]');
                const closeBtn = overlay.querySelector('[data-cta-modal-close]');
                let resolver = null;
                let currentMode = 'insert';

                const close = (result = null) => {
                    overlay.classList.remove('open');
                    overlay.setAttribute('aria-hidden', 'true');
                    const done = resolver;
                    resolver = null;
                    if (done) done(result);
                };

                const wireClose = (node) => node && node.addEventListener('click', () => close(null));
                wireClose(cancelBtn);
                wireClose(closeBtn);
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) close(null);
                });
                overlay.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        close(null);
                    }
                });

                submitBtn?.addEventListener('click', () => {
                    close({
                        label: labelInput?.value || '',
                        url: urlInput?.value || '',
                        targetBlank: !!targetInput?.checked,
                        nofollow: !!nofollowInput?.checked,
                        mode: currentMode,
                    });
                });

                return {
                    open(initial = {}) {
                        currentMode = initial.mode === 'update' ? 'update' : 'insert';
                        if (labelInput) labelInput.value = String(initial.label || 'Кнопка CTA');
                        if (urlInput) urlInput.value = String(initial.url || '/ru/blog');
                        if (targetInput) targetInput.checked = !!initial.targetBlank;
                        if (nofollowInput) nofollowInput.checked = !!initial.nofollow;
                        if (subtitle) subtitle.textContent = currentMode === 'update'
                            ? 'Редактирование выбранной CTA-кнопки'
                            : 'Вставка CTA-кнопки в текст поста';
                        if (status) status.textContent = currentMode === 'update' ? 'Редактирование текущего CTA' : 'Новый CTA';
                        if (submitBtn) submitBtn.textContent = currentMode === 'update' ? 'Обновить CTA' : 'Вставить CTA';
                        overlay.classList.add('open');
                        overlay.setAttribute('aria-hidden', 'false');
                        window.setTimeout(() => labelInput?.focus(), 0);

                        return new Promise((resolve) => {
                            resolver = resolve;
                        });
                    },
                };
            })();

            const updateCounter = (input) => {
                const target = document.querySelector(`[data-char-counter-for="${input.id}"]`);
                if (!target) return;
                target.textContent = `${(input.value || '').length} симв.`;
            };

            const wireLocaleTabs = () => {
                const tabs = Array.from(document.querySelectorAll('[data-locale-tab]'));
                tabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        const locale = tab.getAttribute('data-locale-tab');
                        tabs.forEach((t) => t.classList.toggle('active', t === tab));
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
                const shell = form.querySelector('[data-composer-shell="post"]');
                const sidebar = form.querySelector('[data-composer-sidebar="post"]');
                if (!shell || !sidebar) return;

                const tabButtons = Array.from(sidebar.querySelectorAll('[data-composer-sidebar-tab]'));
                const sections = Array.from(sidebar.querySelectorAll('[data-composer-sidebar-section]'));
                const toggleButtons = Array.from(document.querySelectorAll('[data-composer-sidebar-toggle]'));
                const seoHost = sidebar.querySelector('[data-post-seo-sidebar-host]');
                const statusSelect = form.querySelector('#post-status');
                const statusBadge = sidebar.querySelector('[data-composer-status-badge]');
                const seoLocaleMounts = {};

                const getActiveLocale = () => form.querySelector('[data-locale-tab].active')?.getAttribute('data-locale-tab')
                    || form.querySelector('[data-locale-tab]')?.getAttribute('data-locale-tab')
                    || null;

                if (seoHost) {
                    form.querySelectorAll('[data-post-locale-seo-panel]').forEach((panel) => {
                        const locale = panel.getAttribute('data-post-locale-seo-panel');
                        if (!locale) return;
                        const mount = document.createElement('div');
                        mount.setAttribute('data-post-seo-locale-mount', locale);
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
                    uiWrite(POST_SIDEBAR_KEY, shouldCollapse ? 'collapsed' : 'expanded');
                };

                const syncStatusBadge = () => {
                    if (!statusBadge || !statusSelect) return;
                    const value = String(statusSelect.value || 'draft').trim() || 'draft';
                    statusBadge.textContent = value;
                    statusBadge.className = `status-pill status-${value}`;
                };

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

                form.addEventListener('testocms:locale-changed', syncSeoLocale);
                statusSelect?.addEventListener('change', syncStatusBadge);
                syncSeoLocale();
                syncStatusBadge();
                const firstErrorTab = tabButtons.find((btn) => btn.classList.contains('has-error'))?.getAttribute('data-composer-sidebar-tab');
                setActiveTab(firstErrorTab || 'publish');
                setCollapsed(uiRead(POST_SIDEBAR_KEY, 'expanded') === 'collapsed');
            };

            const editorInstances = [];
            const editorByLocale = new Map();
            const markdownImportStatusByLocale = new Map();

            const commandInsert = (canvas, html) => {
                canvas.focus();
                document.execCommand('insertHTML', false, html);
            };
            const setMarkdownImportStatus = (locale, message, tone = 'idle') => {
                if (!locale) return;
                const statusEl = markdownImportStatusByLocale.get(locale)
                    || document.querySelector(`[data-markdown-import-status="${escapeSelectorName(locale)}"]`);
                if (!(statusEl instanceof HTMLElement)) {
                    return;
                }

                statusEl.textContent = String(message || '');
                statusEl.classList.remove('is-error', 'is-success');
                if (tone === 'error') {
                    statusEl.classList.add('is-error');
                }
                if (tone === 'success') {
                    statusEl.classList.add('is-success');
                }
            };
            const isMarkdownFile = (file) => {
                if (!(file instanceof File)) {
                    return false;
                }

                const normalizedName = String(file.name || '').toLowerCase();
                return ['.md', '.markdown', '.txt'].some((suffix) => normalizedName.endsWith(suffix));
            };
            const importMarkdownFile = async (file, locale) => {
                if (!(file instanceof File) || !locale) {
                    return;
                }

                if (!isMarkdownFile(file)) {
                    setMarkdownImportStatus(locale, 'Нужен файл .md, .markdown или .txt.', 'error');
                    dialogs.alert('Поддерживаются только Markdown и текстовые файлы.');
                    return;
                }

                const editor = editorByLocale.get(locale);
                if (!editor) {
                    return;
                }

                const formData = new FormData();
                formData.append('_token', csrfToken);
                formData.append('locale', locale);
                formData.append('markdown_file', file);
                setMarkdownImportStatus(locale, `Импортируем ${file.name}...`);

                try {
                    const response = await fetch(markdownImportUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    if (!response.ok) {
                        throw new Error('Markdown import failed');
                    }
                    const payload = await response.json();
                    editor.importMarkdownPayload(payload?.data || {});
                    setMarkdownImportStatus(locale, `Импортировано: ${file.name}`, 'success');
                } catch (_) {
                    setMarkdownImportStatus(locale, 'Не удалось импортировать файл.', 'error');
                    dialogs.alert('Не удалось импортировать Markdown-файл.');
                } finally {
                    if (markdownImportInput) {
                        markdownImportInput.value = '';
                    }
                }
            };
            const openMarkdownImportPicker = (locale) => {
                if (!markdownImportInput || !locale) {
                    return;
                }

                pendingMarkdownImportLocale = locale;
                markdownImportInput.value = '';
                markdownImportInput.click();
            };

            const postCanonicalPath = (locale, rawSlug) => {
                const safeLocale = String(locale || '').trim().replace(/^\/+|\/+$/g, '');
                const safeSlug = String(rawSlug || '').trim().replace(/^\/+|\/+$/g, '') || 'slug';
                const postPrefix = postUrlPrefix;

                return `/${safeLocale}/${postPrefix}/${safeSlug}`;
            };
            const postCanonicalAbsolute = (locale, rawSlug) => `${window.location.origin}${postCanonicalPath(locale, rawSlug)}`;
            const normalizeComparableUrl = (value) => {
                const current = String(value || '').trim();
                if (!current) return '';
                const stripped = current.replace(/\/+$/, '');

                return stripped || current;
            };
            const syncPostSlugFromTitle = (locale) => {
                const titleField = document.querySelector(`[data-post-title="${locale}"]`);
                const slugField = document.querySelector(`[data-post-slug="${locale}"]`);
                if (!titleField || !slugField) return;
                if (slugField.dataset.userEdited && slugField.value.trim() !== '') return;
                slugField.value = slugify(titleField.value);
            };
            const syncPostCanonicalFromSlug = (locale) => {
                const slugField = document.querySelector(`[data-post-slug="${locale}"]`);
                const canonicalField = document.querySelector(`[data-post-canonical="${locale}"]`);
                if (!slugField || !canonicalField) return;
                const slugValue = slugField.value.trim();
                if (slugValue === '') return;
                if (canonicalField.dataset.userEdited && canonicalField.value.trim() !== '') return;
                canonicalField.value = postCanonicalAbsolute(locale, slugValue);
            };
            const refreshPostSeoFieldModes = (locale) => {
                const titleField = document.querySelector(`[data-post-title="${locale}"]`);
                const slugField = document.querySelector(`[data-post-slug="${locale}"]`);
                const canonicalField = document.querySelector(`[data-post-canonical="${locale}"]`);

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
                    const baseSlug = (slugField?.value || '').trim() || slugify(titleField ? titleField.value : '');
                    const currentCanonical = canonicalField.value.trim();
                    const expectedPath = baseSlug ? postCanonicalPath(locale, baseSlug) : '';
                    const expectedAbsolute = baseSlug ? postCanonicalAbsolute(locale, baseSlug) : '';
                    const comparable = normalizeComparableUrl(currentCanonical);
                    if (
                        comparable === ''
                        || (expectedPath !== '' && comparable === normalizeComparableUrl(expectedPath))
                        || (expectedAbsolute !== '' && comparable === normalizeComparableUrl(expectedAbsolute))
                    ) {
                        delete canonicalField.dataset.userEdited;
                    } else {
                        canonicalField.dataset.userEdited = '1';
                    }
                }
            };

            const updateSeoPreview = (locale) => {
                const title = document.querySelector(`[data-post-title="${locale}"]`);
                const slug = document.querySelector(`[data-post-slug="${locale}"]`);
                const metaTitle = document.querySelector(`[data-post-meta-title="${locale}"]`);
                const metaDescription = document.querySelector(`[data-post-meta-description="${locale}"]`);
                const excerpt = document.querySelector(`[data-post-excerpt="${locale}"]`);
                const canonical = document.querySelector(`[data-post-canonical="${locale}"]`);
                const preview = document.querySelector(`[data-seo-preview="${locale}"]`);
                if (!preview || !title || !slug || !metaTitle || !metaDescription || !excerpt) return;

                const previewTitle = preview.querySelector('[data-seo-preview-title]');
                const previewUrl = preview.querySelector('[data-seo-preview-url]');
                const previewDesc = preview.querySelector('[data-seo-preview-desc]');

                refreshPostSeoFieldModes(locale);
                syncPostSlugFromTitle(locale);
                syncPostCanonicalFromSlug(locale);

                const finalTitle = (metaTitle.value || title.value || 'Заголовок поста').trim();
                const finalSlug = (slug.value || 'slug').trim();
                const finalDesc = (metaDescription.value || excerpt.value || 'Здесь появится превью meta description.').trim();
                const baseCanonical = (canonical && canonical.value.trim()) || postCanonicalAbsolute(locale, finalSlug);

                if (previewTitle) previewTitle.textContent = finalTitle;
                if (previewUrl) previewUrl.textContent = baseCanonical;
                if (previewDesc) previewDesc.textContent = finalDesc;
            };

            const initPostEditor = (root) => {
                const locale = root.getAttribute('data-post-editor');
                const htmlToolbar = root.querySelector('[data-editor-toolbar]');
                const htmlCanvas = root.querySelector('[data-html-canvas]');
                const htmlSource = root.querySelector('[data-html-source]');
                const htmlPreview = root.querySelector('[data-html-preview]');
                const htmlLayout = root.querySelector('[data-editor-layout]');
                const htmlPaneEditor = root.querySelector('[data-pane="editor"]');
                const htmlPanePreview = root.querySelector('[data-pane="preview"]');
                const htmlPreviewFrame = root.querySelector('[data-preview-frame]');
                const htmlViewToggle = root.querySelector('[data-editor-view-toggle]');
                const markdownToolbar = root.querySelector('[data-markdown-toolbar]');
                const markdownSource = root.querySelector('[data-markdown-source]');
                const markdownPreview = root.querySelector('[data-markdown-preview]');
                const markdownLayout = root.querySelector('[data-markdown-layout]');
                const markdownPaneEditor = root.querySelector('[data-markdown-pane="editor"]');
                const markdownPanePreview = root.querySelector('[data-markdown-pane="preview"]');
                const markdownPreviewFrame = root.querySelector('[data-markdown-preview-frame]');
                const markdownViewToggle = root.querySelector('[data-markdown-view-toggle]');
                const markdownPreviewStatus = root.querySelector('[data-markdown-preview-status]');
                const deviceToggle = root.querySelector('[data-editor-device-toggle]');
                const formatInput = root.querySelector(`[data-content-format="${locale}"]`);
                const formatToggle = root.querySelector(`[data-content-format-toggle="${locale}"]`);

                if (
                    !htmlToolbar || !htmlCanvas || !htmlSource || !htmlPreview || !htmlLayout || !htmlPaneEditor || !htmlPanePreview || !htmlPreviewFrame || !htmlViewToggle
                    || !markdownToolbar || !markdownSource || !markdownPreview || !markdownLayout || !markdownPaneEditor || !markdownPanePreview || !markdownPreviewFrame || !markdownViewToggle
                    || !deviceToggle || !formatInput || !formatToggle
                ) {
                    return;
                }

                let savedRange = null;
                let markdownAbort = null;
                let markdownTimer = null;
                let lastMarkdownPayload = '';
                let lastRenderedMarkdownHtml = htmlSource.value || '';
                let lastRenderedMarkdownPlain = '';

                const captureSelectionRange = () => {
                    const selection = window.getSelection ? window.getSelection() : null;
                    if (!selection || selection.rangeCount === 0) return;
                    const range = selection.getRangeAt(0);
                    if (!htmlCanvas.contains(range.commonAncestorContainer)) return;
                    savedRange = range.cloneRange();
                };
                const restoreSelectionRange = () => {
                    if (!savedRange || !window.getSelection) return false;
                    const selection = window.getSelection();
                    if (!selection) return false;
                    selection.removeAllRanges();
                    selection.addRange(savedRange);
                    return true;
                };

                const getContentFormat = () => String(formatInput.value || 'html').trim() === 'markdown' ? 'markdown' : 'html';

                const syncCanvasToSource = () => {
                    htmlSource.value = htmlCanvas.innerHTML;
                    htmlPreview.innerHTML = htmlSource.value;
                };

                const syncSourceToCanvas = () => {
                    htmlCanvas.innerHTML = htmlSource.value;
                    htmlPreview.innerHTML = htmlSource.value;
                };

                const setHtmlView = (mode) => {
                    htmlViewToggle.querySelectorAll('button').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-editor-view') === mode));
                    htmlSource.style.display = mode === 'source' ? 'block' : 'none';
                    htmlCanvas.style.display = mode === 'source' ? 'none' : 'block';
                    deviceToggle.style.display = (mode === 'split' || mode === 'preview') ? '' : 'none';
                    uiWrite(POST_EDITOR_VIEW_KEY, mode);

                    if (mode === 'edit') {
                        htmlLayout.style.gridTemplateColumns = '1fr';
                        htmlPanePreview.style.display = 'none';
                        htmlPaneEditor.style.display = 'block';
                    } else if (mode === 'preview') {
                        htmlLayout.style.gridTemplateColumns = '1fr';
                        htmlPaneEditor.style.display = 'none';
                        htmlPanePreview.style.display = 'flex';
                    } else {
                        htmlLayout.style.gridTemplateColumns = '';
                        htmlPaneEditor.style.display = 'block';
                        htmlPanePreview.style.display = 'flex';
                    }
                };

                const setMarkdownView = (mode) => {
                    markdownViewToggle.querySelectorAll('button').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-markdown-view') === mode));
                    deviceToggle.style.display = (mode === 'split' || mode === 'preview') ? '' : 'none';
                    uiWrite(POST_MARKDOWN_VIEW_KEY, mode);

                    if (mode === 'edit') {
                        markdownLayout.style.gridTemplateColumns = '1fr';
                        markdownPanePreview.style.display = 'none';
                        markdownPaneEditor.style.display = 'block';
                    } else if (mode === 'preview') {
                        markdownLayout.style.gridTemplateColumns = '1fr';
                        markdownPaneEditor.style.display = 'none';
                        markdownPanePreview.style.display = 'flex';
                    } else {
                        markdownLayout.style.gridTemplateColumns = '';
                        markdownPaneEditor.style.display = 'block';
                        markdownPanePreview.style.display = 'flex';
                    }
                };

                const setDevice = (device) => {
                    deviceToggle.querySelectorAll('button').forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-editor-device') === device));
                    [htmlPreviewFrame, markdownPreviewFrame].forEach((frame) => {
                        if (!frame) return;
                        frame.classList.remove('device-desktop', 'device-tablet', 'device-mobile');
                        frame.classList.add(`device-${device}`);
                    });
                };

                const syncRenderedMarkdownCache = (html, plain) => {
                    lastRenderedMarkdownHtml = String(html || '');
                    lastRenderedMarkdownPlain = String(plain || '');
                    root.dataset.markdownRenderedHtml = lastRenderedMarkdownHtml;
                    root.dataset.markdownRenderedPlain = lastRenderedMarkdownPlain;
                    htmlSource.value = lastRenderedMarkdownHtml;
                };

                const renderMarkdownPreview = ({ immediate = false } = {}) => {
                    const run = () => {
                        const payload = JSON.stringify({ markdown: markdownSource.value || '' });
                        if (!immediate && payload === lastMarkdownPayload) {
                            return;
                        }
                        lastMarkdownPayload = payload;

                        if (markdownAbort) {
                            markdownAbort.abort();
                        }

                        markdownPreviewStatus.textContent = 'Рендерим…';
                        markdownAbort = new AbortController();
                        fetch(markdownPreviewUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            credentials: 'same-origin',
                            body: payload,
                            signal: markdownAbort.signal,
                        })
                            .then((response) => response.ok ? response.json() : Promise.reject(response))
                            .then((data) => {
                                const html = String(data?.data?.html || '');
                                const plain = String(data?.data?.plain || '');
                                markdownPreview.innerHTML = html;
                                syncRenderedMarkdownCache(html, plain);
                                markdownPreviewStatus.textContent = 'Синхронизировано';
                            })
                            .catch((error) => {
                                if (error?.name === 'AbortError') return;
                                markdownPreviewStatus.textContent = 'Ошибка preview';
                            });
                    };

                    if (immediate) {
                        run();
                        return;
                    }

                    if (markdownTimer) window.clearTimeout(markdownTimer);
                    markdownTimer = window.setTimeout(run, 350);
                };

                const applyContentFormatUi = (format, { preserveView = false } = {}) => {
                    const isMarkdown = format === 'markdown';
                    formatInput.value = format;
                    formatToggle.querySelectorAll('button').forEach((btn) => {
                        btn.classList.toggle('active', btn.getAttribute('data-content-format-option') === format);
                    });
                    htmlToolbar.classList.toggle('is-hidden', isMarkdown);
                    htmlLayout.classList.toggle('is-hidden', isMarkdown);
                    htmlViewToggle.classList.toggle('is-hidden', isMarkdown);
                    markdownToolbar.classList.toggle('is-hidden', !isMarkdown);
                    markdownLayout.classList.toggle('is-hidden', !isMarkdown);
                    markdownViewToggle.classList.toggle('is-hidden', !isMarkdown);

                    if (isMarkdown) {
                        const savedView = preserveView ? uiRead(POST_MARKDOWN_VIEW_KEY, 'split') : 'split';
                        setMarkdownView(['edit', 'preview', 'split'].includes(savedView) ? savedView : 'split');
                        renderMarkdownPreview({ immediate: true });
                    } else {
                        syncSourceToCanvas();
                        const savedView = preserveView ? uiRead(POST_EDITOR_VIEW_KEY, 'edit') : 'edit';
                        setHtmlView(['edit', 'preview', 'split', 'source'].includes(savedView) ? savedView : 'edit');
                    }
                };

                const switchContentFormat = (nextFormat) => {
                    const currentFormat = getContentFormat();
                    if (nextFormat === currentFormat) {
                        applyContentFormatUi(currentFormat, { preserveView: true });
                        return;
                    }

                    if (currentFormat === 'html') {
                        syncCanvasToSource();
                        const hasHtml = stripHtml(htmlSource.value).trim() !== '';
                        if (hasHtml && !dialogs.confirm('Переключение на Markdown не конвертирует HTML обратно автоматически. Текущее HTML-содержимое будет перенесено как raw HTML в markdown-источник. Продолжить?')) {
                            return;
                        }
                        if (hasHtml && markdownSource.value.trim() === '') {
                            markdownSource.value = htmlSource.value.trim();
                        }
                    } else {
                        const hasMarkdown = markdownSource.value.trim() !== '';
                        if (hasMarkdown && !dialogs.confirm('Переключение на Visual HTML использует отрендеренный HTML из Markdown preview. Исходный Markdown останется только если вы не будете сохранять пост в HTML-режиме. Продолжить?')) {
                            return;
                        }
                        if (htmlSource.value.trim() === '') {
                            htmlSource.value = lastRenderedMarkdownHtml || markdownPreview.innerHTML || htmlSource.value;
                        }
                    }

                    applyContentFormatUi(nextFormat);
                };

                htmlCanvas.innerHTML = htmlSource.value || '';
                htmlPreview.innerHTML = htmlSource.value || '';
                markdownPreview.innerHTML = htmlSource.value || '';
                syncRenderedMarkdownCache(htmlSource.value || '', stripHtml(htmlSource.value || ''));

                htmlToolbar.addEventListener('mousedown', (e) => {
                    if (e.target.closest('button')) {
                        e.preventDefault();
                    }
                });

                htmlToolbar.addEventListener('click', (e) => {
                    const button = e.target.closest('button');
                    if (!button) return;

                    const cmd = button.getAttribute('data-cmd');
                    const action = button.getAttribute('data-action');
                    const snippet = button.getAttribute('data-snippet');
                    const insert = button.getAttribute('data-insert');

                    htmlCanvas.focus();

                    if (cmd) {
                        document.execCommand(cmd, false, null);
                        syncCanvasToSource();
                        return;
                    }

                    if (action === 'link') {
                        const url = window.prompt('URL ссылки');
                        if (url) {
                            document.execCommand('createLink', false, url);
                            syncCanvasToSource();
                        }
                        return;
                    }

                    if (action === 'image') {
                        captureSelectionRange();
                        if (window.TestoCmsMediaPicker?.open) {
                            window.TestoCmsMediaPicker.open({
                                accept: 'image',
                                multiple: false,
                                title: 'Выбор изображения для поста',
                                subtitle: 'Выберите изображение из Assets для вставки в текст',
                            }).then((asset) => {
                                if (!asset || !asset.public_url) return;
                                htmlCanvas.focus();
                                restoreSelectionRange();
                                const url = String(asset.public_url || '').replace(/"/g, '&quot;');
                                const alt = String(asset.alt || asset.title || '').replace(/"/g, '&quot;');
                                const caption = asset.caption
                                    ? `<figcaption>${String(asset.caption).replace(/</g, '&lt;').replace(/>/g, '&gt;')}</figcaption>`
                                    : '';
                                commandInsert(htmlCanvas, `<figure><img src="${url}" alt="${alt}" loading="lazy">${caption}</figure>`);
                                syncCanvasToSource();
                            }).catch(() => {});
                            return;
                        }

                        const url = window.prompt('URL изображения');
                        if (!url) return;
                        const alt = window.prompt('Alt текст (необязательно)') || '';
                        commandInsert(htmlCanvas, `<figure><img src="${url.replace(/"/g, '&quot;')}" alt="${alt.replace(/"/g, '&quot;')}" loading="lazy"></figure>`);
                        syncCanvasToSource();
                        return;
                    }

                    if (insert === 'hr') {
                        commandInsert(htmlCanvas, '<hr>');
                        syncCanvasToSource();
                        return;
                    }

                    if (snippet) {
                        if (snippet === 'cta') {
                            captureSelectionRange();
                            const selection = window.getSelection ? window.getSelection() : null;
                            let selectedAnchor = null;
                            if (selection && selection.anchorNode) {
                                const baseNode = selection.anchorNode.nodeType === 1
                                    ? selection.anchorNode
                                    : selection.anchorNode.parentElement;
                                if (baseNode && baseNode.closest) {
                                    const anchor = baseNode.closest('a.cms-cta');
                                    if (anchor && htmlCanvas.contains(anchor)) {
                                        selectedAnchor = anchor;
                                    }
                                }
                            }

                            const rel = String(selectedAnchor?.getAttribute('rel') || '').toLowerCase();
                            const initial = {
                                mode: selectedAnchor ? 'update' : 'insert',
                                label: selectedAnchor ? (selectedAnchor.textContent || '').trim() : 'Кнопка CTA',
                                url: selectedAnchor?.getAttribute('href') || `/${locale}/blog`,
                                targetBlank: selectedAnchor?.getAttribute('target') === '_blank',
                                nofollow: rel.includes('nofollow'),
                            };

                            if (!ctaModal?.open) {
                                commandInsert(htmlCanvas, `<p>${buildCtaAnchorHtml(initial)}</p>`);
                                syncCanvasToSource();
                                return;
                            }

                            ctaModal.open(initial).then((result) => {
                                if (!result) return;
                                const anchorHtml = buildCtaAnchorHtml(result);
                                if (selectedAnchor && selectedAnchor.isConnected) {
                                    selectedAnchor.outerHTML = anchorHtml;
                                    syncCanvasToSource();
                                    return;
                                }
                                htmlCanvas.focus();
                                restoreSelectionRange();
                                commandInsert(htmlCanvas, `<p>${anchorHtml}</p>`);
                                syncCanvasToSource();
                            }).catch(() => {});
                            return;
                        }

                        const snippets = {
                            faq: '<section class="cms-faq"><details><summary>Вопрос</summary><div><p>Ответ с форматированием.</p></div></details></section>',
                            table: '<table><tbody><tr><td>Колонка 1</td><td>Колонка 2</td></tr><tr><td>Значение</td><td>Значение</td></tr></tbody></table>',
                        };
                        commandInsert(htmlCanvas, snippets[snippet] || '');
                        syncCanvasToSource();
                    }
                });

                markdownToolbar.addEventListener('click', (e) => {
                    const button = e.target.closest('button[data-markdown-snippet]');
                    if (!button) return;
                    const kind = button.getAttribute('data-markdown-snippet');

                    if (kind === 'image' && window.TestoCmsMediaPicker?.open) {
                        window.TestoCmsMediaPicker.open({
                            accept: 'image',
                            multiple: false,
                            title: 'Выбор изображения для Markdown',
                            subtitle: 'Вставка markdown-ссылки на изображение из Assets',
                        }).then((asset) => {
                            if (!asset || !asset.public_url) return;
                            const alt = String(asset.alt || asset.title || 'Image');
                            insertTextareaSnippet(markdownSource, `![${alt}](${asset.public_url})`);
                            renderMarkdownPreview();
                        }).catch(() => {});
                        return;
                    }

                    const snippetMap = {
                        h2: (selected) => `## ${selected || 'Заголовок'}\n`,
                        bold: (selected) => `**${selected || 'жирный текст'}**`,
                        italic: (selected) => `_${selected || 'курсив'}_`,
                        link: (selected) => `[${selected || 'ссылка'}](https://example.com)`,
                        image: () => '![Alt](https://example.com/image.jpg)',
                        list: () => '- Пункт 1\n- Пункт 2\n- Пункт 3',
                        code: () => "```html\n<div>code</div>\n```",
                        table: () => "| Колонка 1 | Колонка 2 |\n| --- | --- |\n| Значение | Значение |",
                        faq: () => "<section class=\"cms-faq\">\n<details>\n<summary>Вопрос</summary>\n<div><p>Ответ с форматированием.</p></div>\n</details>\n</section>",
                        cta: () => `<p><a class="cms-cta" href="/${locale}/blog">Кнопка CTA</a></p>`,
                    };
                    insertTextareaSnippet(markdownSource, snippetMap[kind] || '');
                    renderMarkdownPreview();
                });

                const formatSelect = htmlToolbar.querySelector('[data-format-block]');
                if (formatSelect) {
                    formatSelect.addEventListener('change', () => {
                        const value = formatSelect.value;
                        if (!value) return;
                        htmlCanvas.focus();
                        document.execCommand('formatBlock', false, value);
                        formatSelect.value = '';
                        syncCanvasToSource();
                    });
                }

                htmlCanvas.addEventListener('input', syncCanvasToSource);
                htmlSource.addEventListener('input', syncSourceToCanvas);
                htmlCanvas.addEventListener('keyup', captureSelectionRange);
                htmlCanvas.addEventListener('mouseup', captureSelectionRange);
                htmlCanvas.addEventListener('focus', captureSelectionRange);
                htmlCanvas.addEventListener('blur', captureSelectionRange);
                markdownSource.addEventListener('input', () => {
                    markdownPreviewStatus.textContent = 'Ожидает рендер…';
                    renderMarkdownPreview();
                });

                htmlViewToggle.addEventListener('click', (e) => {
                    const button = e.target.closest('button[data-editor-view]');
                    if (!button) return;
                    setHtmlView(button.getAttribute('data-editor-view'));
                });
                markdownViewToggle.addEventListener('click', (e) => {
                    const button = e.target.closest('button[data-markdown-view]');
                    if (!button) return;
                    setMarkdownView(button.getAttribute('data-markdown-view'));
                });

                formatToggle.addEventListener('click', (e) => {
                    const button = e.target.closest('button[data-content-format-option]');
                    if (!button) return;
                    switchContentFormat(button.getAttribute('data-content-format-option'));
                });

                deviceToggle.addEventListener('click', (e) => {
                    const button = e.target.closest('button[data-editor-device]');
                    if (!button) return;
                    setDevice(button.getAttribute('data-editor-device'));
                });

                const applyImportedMarkdown = (payload) => {
                    const titleField = document.querySelector(`[data-post-title="${locale}"]`);
                    const slugField = document.querySelector(`[data-post-slug="${locale}"]`);
                    const excerptField = document.querySelector(`[data-post-excerpt="${locale}"]`);
                    const metaTitleField = document.querySelector(`[data-post-meta-title="${locale}"]`);
                    const metaDescriptionField = document.querySelector(`[data-post-meta-description="${locale}"]`);
                    const canonicalField = document.querySelector(`[data-post-canonical="${locale}"]`);
                    const customHeadField = document.querySelector(`#post-${locale}-custom-head`);

                    if (payload.title !== null && titleField) titleField.value = String(payload.title || '');
                    if (payload.slug !== null && slugField) {
                        slugField.value = String(payload.slug || '');
                        if (slugField.value.trim() === '') delete slugField.dataset.userEdited;
                    }
                    if (payload.excerpt !== null && excerptField) excerptField.value = String(payload.excerpt || '');
                    if (payload.meta_title !== null && metaTitleField) metaTitleField.value = String(payload.meta_title || '');
                    if (payload.meta_description !== null && metaDescriptionField) metaDescriptionField.value = String(payload.meta_description || '');
                    if (payload.canonical_url !== null && canonicalField) {
                        canonicalField.value = String(payload.canonical_url || '');
                        delete canonicalField.dataset.userEdited;
                    }
                    if (payload.custom_head_html !== null && customHeadField) customHeadField.value = String(payload.custom_head_html || '');

                    markdownSource.value = String(payload.content_markdown || '');
                    syncRenderedMarkdownCache(String(payload.content_html || ''), String(payload.content_plain || ''));
                    markdownPreview.innerHTML = String(payload.content_html || '');
                    applyContentFormatUi('markdown');
                    document.querySelectorAll('[data-char-counter-for]').forEach((counter) => {
                        const input = document.getElementById(counter.getAttribute('data-char-counter-for'));
                        if (input) updateCounter(input);
                    });
                    updateSeoPreview(locale);
                };

                const instance = {
                    locale,
                    sync() {
                        if (getContentFormat() === 'markdown') {
                            htmlSource.value = lastRenderedMarkdownHtml || htmlSource.value;
                            return;
                        }
                        syncCanvasToSource();
                    },
                    refresh() {
                        syncSourceToCanvas();
                        applyContentFormatUi(getContentFormat(), { preserveView: true });
                        if (getContentFormat() === 'markdown') {
                            renderMarkdownPreview({ immediate: true });
                        }
                    },
                    getFormat: getContentFormat,
                    getMarkdownPlain() {
                        return lastRenderedMarkdownPlain || stripHtml(markdownPreview.innerHTML || '');
                    },
                    importMarkdownPayload: applyImportedMarkdown,
                };

                editorInstances.push(instance);
                editorByLocale.set(locale, instance);

                const savedHtmlView = uiRead(POST_EDITOR_VIEW_KEY, 'edit');
                setHtmlView(['edit', 'preview', 'split', 'source'].includes(savedHtmlView) ? savedHtmlView : 'edit');
                const savedMarkdownView = uiRead(POST_MARKDOWN_VIEW_KEY, 'split');
                setMarkdownView(['edit', 'preview', 'split'].includes(savedMarkdownView) ? savedMarkdownView : 'split');
                setDevice('desktop');
                applyContentFormatUi(getContentFormat(), { preserveView: true });
                updateSeoPreview(locale);
            };

            wireLocaleTabs();
            setupComposerSidebar();

            document.querySelectorAll('[data-post-editor]').forEach((root) => initPostEditor(root));
            let pendingMarkdownImportLocale = null;

            document.querySelectorAll('[data-markdown-import-status]').forEach((statusEl) => {
                const locale = statusEl.getAttribute('data-markdown-import-status');
                if (!locale) return;
                markdownImportStatusByLocale.set(locale, statusEl);
            });

            document.querySelectorAll('[data-markdown-import-trigger]').forEach((button) => {
                button.addEventListener('click', () => {
                    openMarkdownImportPicker(button.getAttribute('data-markdown-import-trigger'));
                });
            });

            document.querySelectorAll('[data-markdown-import-zone]').forEach((zone) => {
                const locale = zone.getAttribute('data-markdown-import-zone');
                if (!locale) return;

                const setDragState = (active) => {
                    zone.classList.toggle('is-dragover', !!active);
                };
                const preventDefaults = (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                };

                ['dragenter', 'dragover'].forEach((eventName) => {
                    zone.addEventListener(eventName, (event) => {
                        preventDefaults(event);
                        setDragState(true);
                    });
                });

                ['dragleave', 'dragend'].forEach((eventName) => {
                    zone.addEventListener(eventName, (event) => {
                        preventDefaults(event);
                        if (!(event.relatedTarget instanceof Node) || !zone.contains(event.relatedTarget)) {
                            setDragState(false);
                        }
                    });
                });

                zone.addEventListener('drop', (event) => {
                    preventDefaults(event);
                    setDragState(false);
                    const file = event.dataTransfer?.files?.[0] || null;
                    if (!file) return;
                    importMarkdownFile(file, locale);
                });

                zone.addEventListener('click', (event) => {
                    if (event.target instanceof Element && event.target.closest('button')) {
                        return;
                    }
                    openMarkdownImportPicker(locale);
                });
            });

            markdownImportInput?.addEventListener('change', async () => {
                const file = markdownImportInput.files && markdownImportInput.files[0];
                const locale = pendingMarkdownImportLocale;
                pendingMarkdownImportLocale = null;
                if (!file || !locale) return;
                await importMarkdownFile(file, locale);
            });

            document.querySelectorAll('[data-slug-generate]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const locale = btn.getAttribute('data-slug-generate');
                    const title = document.querySelector(`[data-post-title="${locale}"]`);
                    const slug = document.querySelector(`[data-post-slug="${locale}"]`);
                    if (!title || !slug) return;
                    slug.value = slugify(title.value);
                    if (slug.value.trim() === '') {
                        delete slug.dataset.userEdited;
                    }
                    updateSeoPreview(locale);
                });
            });

            document.querySelectorAll('[data-excerpt-generate]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const locale = btn.getAttribute('data-excerpt-generate');
                    const editorRoot = document.querySelector(`[data-post-editor="${locale}"]`);
                    const excerpt = document.querySelector(`[data-post-excerpt="${locale}"]`);
                    if (!editorRoot || !excerpt) return;
                    const editor = editorByLocale.get(locale);
                    const format = editor?.getFormat ? editor.getFormat() : 'html';
                    const source = editorRoot.querySelector('[data-html-source]');
                    const text = format === 'markdown'
                        ? String(editor?.getMarkdownPlain ? editor.getMarkdownPlain() : '')
                        : stripHtml(source ? source.value : '');
                    excerpt.value = text.slice(0, 180);
                    updateCounter(excerpt);
                    updateSeoPreview(locale);
                });
            });

            document.querySelectorAll('[data-post-title], [data-post-slug], [data-post-meta-title], [data-post-meta-description], [data-post-excerpt], [data-post-canonical]').forEach((input) => {
                input.addEventListener('input', () => {
                    const locale = input.getAttribute('data-post-title')
                        || input.getAttribute('data-post-slug')
                        || input.getAttribute('data-post-meta-title')
                        || input.getAttribute('data-post-meta-description')
                        || input.getAttribute('data-post-excerpt')
                        || input.getAttribute('data-post-canonical');
                    if (input.dataset.postSlug) {
                        if (input.value.trim() === '') {
                            delete input.dataset.userEdited;
                        } else {
                            input.dataset.userEdited = '1';
                        }
                    }
                    if (input.dataset.postCanonical) {
                        const titleField = document.querySelector(`[data-post-title="${locale}"]`);
                        const slugField = document.querySelector(`[data-post-slug="${locale}"]`);
                        const baseSlug = (slugField?.value || '').trim() || slugify(titleField ? titleField.value : '');
                        const comparable = normalizeComparableUrl(input.value);
                        const expectedPath = baseSlug ? postCanonicalPath(locale, baseSlug) : '';
                        const expectedAbsolute = baseSlug ? postCanonicalAbsolute(locale, baseSlug) : '';
                        if (
                            comparable === ''
                            || (expectedPath !== '' && comparable === normalizeComparableUrl(expectedPath))
                            || (expectedAbsolute !== '' && comparable === normalizeComparableUrl(expectedAbsolute))
                        ) {
                            delete input.dataset.userEdited;
                        } else {
                            input.dataset.userEdited = '1';
                        }
                    }
                    updateSeoPreview(locale);
                    if (input.id) updateCounter(input);
                });
            });

            document.querySelectorAll('[data-post-slug]').forEach((input) => {
                const locale = input.getAttribute('data-post-slug');
                const titleField = document.querySelector(`[data-post-title="${locale}"]`);
                const currentSlug = input.value.trim();
                const suggestedSlug = slugify(titleField ? titleField.value : '');
                if (currentSlug === '' || (suggestedSlug !== '' && currentSlug === suggestedSlug)) {
                    delete input.dataset.userEdited;
                } else {
                    input.dataset.userEdited = '1';
                }
            });

            document.querySelectorAll('[data-post-canonical]').forEach((input) => {
                const locale = input.getAttribute('data-post-canonical');
                const titleField = document.querySelector(`[data-post-title="${locale}"]`);
                const slugField = document.querySelector(`[data-post-slug="${locale}"]`);
                const baseSlug = (slugField?.value || '').trim() || slugify(titleField ? titleField.value : '');
                const expectedPath = baseSlug ? postCanonicalPath(locale, baseSlug) : '';
                const expectedAbsolute = baseSlug ? postCanonicalAbsolute(locale, baseSlug) : '';
                const comparable = normalizeComparableUrl(input.value);
                if (
                    comparable === ''
                    || (expectedPath !== '' && comparable === normalizeComparableUrl(expectedPath))
                    || (expectedAbsolute !== '' && comparable === normalizeComparableUrl(expectedAbsolute))
                ) {
                    delete input.dataset.userEdited;
                } else {
                    input.dataset.userEdited = '1';
                }
            });

            document.querySelectorAll('[data-char-counter-for]').forEach((counter) => {
                const input = document.getElementById(counter.getAttribute('data-char-counter-for'));
                if (input) updateCounter(input);
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
                    const probe = '__testocms_autosave_probe__';
                    window.localStorage.setItem(probe, '1');
                    window.localStorage.removeItem(probe);
                    return window.localStorage;
                } catch (_) {
                    return null;
                }
            })();
            const autosaveSession = (() => {
                try {
                    const probe = '__testocms_post_autosave_session_probe__';
                    window.sessionStorage.setItem(probe, '1');
                    window.sessionStorage.removeItem(probe);
                    return window.sessionStorage;
                } catch (_) {
                    return null;
                }
            })();
            const autosaveKey = `testocms:autosave:post:${window.location.pathname}`;
            const autosaveCreateHandoffKey = 'testocms:autosave:post:create-handoff';
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
                    else if (field === 'content_html' || field === 'content_markdown') byLocale[locale].add('content');
                    else if (field === 'content_format' && text === 'markdown') byLocale[locale].add('markdown');
                    else if (field === 'excerpt') byLocale[locale].add('excerpt');
                    else if (field === 'custom_head_html') byLocale[locale].add('head');
                    else if (field.startsWith('meta_') || field === 'canonical_url') byLocale[locale].add('seo');
                });

                const parts = Object.entries(byLocale).map(([locale, fields]) => {
                    const list = Array.from(fields).sort().join(', ');
                    return `${locale.toUpperCase()}: ${list || 'used'}`;
                });
                return parts.length > 0 ? `Локали: ${parts.join(' · ')}` : 'Локали: нет локального черновика';
            };

            const updateAutosaveSummary = (payload) => {
                autosaveSummaryChip.textContent = summarizeTranslationsPayload(payload || {});
                autosaveSummaryChip.title = autosaveSummaryChip.textContent;
            };

            const serializeForm = () => {
                editorInstances.forEach((editor) => editor.sync());
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

                editorInstances.forEach((editor) => editor.refresh && editor.refresh());
                document.querySelectorAll('[data-char-counter-for]').forEach((counter) => {
                    const input = document.getElementById(counter.getAttribute('data-char-counter-for'));
                    if (input) updateCounter(input);
                });
                document.querySelectorAll('[data-seo-preview]').forEach((box) => {
                    const locale = box.getAttribute('data-seo-preview');
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
                        : dialogs.confirm(`Найден локальный черновик поста (${stampText}). Восстановить?`);

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

            const refreshPostUiAfterReset = () => {
                editorInstances.forEach((editor) => editor.refresh && editor.refresh());
                document.querySelectorAll('[data-char-counter-for]').forEach((counter) => {
                    const input = document.getElementById(counter.getAttribute('data-char-counter-for'));
                    if (input) updateCounter(input);
                });
                document.querySelectorAll('[data-seo-preview]').forEach((box) => {
                    const locale = box.getAttribute('data-seo-preview');
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
                    refreshPostUiAfterReset();
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
                editorInstances.forEach((editor) => editor.sync());
                const payload = {
                    entity: {
                        featured_asset_id: (() => {
                            const value = String(readFieldValue('featured_asset_id') || '').trim();
                            return value === '' ? null : Number(value);
                        })(),
                        category_ids: (() => {
                            const value = readFieldValue('category_ids[]');
                            if (Array.isArray(value)) return value.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0);
                            const single = String(value || '').trim();
                            return single === '' ? [] : [Number(single)].filter((id) => Number.isInteger(id) && id > 0);
                        })(),
                    },
                    translations: {},
                };

                supportedLocales.forEach((locale) => {
                    payload.translations[locale] = {
                        title: String(readFieldValue(`translations[${locale}][title]`) || '').trim(),
                        slug: String(readFieldValue(`translations[${locale}][slug]`) || '').trim(),
                        content_format: String(readFieldValue(`translations[${locale}][content_format]`) || 'html').trim() || 'html',
                        content_html: String(readFieldValue(`translations[${locale}][content_html]`) || ''),
                        content_markdown: String(readFieldValue(`translations[${locale}][content_markdown]`) || ''),
                        excerpt: String(readFieldValue(`translations[${locale}][excerpt]`) || '').trim(),
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
                    templateNameInput.value = baseName !== '' ? `${baseName} · template` : 'Новый шаблон поста';
                }
                if (templateDescInput && !templateDescInput.value) {
                    templateDescInput.value = 'Шаблон поста, сохранённый из редактора.';
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
                editorInstances.forEach((editor) => editor.sync());
                writeCreateHandoffMarker();
                saveAutosave({ force: true });
            });
        })();
