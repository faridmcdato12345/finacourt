import './theme';

const status = () => document.querySelector('[data-network-status]');

function updateNetworkStatus() {
    const element = status();
    if (!element) return;
    element.hidden = navigator.onLine;
    element.textContent = navigator.onLine
        ? ''
        : "You're offline. Live availability, reservations, and payments require a connection.";
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
                button.textContent = button.dataset.loadingLabel || 'Working...';
            }
        });
    });
}

function bindHoldCountdowns() {
    document.querySelectorAll('[data-hold-countdown]').forEach((countdown) => {
        if (countdown.dataset.enhanced === 'true') return;
        countdown.dataset.enhanced = 'true';

        const expiresAt = Date.parse(countdown.dataset.expiresAt || '');
        if (Number.isNaN(expiresAt)) {
            countdown.textContent = 'Check booking status';
            return;
        }

        const update = () => {
            const remainingSeconds = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            if (remainingSeconds === 0) {
                countdown.textContent = 'Expired — refresh to check availability';
                return true;
            }

            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            countdown.textContent = `${minutes}m ${String(seconds).padStart(2, '0')}s`;

            return false;
        };

        if (update()) return;

        const interval = window.setInterval(() => {
            if (update()) window.clearInterval(interval);
        }, 1000);
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
        button.hidden = true;
        await prompt.prompt();
        prompt = null;
    });
}

function bindDirectoryInfiniteScroll() {
    const roots = document.querySelectorAll('[data-directory-infinite-scroll]');

    for (const root of roots) {
        const sentinel = root.querySelector('[data-directory-sentinel]');
        const loadingIndicator = root.querySelector('[data-directory-loading]');
        const fallbackLink = root.querySelector('[data-directory-fallback]');
        const retryButton = root.querySelector('[data-directory-retry]');
        let loading = false;
        let observer;

        const loadNextPage = async () => {
            if (loading) return;
            loading = true;
            sentinel.setAttribute('aria-busy', 'true');
            if (loadingIndicator) loadingIndicator.hidden = false;
            if (retryButton) retryButton.hidden = true;

            const nextUrl = sentinel.dataset.nextUrl;
            if (!nextUrl) {
                loading = false;
                sentinel.setAttribute('aria-busy', 'false');
                if (loadingIndicator) loadingIndicator.hidden = true;
                return;
            }

            try {
                const response = await fetch(nextUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error('Network response was not ok');

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newItems = doc.querySelectorAll('[data-directory-item]');
                const container = root.querySelector('[data-directory-container]');
                if (container) {
                    for (const item of newItems) container.appendChild(item);
                }

                const newSentinel = doc.querySelector('[data-directory-sentinel]');
                if (newSentinel) {
                    sentinel.dataset.nextUrl = newSentinel.dataset.nextUrl;
                    if (!sentinel.dataset.nextUrl) {
                        observer.disconnect();
                        sentinel.hidden = true;
                        if (fallbackLink) fallbackLink.hidden = false;
                    }
                } else {
                    observer.disconnect();
                    sentinel.hidden = true;
                    if (fallbackLink) fallbackLink.hidden = false;
                }
            } catch (error) {
                console.warn('Loading more directory venues failed.', error);
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
    }, { rootMargin: '0px 0px -7% 0px', threshold: 0 });

    revealTargets.forEach((target) => observer.observe(target));
    requestAnimationFrame(() => {
        root.dataset.playerMotion = 'ready';
    });
}

function bindConsecutiveSlotPickers() {
    document.querySelectorAll('[data-slot-picker]').forEach((picker) => {
        if (picker.dataset.enhanced === 'true') return;
        const slots = Array.from(picker.querySelectorAll('[data-slot]'));
        const summary = picker.querySelector('[data-slot-summary]');
        const summaryTime = picker.querySelector('[data-slot-summary-time]');
        const summaryDetail = picker.querySelector('[data-slot-summary-detail]');
        const continueLink = picker.querySelector('[data-slot-continue]');
        const maximumDuration = Number(picker.dataset.maximumDuration || 1440);
        let selected = [];

        if (!slots.length || !summary || !summaryTime || !summaryDetail || !continueLink) return;

        picker.dataset.enhanced = 'true';

        // Group selected slots by resource
        const getSelectionsByResource = () => {
            const groups = new Map();
            for (const slot of selected) {
                const resourceId = slot.dataset.resource;
                if (!groups.has(resourceId)) {
                    groups.set(resourceId, []);
                }
                groups.get(resourceId).push(slot);
            }
            return groups;
        };

        // Sort slots within each resource by startOffset
        const sortSlotsByTime = (slots) => [...slots].sort((a, b) => Number(a.dataset.startOffset) - Number(b.dataset.startOffset));

        // Validate that slots in a group are consecutive
        const areConsecutive = (slots) => {
            const sorted = sortSlotsByTime(slots);
            for (let i = 1; i < sorted.length; i++) {
                const prev = sorted[i - 1];
                const curr = sorted[i];
                if (Number(curr.dataset.slotIndex) !== Number(prev.dataset.slotIndex) + 1) return false;
                if (Number(curr.dataset.startOffset) !== Number(prev.dataset.endOffset)) return false;
            }
            return true;
        };

        const update = () => {
            slots.forEach((slot) => slot.setAttribute('aria-pressed', selected.includes(slot) ? 'true' : 'false'));
            summary.hidden = selected.length === 0;

            if (!selected.length) {
                continueLink.removeAttribute('href');
                continueLink.setAttribute('aria-disabled', 'true');
                return;
            }

            const groups = getSelectionsByResource();
            const selections = Array.from(groups.entries()).map(([resourceId, resourceSlots]) => {
                const sorted = sortSlotsByTime(resourceSlots);
                const first = sorted[0];
                const last = sorted[sorted.length - 1];
                return { resourceId, sorted, first, last, duration: Number(last.dataset.endOffset) - Number(first.dataset.startOffset) };
            });
            const selectedPrice = selected.reduce((total, slot) => total + Number(slot.dataset.price || 0), 0);
            const url = new URL(picker.dataset.reviewUrl, window.location.origin);
            if (selections.length === 1) {
                const item = selections[0];
                url.searchParams.set('resource', item.resourceId);
                url.searchParams.set('date', item.first.dataset.date || picker.dataset.date);
                url.searchParams.set('start', item.first.dataset.start);
                url.searchParams.set('duration', String(item.duration));
                if (item.first.dataset.campaign && item.sorted.every((slot) => slot.dataset.campaign === item.first.dataset.campaign)) url.searchParams.set('campaign', item.first.dataset.campaign);
                summaryTime.textContent = `${item.first.dataset.startLabel}–${item.last.dataset.endLabel}`;
                summaryDetail.textContent = `${item.sorted.length} ${item.sorted.length === 1 ? 'slot' : 'consecutive slots'} · ${item.duration} minutes · ${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(selectedPrice)}`;
            } else {
                url.pathname = `${url.pathname.replace(/\/$/, '')}/multi`;
                selections.forEach((item, index) => {
                    url.searchParams.set(`resources[${index}][resource_id]`, item.resourceId);
                    url.searchParams.set(`resources[${index}][date]`, item.first.dataset.date || picker.dataset.date);
                    url.searchParams.set(`resources[${index}][start_time]`, item.first.dataset.start);
                    url.searchParams.set(`resources[${index}][duration_minutes]`, String(item.duration));
                    if (item.first.dataset.campaign && item.sorted.every((slot) => slot.dataset.campaign === item.first.dataset.campaign)) url.searchParams.set(`resources[${index}][campaign]`, item.first.dataset.campaign);
                });
                summaryTime.textContent = `${selections.length} courts selected`;
                summaryDetail.textContent = `${selected.length} slots across ${selections.length} courts · ${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(selectedPrice)}`;
            }

            continueLink.href = url.toString();
            continueLink.setAttribute('aria-disabled', 'false');
        };

        slots.forEach((slot) => {
            slot.addEventListener('click', (event) => {
                event.preventDefault();
                const selectedIndex = selected.indexOf(slot);

                if (selectedIndex !== -1) {
                    // Remove slot from selection
                    selected.splice(selectedIndex, 1);

                    update();
                    return;
                }

                // Adding a new slot
                if (!selected.length) {
                    selected = [slot];
                    update();
                    return;
                }

                const groups = getSelectionsByResource();
                const resourceId = slot.dataset.resource;
                const resourceSlots = groups.get(resourceId) || [];

                if (resourceSlots.length === 0) {
                    // A second court is allowed; each court retains its own consecutive range.
                    selected = [...selected, slot];
                    update();
                    return;
                }

                // Same resource - check if it extends the selection consecutively
                const sorted = sortSlotsByTime([...resourceSlots, slot]);
                if (areConsecutive(sorted)) {
                    selected = [...selected, slot];
                    update();
                    return;
                }

                // Not consecutive - replace this resource's selection with the new slot
                selected = selected.filter(s => s.dataset.resource !== resourceId);
                selected = [...selected, slot];
                update();
            });
        });

        continueLink.addEventListener('click', (event) => {
            if (continueLink.getAttribute('aria-disabled') === 'true') event.preventDefault();
        });
    });
}

function bindMobileCourtPickers() {
    document.querySelectorAll('[data-mobile-court-picker]').forEach((picker) => {
        if (picker.dataset.mobileCourtsEnhanced === 'true') return;
        picker.dataset.mobileCourtsEnhanced = 'true';

        const tabs = [...picker.querySelectorAll('[data-mobile-court-tab]')];
        const panels = [...picker.querySelectorAll('[data-mobile-court-panel]')];

        const showCourt = (resourceId) => {
            tabs.forEach((tab) => {
                const active = tab.dataset.resourceId === resourceId;
                tab.setAttribute('aria-selected', String(active));
                tab.classList.toggle('border-court-600', active);
                tab.classList.toggle('bg-court-700', active);
                tab.classList.toggle('text-white', active);
                tab.classList.toggle('shadow-sm', active);
                tab.classList.toggle('border-slate-200', !active);
                tab.classList.toggle('bg-white', !active);
                tab.classList.toggle('text-slate-700', !active);

                tab.querySelectorAll('span').forEach((label, index) => {
                    if (index !== 1) return;
                    label.classList.toggle('text-court-100', active);
                    label.classList.toggle('text-slate-400', !active);
                });
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.resourceId !== resourceId;
            });
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => showCourt(tab.dataset.resourceId));
        });

        // Deal links can target a court that appears later in the horizontal
        // list. Keep the selected tab in view instead of making the player
        // discover that their promoted court is off-screen.
        const initiallySelectedTab = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true');
        if (initiallySelectedTab) {
            requestAnimationFrame(() => {
                initiallySelectedTab.scrollIntoView({
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    block: 'nearest',
                    inline: 'center',
                });
            });
        }
    });
}

function bindPageSharing() {
    document.querySelectorAll('[data-share-page]').forEach((button) => {
        if (button.dataset.enhanced === 'true') return;
        button.dataset.enhanced = 'true';

        button.addEventListener('click', async () => {
            const url = button.dataset.shareUrl || window.location.href;
            const title = button.dataset.shareTitle || document.title;
            const label = button.querySelector('[data-share-label]');
            const originalLabel = label?.textContent || 'Share';

            try {
                if (navigator.share) {
                    await navigator.share({ title, url });
                    return;
                }

                if (!navigator.clipboard?.writeText) {
                    throw new Error('Clipboard sharing is unavailable.');
                }

                await navigator.clipboard.writeText(url);
                if (label) label.textContent = 'Link copied';
                window.setTimeout(() => {
                    if (label) label.textContent = originalLabel;
                }, 2000);
            } catch (error) {
                // Closing the native share sheet is an expected choice, not an application error.
                if (error?.name === 'AbortError') return;

                if (label) label.textContent = 'Could not share';
                window.setTimeout(() => {
                    if (label) label.textContent = originalLabel;
                }, 2500);
            }
        });
    });
}

function bindLiveScheduleNavigation() {
    document.querySelectorAll('[data-live-schedule-date]').forEach((link) => {
        if (link.dataset.enhanced === 'true') return;
        link.dataset.enhanced = 'true';
        link.addEventListener('click', async (event) => {
            if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            const panel = link.closest('[data-live-availability]');
            if (!panel) return;
            panel.classList.add('pointer-events-none', 'opacity-60');
            try {
                const response = await fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error(`Schedule request failed (${response.status})`);
                const documentResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
                const replacement = documentResponse.querySelector('[data-live-availability]');
                if (!replacement) throw new Error('Schedule panel was not found in the response.');
                panel.replaceWith(replacement);
                window.history.pushState({}, '', link.href);
                document.dispatchEvent(new CustomEvent('marketplace:availability-replaced', { detail: { root: replacement } }));
                bindLiveScheduleNavigation();
                bindMobileCourtPickers();
                bindConsecutiveSlotPickers();
            } catch (error) {
                console.error(error);
                window.location.assign(link.href);
            }
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
            icon: '/icons/app-logo.png',
            badge: '/icons/app-logo.png',
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
    bindHoldCountdowns();
    bindInstallPrompt();
    bindDirectoryInfiniteScroll();
    bindPlayerExperience();
    bindMobileCourtPickers();
    bindConsecutiveSlotPickers();
    bindLiveScheduleNavigation();
    bindPageSharing();
    bindCourtCarousels();

    try {
        const registration = await navigator.serviceWorker.ready;
        await bindNotificationPermission(registration);
    } catch (error) {
        console.error('Service worker not ready:', error);
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then(registerServiceWorker).catch(console.error);
    });
}
