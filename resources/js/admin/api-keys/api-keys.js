(function () {
    const form = document.querySelector('[data-api-key-form]');
    if (!form) {
        return;
    }

    const fullAccess = form.querySelector('[data-api-full-access]');
    const surfaceInputs = Array.from(form.querySelectorAll('[data-api-surface]'));
    const abilityInputs = Array.from(form.querySelectorAll('[data-api-ability]'));
    const abilityItems = Array.from(form.querySelectorAll('[data-api-ability-item]'));
    const abilitiesWrap = form.querySelector('[data-api-abilities-wrap]');

    const syncAbilitiesVisibility = () => {
        const selectedSurfaces = new Set(
            surfaceInputs.filter((input) => input.checked).map((input) => input.value)
        );
        const full = !!fullAccess?.checked;

        if (abilitiesWrap) {
            abilitiesWrap.style.opacity = full ? '.55' : '1';
        }

        abilityItems.forEach((item) => {
            const surface = String(item.getAttribute('data-surface') || '');
            const visible = selectedSurfaces.has(surface) && !full;
            item.style.display = visible ? '' : 'none';
        });

        abilityInputs.forEach((input) => {
            const surface = String(input.getAttribute('data-surface') || '');
            const enabled = selectedSurfaces.has(surface) && !full;
            input.disabled = !enabled;
            if (!enabled) {
                input.checked = false;
            }
        });
    };

    fullAccess?.addEventListener('change', syncAbilitiesVisibility);
    surfaceInputs.forEach((input) => input.addEventListener('change', syncAbilitiesVisibility));
    syncAbilitiesVisibility();

    const copyButton = document.querySelector('[data-api-key-copy]');
    const secretNode = document.querySelector('[data-api-key-secret]');
    if (!copyButton || !secretNode) {
        return;
    }

    copyButton.addEventListener('click', async () => {
        const secret = String(secretNode.textContent || '').trim();
        if (!secret) {
            return;
        }

        try {
            await navigator.clipboard.writeText(secret);
            copyButton.textContent = 'Скопировано';
        } catch (error) {
            const helper = document.createElement('textarea');
            helper.value = secret;
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            document.body.removeChild(helper);
            copyButton.textContent = 'Скопировано';
        }
    });
})();
