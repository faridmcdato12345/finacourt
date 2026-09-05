export const THEME_STORAGE_KEY = 'finacourt-theme';
export const LIGHT_THEME = 'light';
export const DARK_THEME = 'dark';

function isTheme(value) {
    return value === LIGHT_THEME || value === DARK_THEME;
}

function availableStorage(windowRef = globalThis.window) {
    try {
        return windowRef?.localStorage;
    } catch {
        return null;
    }
}

export function systemTheme(mediaQuery = globalThis.window?.matchMedia?.('(prefers-color-scheme: dark)')) {
    return mediaQuery?.matches ? DARK_THEME : LIGHT_THEME;
}

export function savedTheme(storage = availableStorage()) {
    try {
        const value = storage?.getItem(THEME_STORAGE_KEY);

        return isTheme(value) ? value : null;
    } catch {
        return null;
    }
}

export function preferredTheme(storage = availableStorage()) {
    return savedTheme(storage) || LIGHT_THEME;
}

function updateThemeControls(theme, documentRef) {
    const dark = theme === DARK_THEME;

    documentRef.querySelectorAll('[data-theme-toggle]').forEach((control) => {
        const label = dark ? 'Switch to light mode' : 'Switch to dark mode';
        control.setAttribute('aria-label', label);
        control.setAttribute('aria-pressed', String(dark));
        control.setAttribute('title', label);

        const accessibleLabel = control.querySelector('[data-theme-label]');
        if (accessibleLabel) accessibleLabel.textContent = label;
    });
}

export function applyTheme(theme, documentRef = globalThis.document) {
    if (!documentRef?.documentElement) return isTheme(theme) ? theme : LIGHT_THEME;

    const nextTheme = isTheme(theme) ? theme : LIGHT_THEME;
    const root = documentRef.documentElement;
    root.dataset.theme = nextTheme;
    root.style.colorScheme = nextTheme;

    const themeColor = documentRef.querySelector('meta[name="theme-color"]');
    themeColor?.setAttribute('content', nextTheme === DARK_THEME ? '#071f17' : '#146d4a');
    updateThemeControls(nextTheme, documentRef);

    return nextTheme;
}

export function setTheme(theme, {
    documentRef = globalThis.document,
    storage = availableStorage(),
} = {}) {
    const nextTheme = applyTheme(theme, documentRef);

    try {
        storage?.setItem(THEME_STORAGE_KEY, nextTheme);
    } catch {
        // Browsers can disable storage. The current page can still use the selected theme.
    }

    return nextTheme;
}

export function toggleTheme(options = {}) {
    const documentRef = options.documentRef || globalThis.document;
    const currentTheme = documentRef?.documentElement?.dataset?.theme;

    return setTheme(currentTheme === DARK_THEME ? LIGHT_THEME : DARK_THEME, options);
}

export function bindThemeControls({
    documentRef = globalThis.document,
    windowRef = globalThis.window,
} = {}) {
    if (!documentRef?.documentElement || !windowRef) return;

    const storage = availableStorage(windowRef);
    applyTheme(preferredTheme(storage), documentRef);

    documentRef.addEventListener('click', (event) => {
        const control = event.target?.closest?.('[data-theme-toggle]');
        if (!control) return;

        toggleTheme({ documentRef, storage });
    });

    windowRef.addEventListener('storage', (event) => {
        if (event.key === THEME_STORAGE_KEY && isTheme(event.newValue)) {
            applyTheme(event.newValue, documentRef);
        }
    });

}

if (globalThis.document && globalThis.window) {
    if (globalThis.document.readyState === 'loading') {
        globalThis.document.addEventListener('DOMContentLoaded', () => bindThemeControls(), { once: true });
    } else {
        bindThemeControls();
    }
}
