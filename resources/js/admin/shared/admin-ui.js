(() => {
    if (window.TestoCmsAdminUi) {
        return;
    }

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

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta?.content) {
            return meta.content;
        }

        const input = document.querySelector('input[name="_token"]');
        return input instanceof HTMLInputElement ? input.value : '';
    };
    const submitAction = ({ action, method = 'POST', fields = {}, confirmMessage = '' }) => {
        if (!action) {
            return;
        }

        if (confirmMessage && !dialogService.confirm(confirmMessage)) {
            return;
        }

        const token = getCsrfToken();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.style.display = 'none';

        if (token) {
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = token;
            form.appendChild(tokenInput);
        }

        if (String(method).toUpperCase() !== 'POST') {
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = String(method).toUpperCase();
            form.appendChild(methodInput);
        }

        Object.entries(fields || {}).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value ?? '');
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    };

    const openModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    };

    const resolveModal = (name) => {
        if (!name) {
            return null;
        }

        return document.querySelector(`[data-modal="${String(name).replace(/"/g, '\\"')}"]`);
    };

    const closeActionMenus = () => {
        qsa('[data-action-menu]').forEach((menu) => {
            menu.classList.remove('is-open');
            const trigger = qs('[data-action-trigger]', menu);
            const panel = qs('[data-action-panel]', menu);
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
            if (panel) {
                panel.hidden = true;
                panel.style.top = '';
                panel.style.left = '';
                panel.style.right = '';
                panel.style.bottom = '';
            }
        });
    };

    const positionActionMenu = (menu) => {
        if (!menu) {
            return;
        }

        const trigger = qs('[data-action-trigger]', menu);
        const panel = qs('[data-action-panel]', menu);
        if (!trigger || !panel) {
            return;
        }

        const gap = 8;
        const viewportPadding = 8;
        const align = String(menu.getAttribute('data-action-align') || 'end');
        const triggerRect = trigger.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        let left = align === 'start'
            ? triggerRect.left
            : triggerRect.right - panelRect.width;
        left = Math.max(viewportPadding, Math.min(left, viewportWidth - panelRect.width - viewportPadding));

        let top = triggerRect.bottom + gap;
        if (top + panelRect.height > viewportHeight - viewportPadding) {
            top = triggerRect.top - panelRect.height - gap;
        }
        top = Math.max(viewportPadding, Math.min(top, viewportHeight - panelRect.height - viewportPadding));

        panel.style.top = `${Math.round(top)}px`;
        panel.style.left = `${Math.round(left)}px`;
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
    };

    const parseJson = (value, fallback = {}) => {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (_) {
            return fallback;
        }
    };

    const populateActionModal = (trigger, modal) => {
        if (!trigger || !modal) {
            return;
        }

        const title = trigger.getAttribute('data-action-title');
        const description = trigger.getAttribute('data-action-description');
        const formAction = trigger.getAttribute('data-action-form-action');
        const formMethod = trigger.getAttribute('data-action-form-method');
        const payload = parseJson(trigger.getAttribute('data-action-payload'), {});
        const textPayload = parseJson(trigger.getAttribute('data-action-text'), {});
        const form = qs('[data-action-modal-form]', modal);

        if (title) {
            qsa('[data-action-modal-title]', modal).forEach((node) => {
                node.textContent = title;
            });
        }

        if (description) {
            qsa('[data-action-modal-description]', modal).forEach((node) => {
                node.textContent = description;
            });
        }

        Object.entries(textPayload).forEach(([key, value]) => {
            qsa(`[data-action-text="${String(key)}"]`, modal).forEach((node) => {
                node.textContent = String(value ?? '');
            });
        });

        if (form) {
            if (formAction) {
                form.setAttribute('action', formAction);
            }

            const methodInput = qs('input[name="_method"]', form);
            if (methodInput) {
                methodInput.value = formMethod ? String(formMethod).toUpperCase() : 'POST';
            }
        }

        Object.entries(payload).forEach(([key, value]) => {
            qsa(`[data-action-field="${String(key)}"]`, modal).forEach((field) => {
                if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
                    return;
                }

                if (field.type === 'checkbox') {
                    field.checked = !!value;
                    return;
                }

                field.value = String(value ?? '');
            });
        });
    };

    const bindModalTriggers = () => {
        document.addEventListener('click', (event) => {
            const actionTrigger = event.target.closest('[data-action-trigger]');
            if (actionTrigger) {
                event.preventDefault();
                const menu = actionTrigger.closest('[data-action-menu]');
                if (!menu) {
                    return;
                }

                const shouldOpen = !menu.classList.contains('is-open');
                closeActionMenus();
                if (!shouldOpen) {
                    return;
                }

                const panel = qs('[data-action-panel]', menu);
                menu.classList.add('is-open');
                actionTrigger.setAttribute('aria-expanded', 'true');
                if (panel) {
                    panel.hidden = false;
                    positionActionMenu(menu);
                }
                return;
            }

            const actionModalTrigger = event.target.closest('[data-action-modal]');
            if (actionModalTrigger) {
                event.preventDefault();
                closeActionMenus();
                const modal = resolveModal(actionModalTrigger.getAttribute('data-action-modal'));
                if (modal) {
                    populateActionModal(actionModalTrigger, modal);
                    openModal(modal);
                }
                return;
            }

            const actionSubmitTrigger = event.target.closest('[data-action-submit]');
            if (actionSubmitTrigger) {
                event.preventDefault();
                closeActionMenus();
                submitAction({
                    action: actionSubmitTrigger.getAttribute('data-action-submit') || '',
                    method: actionSubmitTrigger.getAttribute('data-action-method') || 'POST',
                    fields: parseJson(actionSubmitTrigger.getAttribute('data-action-fields'), {}),
                    confirmMessage: actionSubmitTrigger.getAttribute('data-confirm') || '',
                });
                return;
            }

            const openTrigger = event.target.closest('[data-modal-open]');
            if (openTrigger) {
                closeActionMenus();
                const modal = resolveModal(openTrigger.getAttribute('data-modal-open'));
                if (modal) {
                    openModal(modal);
                }
                return;
            }

            const closeTrigger = event.target.closest('[data-modal-close]');
            if (closeTrigger) {
                const modal = closeTrigger.closest('[data-modal], .template-modal');
                if (modal) {
                    closeModal(modal);
                }
                return;
            }

            const modal = event.target.closest('[data-modal], .template-modal');
            if (modal && event.target === modal) {
                closeModal(modal);
                return;
            }

            if (!event.target.closest('[data-action-menu]')) {
                closeActionMenus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            qsa('[data-modal].open, .template-modal.open').forEach((modal) => closeModal(modal));
            closeActionMenus();
        });

        window.addEventListener('resize', closeActionMenus);
        window.addEventListener('scroll', closeActionMenus, true);
    };

    const bindConfirmForms = () => {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const message = form.getAttribute('data-confirm');
            if (!message) {
                return;
            }

            if (!dialogService.confirm(message)) {
                event.preventDefault();
            }
        }, true);
    };

    const wireBulkForm = (form) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.bulkBound === '1') {
            return;
        }
        form.dataset.bulkBound = '1';

        const checkAll = qs('[data-check-all]', form);
        const rowChecks = qsa('[data-row-check]', form);
        const countEl = qs('[data-bulk-count]', form);
        const submitBtn = qs('[data-bulk-submit]', form);
        const actionEl = qs('[data-bulk-action]', form);
        const bulkShell = qs('[data-bulk-shell]', form);

        const selectedCount = () => rowChecks.filter((item) => item.checked).length;
        const updateState = () => {
            const count = selectedCount();
            if (countEl) {
                countEl.textContent = `Выбрано: ${count}`;
            }
            if (submitBtn) {
                submitBtn.disabled = count === 0;
            }
            if (bulkShell) {
                bulkShell.hidden = count === 0;
            }
            if (checkAll) {
                checkAll.checked = count > 0 && count === rowChecks.length;
                checkAll.indeterminate = count > 0 && count < rowChecks.length;
            }
        };

        if (checkAll) {
            checkAll.addEventListener('change', () => {
                rowChecks.forEach((item) => {
                    item.checked = !!checkAll.checked;
                });
                updateState();
            });
        }

        rowChecks.forEach((item) => item.addEventListener('change', updateState));

        form.addEventListener('submit', (event) => {
            if (selectedCount() === 0) {
                event.preventDefault();
                dialogService.alert(form.getAttribute('data-bulk-empty-message') || 'Выберите хотя бы одну строку.');
                return;
            }

            const action = String(actionEl?.value || '');
            if (action === 'delete') {
                const message = form.getAttribute('data-bulk-delete-confirm') || 'Удалить выбранные записи?';
                if (!dialogService.confirm(message)) {
                    event.preventDefault();
                }
            }
        });

        updateState();
    };

    const bindBulkForms = () => {
        qsa('form[data-bulk-form]').forEach((form) => wireBulkForm(form));
    };

    const bindCopyButtons = () => {
        document.addEventListener('click', async (event) => {
            const trigger = event.target.closest('[data-copy-text]');
            if (!trigger) {
                return;
            }

            event.preventDefault();
            const text = String(trigger.getAttribute('data-copy-text') || '');
            if (!text) {
                return;
            }

            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const temp = document.createElement('textarea');
                    temp.value = text;
                    temp.setAttribute('readonly', 'readonly');
                    temp.style.position = 'absolute';
                    temp.style.left = '-9999px';
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                }

                const oldText = trigger.textContent;
                trigger.textContent = 'Скопировано';
                window.setTimeout(() => {
                    trigger.textContent = oldText;
                }, 1200);
            } catch (_) {
                dialogService.alert('Не удалось скопировать значение.');
            }
        });
    };

    bindModalTriggers();
    bindConfirmForms();
    bindBulkForms();
    bindCopyButtons();
    closeActionMenus();

    window.TestoCmsAdminUi = {
        dialogService,
        openModal,
        closeModal,
        resolveModal,
        bindBulkForms,
        closeActionMenus,
    };
})();
