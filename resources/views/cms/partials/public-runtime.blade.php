<script>
(() => {
    const initCarousels = () => {
        document.querySelectorAll('[data-cms-carousel]').forEach((root) => {
            if (root.dataset.cmsCarouselBound === '1') return;
            root.dataset.cmsCarouselBound = '1';

            const slides = Array.from(root.querySelectorAll('[data-cms-carousel-slide]'));
            if (slides.length === 0) return;

            const dots = Array.from(root.querySelectorAll('[data-cms-carousel-dot]'));
            const prevBtn = root.querySelector('[data-cms-carousel-prev]');
            const nextBtn = root.querySelector('[data-cms-carousel-next]');
            const autoplay = root.getAttribute('data-autoplay') === 'true';
            const intervalMs = Math.max(1500, Number(root.getAttribute('data-interval-ms') || 5000));
            let current = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
            if (current < 0) current = 0;
            let timer = null;

            const setActive = (index) => {
                current = (index + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => {
                    const active = slideIndex === current;
                    slide.classList.toggle('is-active', active);
                    slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                });
                dots.forEach((dot, dotIndex) => {
                    const active = dotIndex === current;
                    dot.classList.toggle('is-active', active);
                    dot.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            };

            const stopAutoplay = () => {
                if (timer) {
                    window.clearInterval(timer);
                    timer = null;
                }
            };

            const startAutoplay = () => {
                if (!autoplay || slides.length < 2 || timer) return;
                timer = window.setInterval(() => setActive(current + 1), intervalMs);
            };

            prevBtn?.addEventListener('click', () => {
                stopAutoplay();
                setActive(current - 1);
                startAutoplay();
            });
            nextBtn?.addEventListener('click', () => {
                stopAutoplay();
                setActive(current + 1);
                startAutoplay();
            });
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    stopAutoplay();
                    setActive(index);
                    startAutoplay();
                });
            });

            root.addEventListener('mouseenter', stopAutoplay);
            root.addEventListener('mouseleave', startAutoplay);
            root.addEventListener('focusin', stopAutoplay);
            root.addEventListener('focusout', () => {
                if (!root.contains(document.activeElement)) {
                    startAutoplay();
                }
            });

            setActive(current);
            startAutoplay();
        });
    };

    const initDisclosures = () => {
        const disclosures = Array.from(document.querySelectorAll('[data-cms-nav-disclosure]'));
        if (disclosures.length === 0) return;

        const setOpen = (disclosure, open) => {
            const button = disclosure.querySelector('[data-cms-nav-disclosure-toggle]');
            const menu = disclosure.querySelector('[data-cms-nav-submenu]');
            if (!button || !menu) return;
            disclosure.classList.toggle('is-open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            menu.hidden = !open;
        };

        const closeAll = (except = null) => {
            disclosures.forEach((disclosure) => {
                if (disclosure !== except) {
                    setOpen(disclosure, false);
                }
            });
        };

        disclosures.forEach((disclosure) => {
            if (disclosure.dataset.cmsDisclosureBound === '1') return;
            disclosure.dataset.cmsDisclosureBound = '1';
            const button = disclosure.querySelector('[data-cms-nav-disclosure-toggle]');
            if (!button) return;
            setOpen(disclosure, false);
            button.addEventListener('click', () => {
                const isOpen = button.getAttribute('aria-expanded') === 'true';
                closeAll(disclosure);
                setOpen(disclosure, !isOpen);
            });
        });

        if (document.documentElement.dataset.cmsDisclosureGlobalBound === '1') return;
        document.documentElement.dataset.cmsDisclosureGlobalBound = '1';
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element) || !target.closest('[data-cms-nav-disclosure]')) {
                closeAll();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAll();
            }
        });
    };

    const boot = () => {
        initCarousels();
        initDisclosures();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
</script>
