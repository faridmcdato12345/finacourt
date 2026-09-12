import './theme';

const status = () => document.querySelector('[data-network-status]');

function updateNetworkStatus() {
    const element = status();
    if (!element) return;
    element.hidden = navigator.onLine;
    element.textContent = navigator.onLine
        ? ''
        : 'You’re offline. Live availability, reservations, and payments require a connection.';
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return null;

    try {
        return await navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' });
    } catch (error) {
        console.warn('Service worker registration failed.', error);
        return null;
    }
}

function bindOnlineForms() {
    document.querySelectorAll('form[data-requires-online]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!navigator.onLine) {
                event.preventDefault();
                updateNetworkStatus();
                status()?.focus();
                return;
            }

            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = button.dataset.loadingLabel || 'Working…';
            }
        });
    });
}

function bindInstallPrompt() {
    const button = document.querySelector('[data-install-app]');
    if (!button) return;
    let prompt;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        prompt = event;
        button.hidden = false;
    });

    button.addEventListener('click', async () => {
        if (!prompt) return;
        await prompt.prompt();
        prompt = null;
        button.hidden = true;
    });
}

function bindPageSharing() {
    document.querySelectorAll('[data-share-page]').forEach((button) => {
        button.addEventListener('click', async () => {
            const title = button.dataset.shareTitle || document.title;
            const url = button.dataset.shareUrl || window.location.href;
            const label = button.querySelector('[data-share-label]');

            try {
                if (navigator.share) {
                    await navigator.share({ title, url });
                    return;
                }

                await navigator.clipboard.writeText(url);
                if (label) label.textContent = 'Link copied';
                window.setTimeout(() => {
                    if (label) label.textContent = 'Share';
                }, 2000);
            } catch (error) {
                if (error.name !== 'AbortError') console.warn('Page sharing failed.', error);
            }
        });
    });
}

function bindCourtCarousels() {
    document.querySelectorAll('[data-court-carousel]').forEach((carousel) => {
        const scroller = carousel.querySelector('[data-popular-courts-carousel]');
        const previous = carousel.querySelector('[data-carousel-previous]');
        const next = carousel.querySelector('[data-carousel-next]');

        if (!scroller || !previous || !next) return;

        const updateControls = () => {
            if (!window.matchMedia('(min-width: 640px)').matches) {
                previous.hidden = true;
                next.hidden = true;
                return;
            }

            const maximumScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
            previous.hidden = scroller.scrollLeft <= 4;
            next.hidden = maximumScroll <= 4 || scroller.scrollLeft >= maximumScroll - 4;
        };
        const scroll = (direction) => {
            const card = scroller.firstElementChild;
            const gap = Number.parseFloat(window.getComputedStyle(scroller).columnGap) || 0;
            const distance = card ? card.getBoundingClientRect().width + gap : scroller.clientWidth * 0.8;
            scroller.scrollBy({ left: direction * distance, behavior: 'smooth' });
        };

        previous.addEventListener('click', () => scroll(-1));
        next.addEventListener('click', () => scroll(1));
        scroller.addEventListener('scroll', updateControls, { passive: true });
        window.addEventListener('resize', updateControls, { passive: true });
        updateControls();
    });
}

function bindDirectoryInfiniteScroll() {
    const root = document.querySelector('[data-directory-infinite-scroll]');
    if (!root) return;

    const results = root.querySelector('[data-directory-results]');
    const sentinel = root.querySelector('[data-directory-scroll-sentinel]');
    const status = root.querySelector('[data-directory-scroll-status]');
    const loadingIndicator = root.querySelector('[data-directory-loading-indicator]');
    const fallbackLink = root.querySelector('[data-directory-load-more]');
    const retryButton = root.querySelector('[data-directory-retry]');
    let nextUrl = root.dataset.nextUrl || '';
    let shown = Number(root.dataset.shown || results?.children.length || 0);
    const total = Number(root.dataset.total || shown);
    let loading = false;
    let observer;

    if (!results || !sentinel || !status || !nextUrl) return;

    const finish = () => {
        nextUrl = '';
        root.dataset.nextUrl = '';
        observer?.disconnect();
        if (fallbackLink) fallbackLink.hidden = true;
        if (retryButton) retryButton.hidden = true;
        status.textContent = `All ${total} ${total === 1 ? 'venue is' : 'venues are'} shown.`;
    };

    const loadNextPage = async () => {
        if (loading || !nextUrl) return;

        loading = true;
        sentinel.setAttribute('aria-busy', 'true');
        status.textContent = 'Loading more venues…';
        if (loadingIndicator) loadingIndicator.hidden = false;
        if (retryButton) retryButton.hidden = true;

        try {
            const response = await fetch(nextUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
            });

            if (!response.ok) throw new Error(`Directory page returned ${response.status}.`);

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextResults = page.querySelector('[data-directory-results]');
            const nextSentinel = page.querySelector('[data-directory-infinite-scroll]');
            const existingKeys = new Set(
                [...results.querySelectorAll('[data-directory-card]')]
                    .map((card) => card.dataset.listingKey),
            );
            const cards = nextResults
                ? [...nextResults.querySelectorAll('[data-directory-card]')]
                : [];

            if (!cards.length) throw new Error('The next directory page did not contain venues.');

            let added = 0;
            cards.forEach((card) => {
                if (existingKeys.has(card.dataset.listingKey)) return;

                const importedCard = document.importNode(card, true);
                importedCard.classList.add('player-card-motion', 'player-reveal', 'is-visible');
                results.append(importedCard);
                existingKeys.add(card.dataset.listingKey);
                added += 1;
            });

            shown = Math.min(total, shown + added);
            nextUrl = nextSentinel?.dataset.nextUrl || '';
            root.dataset.nextUrl = nextUrl;

            if (!nextUrl || shown >= total) {
                finish();
            } else {
                status.textContent = `Showing ${shown} of ${total} venues. Keep scrolling for more.`;
                if (fallbackLink) fallbackLink.href = nextUrl;
            }
        } catch (error) {
            console.warn('Loading more directory venues failed.', error);
            status.textContent = 'More venues could not be loaded. Check your connection and try again.';
            if (retryButton) retryButton.hidden = false;
        } finally {
            loading = false;
            sentinel.setAttribute('aria-busy', 'false');
            if (loadingIndicator) loadingIndicator.hidden = true;
        }
    };

    retryButton?.addEventListener('click', loadNextPage);

    if (!('IntersectionObserver' in window)) return;

    root.dataset.infiniteScroll = 'ready';
    if (fallbackLink) fallbackLink.hidden = true;
    observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) loadNextPage();
    }, { rootMargin: '600px 0px', threshold: 0 });
    observer.observe(sentinel);
}

function bindPlayerExperience() {
    const root = document.querySelector('.player-experience');
    if (!root) return;

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const revealTargets = [...new Set([
        ...document.querySelectorAll('.player-main > section'),
        ...document.querySelectorAll('.player-main [data-player-reveal]'),
        ...document.querySelectorAll('.player-main [data-player-card]'),
        ...document.querySelectorAll('.player-main article'),
        ...document.querySelectorAll('.player-main .app-card:not(form)'),
    ])];

    document.querySelectorAll('.player-main article, .player-main .app-card:not(form), .player-main [data-player-card]')
        .forEach((card) => card.classList.add('player-card-motion'));

    revealTargets.forEach((target, index) => {
        target.classList.add('player-reveal', `player-reveal-delay-${Math.min(index % 4, 3)}`);
    });

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        revealTargets.forEach((target) => target.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -7% 0px', threshold: 0.06 });

    revealTargets.forEach((target) => observer.observe(target));
    requestAnimationFrame(() => {
        root.dataset.playerMotion = 'ready';
    });
}

function bindConsecutiveSlotPickers() {
    document.querySelectorAll('[data-slot-picker]').forEach((picker) => {
        const slots = Array.from(picker.querySelectorAll('[data-slot]'));
        const summary = picker.querySelector('[data-slot-summary]');
        const summaryTime = picker.querySelector('[data-slot-summary-time]');
        const summaryDetail = picker.querySelector('[data-slot-summary-detail]');
        const continueLink = picker.querySelector('[data-slot-continue]');
        const maximumDuration = Number(picker.dataset.maximumDuration || 1440);
        let selected = [];

        if (!slots.length || !summary || !summaryTime || !summaryDetail || !continueLink) return;

        picker.dataset.enhanced = 'true';

        const update = () => {
            slots.forEach((slot) => slot.setAttribute('aria-pressed', selected.includes(slot) ? 'true' : 'false'));
            summary.hidden = selected.length === 0;

            if (!selected.length) {
                continueLink.removeAttribute('href');
                continueLink.setAttribute('aria-disabled', 'true');
                return;
            }

            const first = selected[0];
            const last = selected[selected.length - 1];
            const duration = Number(last.dataset.endOffset) - Number(first.dataset.startOffset);
            const url = new URL(picker.dataset.reviewUrl, window.location.origin);
            url.searchParams.set('resource', picker.dataset.resource);
            url.searchParams.set('date', first.dataset.date || picker.dataset.date);
            url.searchParams.set('start', first.dataset.start);
            url.searchParams.set('duration', String(duration));

            const campaigns = [...new Set(selected.map((slot) => slot.dataset.campaign).filter(Boolean))];
            if (campaigns.length === 1 && selected.every((slot) => slot.dataset.campaign === campaigns[0])) {
                url.searchParams.set('campaign', campaigns[0]);
            }

            summaryTime.textContent = `${first.dataset.startLabel}–${last.dataset.endLabel}`;
            const hasOnePriceContext = campaigns.length === 0
                || (campaigns.length === 1 && selected.every((slot) => slot.dataset.campaign === campaigns[0]));
            const selectedPrice = selected.reduce((total, slot) => total + Number(slot.dataset.price || 0), 0);
            const priceLabel = hasOnePriceContext
                ? ` · ${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(selectedPrice)}`
                : ' · final price shown next';
            summaryDetail.textContent = `${selected.length} ${selected.length === 1 ? 'slot' : 'consecutive slots'} · ${duration} minutes${priceLabel}`;
            continueLink.href = url.toString();
            continueLink.setAttribute('aria-disabled', 'false');
        };

        slots.forEach((slot) => {
            slot.addEventListener('click', (event) => {
                event.preventDefault();
                const selectedIndex = selected.indexOf(slot);

                if (selectedIndex !== -1) {
                    if (selected.length === 1) {
                        selected = [];
                    } else if (selectedIndex === 0) {
                        selected.shift();
                    } else if (selectedIndex === selected.length - 1) {
                        selected.pop();
                    } else {
                        selected = [slot];
                    }

                    update();
                    return;
                }

                if (!selected.length) {
                    selected = [slot];
                    update();
                    return;
                }

                const first = selected[0];
                const last = selected[selected.length - 1];
                const extendsAfter = Number(slot.dataset.slotIndex) === Number(last.dataset.slotIndex) + 1
                    && Number(slot.dataset.startOffset) === Number(last.dataset.endOffset);
                const extendsBefore = Number(slot.dataset.slotIndex) === Number(first.dataset.slotIndex) - 1
                    && Number(slot.dataset.endOffset) === Number(first.dataset.startOffset);
                const candidate = extendsAfter ? [...selected, slot] : extendsBefore ? [slot, ...selected] : [slot];
                const duration = Number(candidate[candidate.length - 1].dataset.endOffset)
                    - Number(candidate[0].dataset.startOffset);

                selected = duration <= maximumDuration ? candidate : [slot];
                update();
            });
        });

        continueLink.addEventListener('click', (event) => {
            if (continueLink.getAttribute('aria-disabled') === 'true') event.preventDefault();
        });
    });
}

async function bindNotificationPermission(registration) {
    const button = document.querySelector('[data-enable-notifications]');
    if (!button || !('Notification' in window) || !registration) return;
    button.hidden = Notification.permission === 'granted';

    button.addEventListener('click', async () => {
        const permission = await Notification.requestPermission();
        button.hidden = permission === 'granted';
        button.textContent = permission === 'denied' ? 'Browser alerts blocked' : button.textContent;
    });

    if (Notification.permission !== 'granted') return;
    const payloads = document.querySelector('[data-browser-notifications]');
    if (!payloads) return;

    for (const item of JSON.parse(payloads.textContent || '[]')) {
        const key = `court-notification:${item.id}`;
        if (localStorage.getItem(key)) continue;
        await registration.showNotification(item.title, {
            body: item.message,
            icon: '/icons/finacourt-logo-192.png',
            badge: '/icons/finacourt-logo-192.png',
            data: { url: item.url },
            tag: item.id,
        });
        localStorage.setItem(key, 'shown');
    }
}

window.addEventListener('online', updateNetworkStatus);
window.addEventListener('offline', updateNetworkStatus);

document.addEventListener('DOMContentLoaded', async () => {
    updateNetworkStatus();
    bindOnlineForms();
    bindConsecutiveSlotPickers();
    bindPageSharing();
    bindCourtCarousels();
    bindDirectoryInfiniteScroll();
    bindPlayerExperience();
    bindInstallPrompt();
    const registration = await registerServiceWorker();
    await bindNotificationPermission(registration);
});
