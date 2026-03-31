(() => {
    if (window.TestoCmsAssetSelector) {
        return;
    }

    const picker = () => window.TestoCmsMediaPicker || null;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const isImage = (asset) => (asset?.type === 'image') || String(asset?.mime_type || '').startsWith('image/');
    const isVideo = (asset) => (asset?.type === 'video') || String(asset?.mime_type || '').startsWith('video/');

    const findOption = (select, value) => Array.from(select.options).find((option) => String(option.value || '') === String(value || '')) || null;

    const upsertOption = (select, asset) => {
        if (!(select instanceof HTMLSelectElement) || !asset?.id) {
            return;
        }

        let option = findOption(select, asset.id);
        if (!option) {
            option = document.createElement('option');
            option.value = String(asset.id);
            const firstAssetOption = Array.from(select.options).find((item) => String(item.value || '').trim() !== '');
            if (firstAssetOption) {
                select.insertBefore(option, firstAssetOption);
            } else {
                select.appendChild(option);
            }
        }

        option.textContent = `#${asset.id} · ${asset.title || asset.public_url || asset.storage_path || 'Asset'}`;
        select.value = String(asset.id);
    };

    const renderPreview = (root) => {
        const previewEl = root.querySelector('[data-asset-selector-preview]');
        const select = root.querySelector('[data-asset-selector-source]');
        const clearBtn = root.querySelector('[data-asset-selector-clear]');
        if (!previewEl || !(select instanceof HTMLSelectElement)) {
            return;
        }

        const selectedId = String(select.value || '').trim();
        const selectedAsset = selectedId !== '' ? picker()?.findAsset?.(selectedId) || null : null;
        const emptyLabel = String(root.getAttribute('data-empty-label') || 'Файл не выбран');

        if (!selectedAsset) {
            previewEl.innerHTML = `
                <div class="asset-selector-thumb">Нет файла</div>
                <div class="asset-selector-meta">
                    <p class="asset-selector-title">${escapeHtml(emptyLabel)}</p>
                    <p class="asset-selector-note">Выберите существующий asset или загрузите новый прямо из этой формы.</p>
                </div>
            `;
            if (clearBtn instanceof HTMLButtonElement) {
                clearBtn.disabled = true;
            }
            return;
        }

        const thumb = isImage(selectedAsset) && selectedAsset.public_url
            ? `<img src="${escapeHtml(selectedAsset.public_url)}" alt="${escapeHtml(selectedAsset.alt || selectedAsset.title || '')}">`
            : `<span>${isVideo(selectedAsset) ? 'VIDEO' : 'FILE'}</span>`;
        const note = [
            `#${selectedAsset.id}`,
            selectedAsset.mime_type || selectedAsset.type || 'asset',
            selectedAsset.public_url || selectedAsset.storage_path || '',
        ].filter(Boolean).join(' · ');

        previewEl.innerHTML = `
            <div class="asset-selector-thumb">${thumb}</div>
            <div class="asset-selector-meta">
                <p class="asset-selector-title">${escapeHtml(selectedAsset.title || selectedAsset.alt || selectedAsset.storage_path || selectedAsset.public_url || `Asset #${selectedAsset.id}`)}</p>
                <p class="asset-selector-note">${escapeHtml(note)}</p>
            </div>
        `;
        if (clearBtn instanceof HTMLButtonElement) {
            clearBtn.disabled = false;
        }
    };

    const enhanceSelector = (root) => {
        const currentEl = root.querySelector('[data-asset-selector-current]');
        if (currentEl) {
            try {
                const currentAsset = JSON.parse(currentEl.textContent || '{}');
                if (currentAsset?.id) {
                    picker()?.upsertAsset?.(currentAsset);
                }
            } catch (_) {}
        }

        const select = root.querySelector('[data-asset-selector-source]');
        const ui = root.querySelector('[data-asset-selector-ui]');
        const fallback = root.querySelector('[data-asset-selector-fallback]');
        const openBtn = root.querySelector('[data-asset-selector-open]');
        const uploadBtn = root.querySelector('[data-asset-selector-upload]');
        const clearBtn = root.querySelector('[data-asset-selector-clear]');

        if (!(select instanceof HTMLSelectElement) || !(ui instanceof HTMLElement) || !picker()?.open) {
            return;
        }

        ui.hidden = false;
        if (fallback instanceof HTMLElement) {
            fallback.hidden = true;
        }
        if (uploadBtn instanceof HTMLButtonElement) {
            uploadBtn.hidden = !picker()?.canUpload?.();
        }

        const pickerOptions = (focusUpload = false) => ({
            accept: String(root.getAttribute('data-accept') || 'all'),
            multiple: false,
            title: String(root.getAttribute('data-picker-title') || 'Медиатека'),
            subtitle: String(focusUpload
                ? root.getAttribute('data-picker-upload-subtitle')
                : root.getAttribute('data-picker-subtitle') || 'Выберите файл из Assets'),
            focusUpload,
        });

        const handlePickedAsset = (asset) => {
            if (!asset?.id) {
                return;
            }
            picker()?.upsertAsset?.(asset);
            upsertOption(select, asset);
            renderPreview(root);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        openBtn?.addEventListener('click', () => {
            picker().open(pickerOptions(false)).then(handlePickedAsset).catch(() => {});
        });

        uploadBtn?.addEventListener('click', () => {
            picker().open(pickerOptions(true)).then(handlePickedAsset).catch(() => {});
        });

        clearBtn?.addEventListener('click', () => {
            select.value = '';
            renderPreview(root);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        select.addEventListener('change', () => renderPreview(root));
        renderPreview(root);
    };

    const init = () => {
        document.querySelectorAll('[data-asset-selector]').forEach((root) => enhanceSelector(root));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    window.TestoCmsAssetSelector = {
        init,
    };
})();
