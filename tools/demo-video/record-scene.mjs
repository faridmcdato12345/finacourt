import fs from 'node:fs/promises';

const scene = process.argv[2];
const scenes = new Set([
    'intro',
    'discovery',
    'venue',
    'booking',
    'owner',
    'analytics',
    'links',
    'outro',
    'owner-intro',
    'owner-dashboard',
    'owner-bookings',
    'owner-analytics',
    'owner-links',
    'owner-promotions',
    'owner-visibility',
    'owner-outro',
]);

if (!scenes.has(scene)) {
    throw new Error(`Unknown scene: ${scene || '(missing)'}`);
}

const webdriverUrl = process.env.SELENIUM_URL || 'http://finacourt-demo-browser:4444';
const appUrl = (process.env.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
const password = process.env.DEMO_VIDEO_ACCOUNT_PASSWORD || 'finacourt-demo-only';
const outputDirectory = process.env.DEMO_VIDEO_OUTPUT || '/var/www/html/output/demo-video';
const controlDirectory = `${outputDirectory}/control`;
const manifest = JSON.parse(await fs.readFile(`${outputDirectory}/manifest.json`, 'utf8'));
const elementKey = 'element-6066-11e4-a52e-4f735466cecf';
let sessionId;

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function request(method, path, body) {
    const response = await fetch(`${webdriverUrl}${path}`, {
        method,
        headers: body === undefined ? {} : { 'content-type': 'application/json' },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok || payload?.value?.error) {
        throw new Error(`${method} ${path} failed: ${JSON.stringify(payload.value || payload)}`);
    }

    return payload.value;
}

async function command(method, path, body) {
    return request(method, `/session/${sessionId}${path}`, body);
}

async function createSession() {
    const value = await request('POST', '/session', {
        capabilities: {
            alwaysMatch: {
                browserName: 'chrome',
                pageLoadStrategy: 'normal',
                'goog:chromeOptions': {
                    args: [
                        '--no-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--disable-notifications',
                        '--disable-popup-blocking',
                        '--force-device-scale-factor=1',
                        '--ignore-certificate-errors',
                        '--window-size=1920,1080',
                        `--app=${appUrl}`,
                        '--kiosk',
                        '--start-fullscreen',
                        '--host-resolver-rules=MAP localhost host.docker.internal, MAP existing-booking.test 127.0.0.1',
                    ],
                    excludeSwitches: ['enable-automation'],
                    prefs: {
                        'credentials_enable_service': false,
                        'profile.password_manager_enabled': false,
                    },
                },
            },
        },
    });

    sessionId = value.sessionId;
    await command('POST', '/window/rect', { x: 0, y: 0, width: 1920, height: 1080 });
    await command('POST', '/window/fullscreen', {});

    const viewport = await execute(`return {
        innerWidth: window.innerWidth,
        innerHeight: window.innerHeight,
        outerWidth: window.outerWidth,
        outerHeight: window.outerHeight,
        screenWidth: window.screen.width,
        screenHeight: window.screen.height,
    }`);
    console.log(`Viewport check: ${JSON.stringify(viewport)}`);

    if (viewport.innerWidth !== 1920 || viewport.innerHeight !== 1080) {
        throw new Error(`Application viewport must fill the 1920x1080 recording frame: ${JSON.stringify(viewport)}`);
    }
}

async function navigate(path) {
    const url = path.startsWith('http') ? path : `${appUrl}${path}`;
    await command('POST', '/url', { url });
    await waitUntil(async () => (await execute('return document.readyState')) === 'complete', 20000, 'page load');
    await sleep(650);
    await sanitizeVisibleLocalOrigins();
}

async function sanitizeVisibleLocalOrigins() {
    const remaining = await execute(`
        const replaceOrigin = (value) => String(value ?? '')
            .replaceAll('http://localhost:8000', 'https://finacourt.asia')
            .replaceAll('http://127.0.0.1:8000', 'https://finacourt.asia');
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const textNodes = [];

        while (walker.nextNode()) textNodes.push(walker.currentNode);
        for (const node of textNodes) {
            const replacement = replaceOrigin(node.nodeValue);
            if (replacement !== node.nodeValue) node.nodeValue = replacement;
        }

        for (const field of document.querySelectorAll('input, textarea')) {
            const replacement = replaceOrigin(field.value);
            if (replacement !== field.value) field.value = replacement;
        }

        return {
            text: /localhost:8000|127\\.0\\.0\\.1:8000/.test(document.body?.innerText || ''),
            fields: [...document.querySelectorAll('input, textarea')].some((field) =>
                /localhost:8000|127\\.0\\.0\\.1:8000/.test(field.value)),
        };
    `);

    if (remaining.text || remaining.fields) {
        throw new Error(`A local application origin remains visible: ${JSON.stringify(remaining)}`);
    }
}

async function currentUrl() {
    return command('GET', '/url');
}

async function execute(script, args = []) {
    return command('POST', '/execute/sync', { script, args });
}

async function find(selector) {
    const value = await command('POST', '/element', { using: 'css selector', value: selector });
    return value[elementKey];
}

async function waitForSelector(selector, timeout = 15000) {
    let element;
    await waitUntil(async () => {
        try {
            element = await find(selector);
            return true;
        } catch {
            return false;
        }
    }, timeout, selector);
    return element;
}

async function waitForText(text, timeout = 15000) {
    await waitUntil(
        () => execute('return document.body && document.body.innerText.includes(arguments[0])', [text]),
        timeout,
        `text ${text}`,
    );
}

async function waitUntil(predicate, timeout, label) {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
        if (await predicate()) return;
        await sleep(150);
    }

    throw new Error(`Timed out waiting for ${label}`);
}

async function type(selector, value) {
    const element = await waitForSelector(selector);
    await command('POST', `/element/${element}/clear`, {});
    await command('POST', `/element/${element}/value`, { text: value, value: [...value] });
}

async function click(selector) {
    const element = await waitForSelector(selector);
    await command('POST', `/element/${element}/click`, {});
}

async function clickByText(tagName, text) {
    const clicked = await execute(`
        const target = [...document.querySelectorAll(arguments[0])]
            .find((element) => element.textContent.trim().includes(arguments[1]));
        if (!target) return false;
        target.click();
        return true;
    `, [tagName, text]);

    if (!clicked) throw new Error(`Could not find ${tagName} containing ${text}`);
}

async function scrollToSelector(selector, block = 'center') {
    const found = await execute(`
        const target = document.querySelector(arguments[0]);
        if (!target) return false;
        target.scrollIntoView({ behavior: 'smooth', block: arguments[1], inline: 'nearest' });
        return true;
    `, [selector, block]);

    if (!found) throw new Error(`Could not scroll to ${selector}`);
    await settleVerticalScroll();
}

async function scrollToText(text, block = 'center') {
    const found = await execute(`
        const target = [...document.querySelectorAll('h1,h2,h3,h4,p')]
            .find((element) => element.textContent.trim().includes(arguments[0]));
        if (!target) return false;
        target.closest('section,article,div').scrollIntoView({ behavior: 'smooth', block: arguments[1], inline: 'nearest' });
        return true;
    `, [text, block]);

    if (!found) throw new Error(`Could not scroll to text ${text}`);
    await settleVerticalScroll();
}

async function settleVerticalScroll() {
    await sleep(750);
    await execute(`
        const root = document.documentElement;
        const top = document.scrollingElement.scrollTop;
        root.style.setProperty('scroll-behavior', 'auto', 'important');
        document.scrollingElement.scrollLeft = 0;
        document.body.scrollLeft = 0;
        window.scrollTo({ left: 0, top, behavior: 'auto' });
        document.querySelectorAll('aside').forEach((aside) => { aside.scrollLeft = 0; });
        void document.body.offsetWidth;
        root.style.setProperty('scroll-behavior', 'smooth', 'important');
    `);
}

async function jumpToSelector(selector, block = 'center') {
    const found = await execute(`
        const target = document.querySelector(arguments[0]);
        if (!target) return false;
        const root = document.documentElement;
        root.style.setProperty('scroll-behavior', 'auto', 'important');
        target.scrollIntoView({ behavior: 'auto', block: arguments[1], inline: 'nearest' });
        document.scrollingElement.scrollLeft = 0;
        document.body.scrollLeft = 0;
        window.scrollTo({ left: 0, top: document.scrollingElement.scrollTop, behavior: 'auto' });
        document.querySelectorAll('aside').forEach((aside) => { aside.scrollLeft = 0; });
        return true;
    `, [selector, block]);

    if (!found) throw new Error(`Could not position at ${selector}`);
    await sleep(450);
}

async function jumpToText(text, block = 'center') {
    const found = await execute(`
        const target = [...document.querySelectorAll('h1,h2,h3,h4,p')]
            .find((element) => element.textContent.trim().includes(arguments[0]));
        if (!target) return false;
        const root = document.documentElement;
        root.style.setProperty('scroll-behavior', 'auto', 'important');
        target.closest('section,article,div').scrollIntoView({ behavior: 'auto', block: arguments[1], inline: 'nearest' });
        document.scrollingElement.scrollLeft = 0;
        document.body.scrollLeft = 0;
        window.scrollTo({ left: 0, top: document.scrollingElement.scrollTop, behavior: 'auto' });
        document.querySelectorAll('aside').forEach((aside) => { aside.scrollLeft = 0; });
        return true;
    `, [text, block]);

    if (!found) throw new Error(`Could not position at text ${text}`);
    await sleep(450);
}

async function login(kind) {
    await navigate(kind === 'owner' ? '/login' : '/player/login');
    await type(kind === 'owner' ? '#email' : 'input[name="email"]', kind === 'owner' ? manifest.owner_email : manifest.player_email);
    await type(kind === 'owner' ? '#password' : '#player-password', password);
    await click('button[type="submit"], form button:not([type])');
    await waitUntil(async () => {
        const url = await currentUrl();
        return !url.includes('/login');
    }, 20000, `${kind} login`);
    await sleep(700);
}

async function injectCaption(title, detail, { demo = false } = {}) {
    await execute(`
        document.querySelector('#finacourt-video-caption')?.remove();
        document.querySelector('#finacourt-video-demo-badge')?.remove();
        const styleId = 'finacourt-video-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = [
                'html { scroll-behavior: smooth !important; }',
                '#finacourt-video-caption { position: fixed; z-index: 2147483646; left: 50%; bottom: 40px; width: min(980px, calc(100vw - 96px)); transform: translateX(-50%); display: flex; align-items: center; gap: 18px; padding: 18px 24px; border: 1px solid rgba(255,255,255,.16); border-radius: 20px; color: white; background: rgba(8,42,33,.94); box-shadow: 0 22px 60px rgba(0,0,0,.24); backdrop-filter: blur(16px); font-family: Inter,ui-sans-serif,system-ui,sans-serif; }',
                '#finacourt-video-caption .cap-mark { width: 10px; height: 48px; flex: 0 0 auto; border-radius: 999px; background: #7ee0ae; }',
                '#finacourt-video-caption .cap-copy { min-width: 0; flex: 1 1 auto; }',
                '#finacourt-video-caption strong { display: block; font-size: 24px; line-height: 1.2; letter-spacing: -.02em; }',
                '#finacourt-video-caption span { display: block; margin-top: 5px; color: #c9ded6; font-size: 15px; line-height: 1.4; }',
                '#finacourt-video-demo-badge { flex: 0 0 auto; margin-left: auto; padding: 9px 13px; border-radius: 999px; color: #0b3b2d; background: #d8f5e7; font: 800 12px/1 Inter,ui-sans-serif,system-ui,sans-serif; letter-spacing: .12em; text-transform: uppercase; }',
            ].join('');
            document.head.appendChild(style);
        }
        const caption = document.createElement('div');
        caption.id = 'finacourt-video-caption';
        const mark = document.createElement('i');
        mark.className = 'cap-mark';
        const copy = document.createElement('div');
        copy.className = 'cap-copy';
        const heading = document.createElement('strong');
        heading.textContent = arguments[0];
        const body = document.createElement('span');
        body.textContent = arguments[1];
        copy.append(heading, body);
        caption.append(mark, copy);
        if (arguments[2]) {
            const badge = document.createElement('div');
            badge.id = 'finacourt-video-demo-badge';
            badge.textContent = 'Demo data';
            caption.appendChild(badge);
        }
        document.body.appendChild(caption);
        document.documentElement.style.cursor = 'none';
    `, [title, detail, demo]);
}

async function injectTitleCard(outro = false, ownerFocused = false) {
    const alignment = await execute(`
        document.querySelector('#finacourt-video-title')?.remove();
        const card = document.createElement('div');
        card.id = 'finacourt-video-title';
        card.style.cssText = 'position:fixed;left:0;top:0;width:100vw;height:100vh;z-index:2147483647;display:flex;align-items:center;justify-content:center;color:white;background:radial-gradient(circle at 68% 22%,#287b5c 0,#124d3a 28%,#062d23 70%);font-family:Inter,ui-sans-serif,system-ui,sans-serif;';
        const content = document.createElement('div');
        content.dataset.finacourtTitleContent = '';
        content.style.cssText = 'width:min(1120px,calc(100vw - 120px));margin:0 auto;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;';
        const logo = document.createElement('img');
        logo.src = '/icons/finacourt-logo.png';
        logo.alt = '';
        logo.style.cssText = 'display:block;width:112px;height:112px;margin:0 auto;object-fit:contain;border-radius:28px;background:white;box-shadow:0 25px 70px rgba(0,0,0,.25);';
        const brand = document.createElement('div');
        brand.textContent = 'FinACourt';
        brand.style.cssText = 'margin-top:24px;font-size:32px;font-weight:800;letter-spacing:.06em;';
        const title = document.createElement('h1');
        title.textContent = arguments[0]
            ? (arguments[1] ? 'Keep what already works.' : 'Fill more court hours with clarity.')
            : (arguments[1] ? 'Run your courts with clarity.' : 'More players. More bookings. Better visibility.');
        title.style.cssText = 'margin:26px auto 0;max-width:1000px;font-size:72px;line-height:1.02;letter-spacing:-.055em;';
        const detail = document.createElement('p');
        detail.textContent = arguments[0]
            ? (arguments[1] ? 'Understand your traffic. Fill more court hours. Grow your venue.' : 'Discovery, bookings, and source insights—working with the tools you already use.')
            : (arguments[1] ? 'Bookings, schedules and growth tools in one place.' : 'FinACourt for sports court owners');
        detail.style.cssText = 'margin:24px auto 0;max-width:900px;color:#cae9dc;font-size:24px;line-height:1.5;';
        const url = document.createElement('div');
        url.textContent = arguments[0] || arguments[1] ? 'finacourt.asia' : 'Marketplace + owner tools';
        url.style.cssText = 'display:inline-flex;margin-top:34px;padding:12px 20px;border:1px solid rgba(255,255,255,.22);border-radius:999px;color:#e9fff5;background:rgba(255,255,255,.08);font-size:16px;font-weight:750;letter-spacing:.08em;';
        content.append(logo, brand, title, detail, url);
        card.appendChild(content);
        document.body.appendChild(card);
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        document.documentElement.style.cursor = 'none';
        const logoBounds = logo.getBoundingClientRect();
        const contentBounds = content.getBoundingClientRect();
        return {
            frameCenterX: window.innerWidth / 2,
            logoCenterX: logoBounds.left + (logoBounds.width / 2),
            contentCenterX: contentBounds.left + (contentBounds.width / 2),
        };
    `, [outro, ownerFocused]);

    const logoOffset = Math.abs(alignment.logoCenterX - alignment.frameCenterX);
    const contentOffset = Math.abs(alignment.contentCenterX - alignment.frameCenterX);
    console.log(`Title alignment check: ${JSON.stringify(alignment)}`);

    if (logoOffset > 1 || contentOffset > 1) {
        throw new Error(`Title card is not centered in the recording frame: ${JSON.stringify(alignment)}`);
    }
}

async function prepare() {
    const venuePath = `/venues/${manifest.venue_slug}?resource=${manifest.resource_id}&date=${manifest.booking_date}&duration=${manifest.booking_duration}`;

    switch (scene) {
        case 'intro':
            await navigate('/');
            await injectTitleCard(false);
            break;
        case 'owner-intro':
            await navigate('/');
            await injectTitleCard(false, true);
            break;
        case 'discovery':
            await navigate('/');
            await waitForSelector('[data-player-hero]');
            await injectCaption('Discover the right court', 'Search by city, sport, schedule, and price.');
            break;
        case 'venue':
            await navigate(venuePath);
            await waitForText(manifest.venue_name);
            await injectCaption('See the venue before you book', 'Compare courts, rates, facilities, and live opening hours.');
            break;
        case 'booking':
            await login('player');
            await navigate(`/venues/${manifest.venue_slug}/reserve?resource=${manifest.resource_id}&date=${manifest.booking_date}&start=${manifest.booking_start}&duration=${manifest.booking_duration}`);
            await waitForSelector('[data-booking-payment-pricing]');
            await injectCaption('Reserve without surprises', 'Review the court, time, and total before creating a safe hold.');
            break;
        case 'owner':
            await login('owner');
            await navigate('/owner/dashboard');
            await waitForText('Where Your Bookings Come From');
            await injectCaption('Run the day from one workspace', 'Bookings, court activity, and revenue stay organized.', { demo: true });
            break;
        case 'analytics':
            await login('owner');
            await navigate(`/owner/analytics?venue=${manifest.venue_id}`);
            await waitForText('Visits & bookings');
            await injectCaption('Turn activity into clear decisions', 'Follow the journey from discovery to confirmed booking.', { demo: true });
            break;
        case 'links':
            await login('owner');
            await navigate(`/owner/booking-links?venue=${manifest.venue_id}&range=this_month`);
            await waitForText('Booking Links');
            await injectCaption('Keep your current booking platform', 'Create a measured link for Facebook, Google, Instagram, QR, or sharing.', { demo: true });
            break;
        case 'outro':
            await navigate('/');
            await injectTitleCard(true);
            break;
        case 'owner-dashboard':
            await login('owner');
            await navigate('/owner/dashboard');
            await waitForText('Where Your Bookings Come From');
            await jumpToText('Today’s schedule', 'center');
            await injectCaption('Manage your court in one place', 'Bookings, schedules and growth tools stay together.', { demo: true });
            break;
        case 'owner-bookings':
            await login('owner');
            await navigate('/owner/bookings');
            await waitForSelector('input[type="date"]');
            await injectCaption('See bookings and schedules', 'Court, time, player, status and booking value at a glance.', { demo: true });
            break;
        case 'owner-analytics':
            await login('owner');
            await navigate(`/owner/analytics?venue=${manifest.venue_id}`);
            await waitForText('Visits & bookings');
            await jumpToText('Where players first found you', 'center');
            await injectCaption('Know where players come from', 'Confirmed bookings and external clicks stay clearly separated.', { demo: true });
            break;
        case 'owner-links':
            await login('owner');
            await navigate(`/owner/booking-links?venue=${manifest.venue_id}&range=this_month`);
            await waitForText('Booking Links');
            await jumpToText('Create a link for each channel', 'center');
            await injectCaption('Keep your current booking platform', 'FinACourt adds trackable links without replacing it.', { demo: true });
            break;
        case 'owner-promotions':
            await login('owner');
            await navigate('/owner/promotions');
            await waitForText('Turn an open court time into a promotion');
            await jumpToText('Your promotions', 'center');
            await injectCaption('Fill empty court hours', 'Use real openings to promote slower times.', { demo: true });
            break;
        case 'owner-visibility':
            await login('owner');
            await navigate('/owner/visibility');
            await waitForText('Help players find you');
            await jumpToSelector('[data-visibility-venue]', 'center');
            await injectCaption('Improve your online visibility', 'Keep venue details, hours and booking links ready to share.', { demo: true });
            break;
        case 'owner-outro':
            await navigate('/');
            await injectTitleCard(true, true);
            break;
    }
}

async function perform() {
    switch (scene) {
        case 'intro':
            await sleep(4500);
            break;
        case 'owner-intro':
            await sleep(4000);
            break;
        case 'discovery':
            await sleep(2300);
            await navigate('/courts?city=makati&sport=pickleball');
            await waitForText(manifest.venue_name);
            await execute(`
                const heading = [...document.querySelectorAll('h2')].find((node) => node.textContent.includes(arguments[0]));
                heading?.closest('article')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            `, [manifest.venue_name]);
            await injectCaption('Real public inventory, ready to compare', 'Players see verified venues, available sports, and transparent hourly rates.');
            await sleep(4400);
            break;
        case 'venue':
            await sleep(2500);
            await scrollToSelector('[data-live-availability]', 'center');
            await sleep(900);
            await injectCaption('Live times in a familiar 12-hour clock', 'Availability is checked again when the player creates a hold.');
            await sleep(3600);
            break;
        case 'booking':
            await sleep(2100);
            await execute(`
                const radio = document.querySelector('input[name="payment_option"][value="pay_at_venue"]');
                if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
                const terms = document.querySelector('input[name="terms"]');
                if (terms) { terms.checked = true; terms.dispatchEvent(new Event('change', { bubbles: true })); }
                return Boolean(radio && terms);
            `);
            await clickByText('button', 'Hold this time');
            await waitForSelector('[data-player-hold-card]', 20000);
            await injectCaption('A safe hold protects the selected time', 'This demo uses pay at venue—no card or live payment is involved.');
            await sleep(1900);
            await clickByText('button', 'Confirm — pay at venue');
            await waitForSelector('[data-booking-celebration]', 20000);
            await injectCaption('Booking confirmed', 'The player and court owner now share one clear reservation record.');
            await sleep(3000);
            break;
        case 'owner':
            await sleep(2500);
            await scrollToText('Today’s schedule', 'center');
            await sleep(900);
            await injectCaption('Today’s schedule stays actionable', 'See each time, player, court, amount, and payment status at a glance.', { demo: true });
            await sleep(3300);
            break;
        case 'analytics':
            await sleep(2300);
            await scrollToText('Where players first found you', 'center');
            await sleep(900);
            await injectCaption('Know what is bringing players in', 'FinACourt separates first discovery from the source credited for booking.', { demo: true });
            await sleep(3500);
            break;
        case 'links':
            await sleep(2100);
            await scrollToText('Create a link for each channel', 'center');
            await sleep(900);
            await clickByText('button', 'Copy Link');
            await injectCaption('One destination, measurable channels', 'Existing booking links keep working while FinACourt records the source.', { demo: true });
            await sleep(1700);
            await navigate(`/go/${manifest.external_link_token}`);
            await waitForText('Keep the booking system you already use.');
            await injectCaption('A clean handoff to the owner’s platform', 'FinACourt measures the visit, then sends the player straight through.');
            await sleep(2900);
            break;
        case 'outro':
            await sleep(4800);
            break;
        case 'owner-dashboard':
            await sleep(3200);
            await injectCaption('Run today’s schedule with confidence', 'See each court, time, player and payment status.', { demo: true });
            await sleep(3800);
            break;
        case 'owner-bookings':
            await sleep(3300);
            await injectCaption('Court schedules stay easy to scan', 'Confirmed reservations and open court time are organized by court.', { demo: true });
            await sleep(3700);
            break;
        case 'owner-analytics':
            await sleep(3200);
            await injectCaption('See where to focus your marketing', 'Google, social, FinACourt search, QR and shared links use real confirmed bookings.', { demo: true });
            await sleep(4800);
            break;
        case 'owner-links':
            await sleep(2000);
            await injectCaption('Track the traffic going to it', 'Each channel shows clicks—not unverified external bookings.', { demo: true });
            await sleep(2300);
            await navigate(`/go/${manifest.external_link_token}`);
            await waitForText('Keep the booking system you already use.');
            await injectCaption('A clean handoff to your booking page', 'FinACourt records the visit, then sends the player straight through.');
            await sleep(4400);
            break;
        case 'owner-promotions':
            await sleep(3100);
            await injectCaption('Promote slow time slots', 'Publish an offer while keeping court availability accurate.', { demo: true });
            await sleep(3900);
            break;
        case 'owner-visibility':
            await sleep(3200);
            await injectCaption('Make it easier for players to find you', 'Review your public page, address, hours and shareable links.', { demo: true });
            await sleep(2800);
            break;
        case 'owner-outro':
            await sleep(5000);
            break;
    }
}

async function writeMarker(name) {
    await fs.mkdir(controlDirectory, { recursive: true });
    await fs.writeFile(`${controlDirectory}/${scene}.${name}`, `${new Date().toISOString()}\n`);
}

async function waitForStart() {
    const path = `${controlDirectory}/${scene}.start`;
    await waitUntil(async () => {
        try {
            await fs.access(path);
            return true;
        } catch {
            return false;
        }
    }, 30000, `${scene} recorder start`);
}

async function saveDebugScreenshot() {
    if (!sessionId) return;

    try {
        const base64 = await command('GET', '/screenshot');
        await fs.writeFile(`${outputDirectory}/debug-${scene}.png`, Buffer.from(base64, 'base64'));
    } catch {
        // Preserve the original automation error when a screenshot also fails.
    }
}

try {
    await createSession();
    await prepare();
    await writeMarker('ready');
    await waitForStart();
    await perform();
    // Keep the fully painted final state alive beyond the retained scene. This
    // prevents X11 window teardown from entering the last encoded frames.
    await sleep(1500);
    await writeMarker('done');
} catch (error) {
    await saveDebugScreenshot();
    await fs.mkdir(controlDirectory, { recursive: true });
    await fs.writeFile(`${controlDirectory}/${scene}.error`, `${error.stack || error}\n`);
    throw error;
} finally {
    if (sessionId) {
        await request('DELETE', `/session/${sessionId}`).catch(() => {});
    }
}
