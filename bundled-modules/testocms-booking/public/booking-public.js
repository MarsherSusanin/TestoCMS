(() => {
    const initWidgetShells = () => {
        document.querySelectorAll('[data-booking-widget-endpoint]').forEach((node) => {
            if (!(node instanceof HTMLElement) || node.dataset.bookingWidgetLoaded === '1') {
                return;
            }
            node.dataset.bookingWidgetLoaded = '1';
            const endpoint = node.dataset.bookingWidgetEndpoint;
            if (!endpoint) {
                return;
            }
            fetch(endpoint, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then((response) => response.ok ? response.text() : Promise.reject(new Error('Widget load failed')))
                .then((html) => {
                    node.innerHTML = html;
                    initBookingForms(node);
                })
                .catch(() => {
                    node.dataset.bookingWidgetLoaded = '0';
                });
        });
    };

    const renderSlots = (container, slots, emptyLabel, isAggregated = false) => {
        if (!(container instanceof HTMLElement)) {
            return;
        }
        if (!Array.isArray(slots) || slots.length === 0) {
            container.innerHTML = `<div class="empty-state">${emptyLabel}</div>`;
            return;
        }
        container.innerHTML = slots.map((slot) => `
            <label class="booking-slot">
                <input type="radio" name="booking_slot" value="${slot.id}">
                <span>${slot.label}</span>
                <span class="muted">${isAggregated ? slot.available_count : slot.available}</span>
            </label>
        `).join('');
    };

    const initBookingForms = (root = document) => {
        root.querySelectorAll('[data-booking-form]').forEach((formNode) => {
            if (!(formNode instanceof HTMLElement) || formNode.dataset.bookingBound === '1') {
                return;
            }
            formNode.dataset.bookingBound = '1';
            const dateInput = formNode.querySelector('[data-booking-date]');
            const resourceSelect = formNode.querySelector('[data-booking-resource]');
            const slotsWrap = formNode.querySelector('[data-booking-slots]');
            const statusWrap = formNode.querySelector('[data-booking-status]');
            const submitButton = formNode.querySelector('[data-booking-submit]');
            const slotsEndpoint = formNode.dataset.slotsEndpoint;
            const bookEndpoint = formNode.dataset.bookEndpoint;
            const resourceMode = formNode.dataset.bookingResourceMode || 'auto_assign';
            const requiresResource = formNode.dataset.bookingRequiresResource === '1';
            const isRu = document.documentElement.lang.startsWith('ru');
            const setStatus = (message) => {
                if (statusWrap) {
                    statusWrap.textContent = String(message || '');
                }
            };
            const syncResourceOptions = (resources) => {
                if (!(resourceSelect instanceof HTMLSelectElement) || !Array.isArray(resources)) {
                    return;
                }
                const selected = resourceSelect.value;
                const placeholder = requiresResource
                    ? (isRu ? 'Сначала выберите ресурс' : 'Choose resource')
                    : (isRu ? 'Автоподбор' : 'Auto assign');
                resourceSelect.innerHTML = `<option value="">${placeholder}</option>` + resources.map((resource) => `
                    <option value="${resource.id}">${resource.name}</option>
                `).join('');
                if (selected && resources.some((resource) => String(resource.id) === String(selected))) {
                    resourceSelect.value = selected;
                }
            };
            const loadSlots = () => {
                if (!slotsEndpoint || !(dateInput instanceof HTMLInputElement) || !dateInput.value) {
                    return;
                }
                if (requiresResource && resourceSelect instanceof HTMLSelectElement && !resourceSelect.value) {
                    renderSlots(slotsWrap, [], isRu ? 'Сначала выберите ресурс.' : 'Select a resource first.');
                    setStatus(isRu ? 'Выберите ресурс, затем загрузятся слоты.' : 'Select a resource to load slots.');
                    return;
                }
                const url = new URL(slotsEndpoint, window.location.origin);
                url.searchParams.set('date', dateInput.value);
                if (resourceSelect instanceof HTMLSelectElement && resourceSelect.value) {
                    url.searchParams.set('resource_id', resourceSelect.value);
                }
                setStatus(isRu ? 'Загружаем слоты…' : 'Loading slots...');
                fetch(url.toString(), {headers: {'Accept': 'application/json'}})
                    .then((response) => response.ok ? response.json() : Promise.reject(new Error('Slots failed')))
                    .then((payload) => {
                        syncResourceOptions(payload?.meta?.resources || []);
                        const currentMode = payload?.meta?.resource_selection_mode || resourceMode;
                        renderSlots(
                            slotsWrap,
                            payload.data || [],
                            isRu ? 'На выбранную дату слотов нет.' : 'No slots for selected date.',
                            currentMode === 'auto_assign'
                        );
                        setStatus(isRu ? 'Слоты обновлены.' : 'Slots updated.');
                    })
                    .catch(() => {
                        renderSlots(slotsWrap, [], isRu ? 'Не удалось загрузить слоты.' : 'Failed to load slots.');
                    });
            };
            const submitBooking = () => {
                if (!bookEndpoint) {
                    return;
                }
                const selectedSlot = formNode.querySelector('input[name="booking_slot"]:checked');
                if (!(selectedSlot instanceof HTMLInputElement)) {
                    setStatus(isRu ? 'Сначала выберите слот.' : 'Select a slot first.');
                    return;
                }
                const payload = {
                    slot_id: selectedSlot.value,
                    customer_name: formNode.querySelector('[data-booking-customer-name]')?.value || '',
                    customer_email: formNode.querySelector('[data-booking-customer-email]')?.value || '',
                    customer_phone: formNode.querySelector('[data-booking-customer-phone]')?.value || '',
                    customer_comment: formNode.querySelector('[data-booking-customer-comment]')?.value || '',
                };
                if (resourceSelect instanceof HTMLSelectElement && resourceSelect.value) {
                    payload.resource_id = resourceSelect.value;
                }
                if (requiresResource && !payload.resource_id) {
                    setStatus(isRu ? 'Сначала выберите ресурс.' : 'Select a resource first.');
                    return;
                }
                setStatus(isRu ? 'Отправляем бронирование…' : 'Submitting booking...');
                fetch(bookEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify(payload),
                })
                    .then(async (response) => {
                        const json = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(json.message || 'Booking failed');
                        }
                        return json;
                    })
                    .then((payload) => {
                        setStatus(payload?.data?.message || (isRu ? 'Бронирование отправлено.' : 'Booking submitted.'));
                        loadSlots();
                    })
                    .catch((error) => setStatus(error.message || (isRu ? 'Ошибка бронирования.' : 'Booking failed.')));
            };
            if (dateInput instanceof HTMLInputElement) {
                dateInput.addEventListener('change', loadSlots);
            }
            if (resourceSelect instanceof HTMLSelectElement) {
                resourceSelect.addEventListener('change', loadSlots);
            }
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.addEventListener('click', submitBooking);
            }
            loadSlots();
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initWidgetShells();
            initBookingForms(document);
        });
    } else {
        initWidgetShells();
        initBookingForms(document);
    }
})();
