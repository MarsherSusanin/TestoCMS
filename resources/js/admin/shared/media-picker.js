(() => {
    if (window.TestoCmsMediaPicker) {
        return;
    }

    const overlay = document.getElementById('testocms-media-picker');
    if (!overlay) {
        return;
    }

    const adminUi = window.TestoCmsAdminUi || {};
    const dialogService = adminUi.dialogService || {
        alert(message) {
            return window.alert(String(message ?? ''));
        },
        confirm(message) {
            return window.confirm(String(message ?? ''));
        },
    };

    const gridEl = overlay.querySelector('[data-media-picker-grid]');
    const sideEl = overlay.querySelector('[data-media-picker-side]');
    const searchEl = overlay.querySelector('[data-media-picker-search]');
    const kindEl = overlay.querySelector('[data-media-picker-kind]');
    const sortEl = overlay.querySelector('[data-media-picker-sort]');
    const statusEl = overlay.querySelector('[data-media-picker-status]');
    const subtitleEl = overlay.querySelector('#media-picker-subtitle');
    const confirmBtn = overlay.querySelector('[data-media-picker-confirm]');
    const uploadEl = overlay.querySelector('[data-media-picker-upload]');
    const uploadDropzoneEl = overlay.querySelector('[data-media-picker-upload-dropzone]');
    const uploadInputEl = overlay.querySelector('[data-media-picker-upload-input]');
    const uploadChooseBtn = overlay.querySelector('[data-media-picker-upload-choose]');
    const uploadTitleEl = overlay.querySelector('[data-media-picker-upload-title]');
    const uploadAltEl = overlay.querySelector('[data-media-picker-upload-alt]');
    const uploadResetBtn = overlay.querySelector('[data-media-picker-upload-reset]');
    const uploadSubmitBtn = overlay.querySelector('[data-media-picker-upload-submit]');
    const uploadStatusEl = overlay.querySelector('[data-media-picker-upload-status]');

    const state = {
        assets: [],
        open: false,
        query: '',
        kind: 'all',
        sort: 'newest',
        selectedIds: new Set(),
        activeId: null,
        resolve: null,
        reject: null,
        config: {
            uploadUrl: '',
            canUpload: false,
        },
        options: {
            accept: 'all',
            multiple: false,
            title: 'Медиатека',
            subtitle: 'Выберите файл из Assets',
            focusUpload: false,
        },
        upload: {
            file: null,
            busy: false,
        },
    };

    const bytes = (size) => {
        const n = Number(size || 0);
        if (!Number.isFinite(n) || n <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let value = n;
        let i = 0;
        while (value >= 1024 && i < units.length - 1) {
            value /= 1024;
            i++;
        }
        return `${value.toFixed(value >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta?.content) {
            return meta.content;
        }

        const input = document.querySelector('input[name="_token"]');
        return input instanceof HTMLInputElement ? input.value : '';
    };

    const isImage = (asset) => (asset.type === 'image') || String(asset.mime_type || '').startsWith('image/');
    const isVideo = (asset) => (asset.type === 'video') || String(asset.mime_type || '').startsWith('video/');
    const isDocument = (asset) => !isImage(asset) && !isVideo(asset);

    const acceptMatches = (asset) => {
        const accept = state.options.accept || 'all';
        if (accept === 'all') return true;
        if (accept === 'image') return isImage(asset);
        if (accept === 'video') return isVideo(asset);
        if (accept === 'document') return isDocument(asset);
        return true;
    };

    const kindMatches = (asset) => {
        const kind = state.kind || 'all';
        if (kind === 'all') return true;
        if (kind === 'image') return isImage(asset);
        if (kind === 'video') return isVideo(asset);
        if (kind === 'document') return isDocument(asset);
        return true;
    };

    const normalizeAsset = (asset) => ({
        id: Number(asset?.id || 0),
        type: String(asset?.type || ''),
        mime_type: String(asset?.mime_type || ''),
        title: String(asset?.title || ''),
        alt: String(asset?.alt || ''),
        caption: String(asset?.caption || ''),
        credits: String(asset?.credits || ''),
        disk: String(asset?.disk || ''),
        storage_path: String(asset?.storage_path || ''),
        public_url: String(asset?.public_url || ''),
        size: Number(asset?.size || 0),
        width: asset?.width != null ? Number(asset.width) : null,
        height: asset?.height != null ? Number(asset.height) : null,
    });

    const acceptAttributeFor = (accept) => {
        if (accept === 'image') return 'image/*';
        if (accept === 'video') return 'video/*';
        return '';
    };

    const fileMatchesAccept = (file) => {
        if (!(file instanceof File)) {
            return false;
        }

        if (state.options.accept === 'image') {
            return String(file.type || '').startsWith('image/');
        }
        if (state.options.accept === 'video') {
            return String(file.type || '').startsWith('video/');
        }

        return true;
    };

    const filteredAssets = () => {
        let items = state.assets.filter((asset) => acceptMatches(asset) && kindMatches(asset));
        const q = state.query.trim().toLowerCase();
        if (q) {
            items = items.filter((asset) => {
                const haystack = [
                    asset.id,
                    asset.title,
                    asset.alt,
                    asset.caption,
                    asset.mime_type,
                    asset.public_url,
                    asset.storage_path,
                ].join(' ').toLowerCase();
                return haystack.includes(q);
            });
        }

        if (state.sort === 'oldest') {
            items.sort((a, b) => a.id - b.id);
        } else if (state.sort === 'title') {
            items.sort((a, b) => String(a.title || a.alt || a.public_url || '').localeCompare(String(b.title || b.alt || b.public_url || '')));
        } else {
            items.sort((a, b) => b.id - a.id);
        }

        return items;
    };

    const findAsset = (id) => state.assets.find((asset) => Number(asset.id) === Number(id)) || null;

    const upsertAsset = (asset) => {
        const normalized = normalizeAsset(asset);
        if (!normalized.id) {
            return null;
        }

        const existingIndex = state.assets.findIndex((item) => Number(item.id) === normalized.id);
        if (existingIndex >= 0) {
            state.assets.splice(existingIndex, 1, normalized);
        } else {
            state.assets.unshift(normalized);
        }

        if (state.open) {
            renderGrid();
        }

        return normalized;
    };

    const setAssets = (assets) => {
        state.assets = Array.isArray(assets) ? assets.map(normalizeAsset).filter((asset) => asset.id > 0) : [];
        if (state.open) {
            renderGrid();
        }
    };

    const setUploadStatus = (message) => {
        if (uploadStatusEl) {
            uploadStatusEl.textContent = String(message || '');
        }
    };

    const resetUploadForm = () => {
        state.upload.file = null;
        state.upload.busy = false;
        if (uploadInputEl) uploadInputEl.value = '';
        if (uploadTitleEl) uploadTitleEl.value = '';
        if (uploadAltEl) uploadAltEl.value = '';
        if (uploadDropzoneEl) uploadDropzoneEl.classList.remove('is-active');
        setUploadStatus('Файл ещё не выбран.');
        if (uploadSubmitBtn) {
            uploadSubmitBtn.disabled = false;
            uploadSubmitBtn.textContent = state.options.multiple ? 'Загрузить и добавить' : 'Загрузить и выбрать';
        }
    };

    const updateUploadUi = () => {
        if (!uploadEl) {
            return;
        }

        uploadEl.hidden = !state.config.canUpload;
        if (uploadInputEl) {
            uploadInputEl.setAttribute('accept', acceptAttributeFor(state.options.accept));
        }
        if (uploadSubmitBtn && !state.upload.busy) {
            uploadSubmitBtn.textContent = state.options.multiple ? 'Загрузить и добавить' : 'Загрузить и выбрать';
        }
    };

    const setUploadFile = (file) => {
        if (!(file instanceof File)) {
            resetUploadForm();
            return;
        }

        if (!fileMatchesAccept(file)) {
            dialogService.alert(
                state.options.accept === 'image'
                    ? 'В этом режиме можно загружать только изображения.'
                    : 'В этом режиме можно загружать только видео.'
            );
            resetUploadForm();
            return;
        }

        state.upload.file = file;
        if (uploadTitleEl && !uploadTitleEl.value.trim()) {
            uploadTitleEl.value = String(file.name || '').replace(/\.[^.]+$/, '');
        }
        setUploadStatus(`Выбран файл: ${file.name}`);
    };

    const renderSide = () => {
        const selected = state.selectedIds.size > 0 ? Array.from(state.selectedIds).map(findAsset).filter(Boolean) : [];
        const active = findAsset(state.activeId) || selected[0] || null;

        if (!active) {
            sideEl.innerHTML = '<div class="empty">Выберите файл, чтобы увидеть детали. В режиме множественного выбора можно отметить несколько изображений.</div>';
            return;
        }

        const dim = active.width && active.height ? `${active.width} × ${active.height}` : '—';
        const imagePreview = isImage(active) && active.public_url
            ? `<div class="media-picker-detail-preview"><img src="${escapeHtml(active.public_url)}" alt="${escapeHtml(active.alt || active.title || 'image')}"></div>`
            : `<div class="media-picker-detail-preview"><div class="placeholder">${isVideo(active) ? 'Видеофайл' : 'Документ'}</div></div>`;

        sideEl.innerHTML = `
            <h4>Выбранный файл</h4>
            ${imagePreview}
            <div class="media-picker-detail-grid">
                <div class="media-picker-detail-row"><div class="label">ID</div><div class="value">#${active.id}</div></div>
                <div class="media-picker-detail-row"><div class="label">Название</div><div class="value">${escapeHtml(active.title || '—')}</div></div>
                <div class="media-picker-detail-row"><div class="label">Alt-текст</div><div class="value">${escapeHtml(active.alt || '—')}</div></div>
                <div class="media-picker-detail-row"><div class="label">Тип / MIME</div><div class="value">${escapeHtml(active.type || '—')} · ${escapeHtml(active.mime_type || '—')}</div></div>
                <div class="media-picker-detail-row"><div class="label">Размеры</div><div class="value">${escapeHtml(dim)}</div></div>
                <div class="media-picker-detail-row"><div class="label">Размер</div><div class="value">${escapeHtml(bytes(active.size))}</div></div>
                <div class="media-picker-detail-row"><div class="label">URL</div><div class="value">${escapeHtml(active.public_url || '—')}</div></div>
            </div>
            ${selected.length > 1 ? `<div class="empty" style="margin-top:10px;">Выбрано несколько: ${selected.length}</div>` : ''}
        `;
    };

    const updateStatus = () => {
        const count = state.selectedIds.size;
        statusEl.textContent = state.options.multiple
            ? `Выбрано: ${count}`
            : (count === 1 ? 'Выбран 1 файл' : 'Выберите один файл');
        confirmBtn.style.display = state.options.multiple ? 'inline-flex' : 'none';
    };

    const renderGrid = () => {
        const items = filteredAssets();
        if (items.length === 0) {
            gridEl.innerHTML = '<div class="media-picker-empty-grid">По текущим фильтрам ничего не найдено.</div>';
            renderSide();
            updateStatus();
            return;
        }

        gridEl.innerHTML = items.map((asset) => {
            const image = isImage(asset) && asset.public_url
                ? `<img src="${escapeHtml(asset.public_url)}" alt="${escapeHtml(asset.alt || asset.title || '')}">`
                : `<div class="placeholder">${isVideo(asset) ? 'VIDEO' : 'FILE'}</div>`;
            const badge = (asset.type || (isImage(asset) ? 'image' : isVideo(asset) ? 'video' : 'document')).toUpperCase();
            const title = asset.title || asset.alt || asset.storage_path || asset.public_url || `Asset #${asset.id}`;
            const meta = `${asset.mime_type || '—'}${asset.width && asset.height ? ` · ${asset.width}×${asset.height}` : ''}`;
            const selected = state.selectedIds.has(asset.id) ? 'selected' : '';

            return `
                <button type="button" class="media-picker-card ${selected}" data-media-picker-item="${asset.id}">
                    <div class="media-picker-thumb">
                        <span class="badge">${escapeHtml(badge)}</span>
                        ${image}
                    </div>
                    <div class="media-picker-card-body">
                        <p class="media-picker-card-title">${escapeHtml(title)}</p>
                        <p class="media-picker-card-meta">#${asset.id} · ${escapeHtml(meta)}</p>
                    </div>
                </button>
            `;
        }).join('');

        renderSide();
        updateStatus();
    };

    const close = (reason = 'cancel') => {
        if (!state.open) {
            return;
        }

        state.open = false;
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        resetUploadForm();

        const reject = state.reject;
        state.resolve = null;
        state.reject = null;

        if (reason !== 'confirm' && typeof reject === 'function') {
            reject(new Error('Выбор файла отменён'));
        }
    };

    const confirmSelection = () => {
        const selected = Array.from(state.selectedIds).map(findAsset).filter(Boolean);
        if (selected.length === 0) {
            dialogService.alert('Выберите файл.');
            return;
        }

        const resolve = state.resolve;
        state.resolve = null;
        state.reject = null;
        state.open = false;
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        resetUploadForm();

        if (typeof resolve === 'function') {
            resolve(state.options.multiple ? selected : selected[0]);
        }
    };

    const uploadSelectedFile = async () => {
        if (!state.config.canUpload || !state.config.uploadUrl) {
            return;
        }
        if (!(state.upload.file instanceof File)) {
            dialogService.alert('Сначала выберите файл для загрузки.');
            return;
        }

        state.upload.busy = true;
        if (uploadSubmitBtn) {
            uploadSubmitBtn.disabled = true;
            uploadSubmitBtn.textContent = 'Загрузка...';
        }
        setUploadStatus(`Загружается: ${state.upload.file.name}`);

        const formData = new FormData();
        formData.append('file', state.upload.file);
        if (uploadTitleEl?.value.trim()) {
            formData.append('title', uploadTitleEl.value.trim());
        }
        if (uploadAltEl?.value.trim()) {
            formData.append('alt', uploadAltEl.value.trim());
        }

        try {
            const response = await fetch(state.config.uploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = payload?.message || payload?.error || 'Не удалось загрузить файл.';
                throw new Error(String(message));
            }

            const asset = upsertAsset(payload?.data || {});
            if (!asset) {
                throw new Error('Сервер вернул некорректный asset payload.');
            }

            state.activeId = asset.id;
            if (state.options.multiple) {
                state.selectedIds.add(asset.id);
            } else {
                state.selectedIds = new Set([asset.id]);
            }
            renderGrid();
            setUploadStatus(`Файл загружен: ${asset.title || state.upload.file.name}`);

            if (state.options.multiple) {
                resetUploadForm();
            } else {
                confirmSelection();
            }
        } catch (error) {
            state.upload.busy = false;
            if (uploadSubmitBtn) {
                uploadSubmitBtn.disabled = false;
                uploadSubmitBtn.textContent = state.options.multiple ? 'Загрузить и добавить' : 'Загрузить и выбрать';
            }
            setUploadStatus(error instanceof Error ? error.message : 'Не удалось загрузить файл.');
            dialogService.alert(error instanceof Error ? error.message : 'Не удалось загрузить файл.');
        }
    };

    const open = (options = {}) => {
        state.options = {
            accept: options.accept || 'all',
            multiple: !!options.multiple,
            title: options.title || 'Медиатека',
            subtitle: options.subtitle || 'Выберите файл из Assets',
            focusUpload: !!options.focusUpload,
        };
        state.query = '';
        state.kind = state.options.accept && state.options.accept !== 'all' ? state.options.accept : 'all';
        state.sort = 'newest';
        state.selectedIds = new Set();
        state.activeId = null;
        resetUploadForm();
        updateUploadUi();

        overlay.querySelector('#media-picker-title').textContent = state.options.title;
        subtitleEl.textContent = state.options.subtitle;
        searchEl.value = '';
        kindEl.value = state.kind;
        sortEl.value = state.sort;

        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        state.open = true;
        renderGrid();

        window.setTimeout(() => {
            if (state.options.focusUpload && uploadDropzoneEl && state.config.canUpload) {
                uploadDropzoneEl.focus();
                uploadDropzoneEl.classList.add('is-active');
            } else {
                searchEl.focus();
            }
        }, 0);

        return new Promise((resolve, reject) => {
            state.resolve = resolve;
            state.reject = reject;
        });
    };

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            close();
            return;
        }

        if (event.target.closest('[data-media-picker-close]') || event.target.closest('[data-media-picker-cancel]')) {
            close();
            return;
        }
        if (event.target.closest('[data-media-picker-clear]')) {
            state.selectedIds = new Set();
            state.activeId = null;
            renderGrid();
            return;
        }
        if (event.target.closest('[data-media-picker-confirm]')) {
            confirmSelection();
            return;
        }
        if (event.target.closest('[data-media-picker-upload-choose]')) {
            uploadInputEl?.click();
            return;
        }
        if (event.target.closest('[data-media-picker-upload-reset]')) {
            resetUploadForm();
            return;
        }
        if (event.target.closest('[data-media-picker-upload-submit]')) {
            uploadSelectedFile();
            return;
        }

        const item = event.target.closest('[data-media-picker-item]');
        if (!item) {
            return;
        }

        const id = Number(item.getAttribute('data-media-picker-item'));
        const asset = findAsset(id);
        if (!asset) {
            return;
        }

        state.activeId = id;

        if (state.options.multiple) {
            if (state.selectedIds.has(id)) {
                state.selectedIds.delete(id);
            } else {
                state.selectedIds.add(id);
            }
            renderGrid();
        } else {
            state.selectedIds = new Set([id]);
            renderGrid();
            confirmSelection();
        }
    });

    searchEl.addEventListener('input', () => {
        state.query = searchEl.value || '';
        renderGrid();
    });
    kindEl.addEventListener('change', () => {
        state.kind = kindEl.value;
        renderGrid();
    });
    sortEl.addEventListener('change', () => {
        state.sort = sortEl.value;
        renderGrid();
    });

    uploadInputEl?.addEventListener('change', () => {
        const file = uploadInputEl.files && uploadInputEl.files[0];
        setUploadFile(file || null);
    });

    uploadDropzoneEl?.addEventListener('dragenter', (event) => {
        if (!state.config.canUpload) {
            return;
        }
        event.preventDefault();
        uploadDropzoneEl.classList.add('is-active');
    });
    uploadDropzoneEl?.addEventListener('dragover', (event) => {
        if (!state.config.canUpload) {
            return;
        }
        event.preventDefault();
        uploadDropzoneEl.classList.add('is-active');
    });
    uploadDropzoneEl?.addEventListener('dragleave', () => {
        uploadDropzoneEl.classList.remove('is-active');
    });
    uploadDropzoneEl?.addEventListener('drop', (event) => {
        if (!state.config.canUpload) {
            return;
        }
        event.preventDefault();
        uploadDropzoneEl.classList.remove('is-active');
        const file = event.dataTransfer?.files && event.dataTransfer.files[0];
        setUploadFile(file || null);
    });
    uploadDropzoneEl?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            uploadInputEl?.click();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!state.open || event.key !== 'Escape') {
            return;
        }
        event.preventDefault();
        close();
    });

    const assetsEl = document.getElementById('testocms-media-picker-assets');
    if (assetsEl) {
        try {
            setAssets(JSON.parse(assetsEl.textContent || '[]') || []);
        } catch (_) {
            setAssets([]);
        }
    }

    const configEl = document.getElementById('testocms-media-picker-config');
    if (configEl) {
        try {
            const parsed = JSON.parse(configEl.textContent || '{}') || {};
            state.config.uploadUrl = String(parsed.upload_url || '');
            state.config.canUpload = !!parsed.can_upload;
        } catch (_) {
            state.config.uploadUrl = '';
            state.config.canUpload = false;
        }
    }

    updateUploadUi();

    window.TestoCmsMediaPicker = {
        setAssets,
        upsertAsset,
        findAsset,
        getAssets() {
            return state.assets.slice();
        },
        canUpload() {
            return !!state.config.canUpload;
        },
        open,
        close,
    };
})();
