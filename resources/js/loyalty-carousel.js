export function initLoyaltyCarousels(documentRef = document, windowRef = window) {
    documentRef.querySelectorAll('[data-loyalty-carousel-controls]').forEach((controls) => {
        const scroller = documentRef.getElementById('loyalty-cards');
        if (!scroller) return;

        const previous = controls.querySelector('[data-loyalty-direction="previous"]');
        const next = controls.querySelector('[data-loyalty-direction="next"]');

        const updateButtons = () => {
            const lastPosition = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
            previous.disabled = scroller.scrollLeft <= 1;
            next.disabled = scroller.scrollLeft >= lastPosition - 1;
        };

        [previous, next].forEach((button) => {
            button.addEventListener('click', () => {
                const card = scroller.querySelector('[data-loyalty-card]');
                const gap = Number.parseFloat(windowRef.getComputedStyle(scroller).columnGap) || 0;
                const distance = (card?.getBoundingClientRect().width || scroller.clientWidth) + gap;
                scroller.scrollBy({
                    left: button.dataset.loyaltyDirection === 'next' ? distance : -distance,
                    behavior: 'smooth',
                });
            });
        });

        scroller.addEventListener('scroll', updateButtons, { passive: true });
        windowRef.addEventListener('resize', updateButtons);
        windowRef.requestAnimationFrame(updateButtons);
    });
}

if (typeof document !== 'undefined') initLoyaltyCarousels();
