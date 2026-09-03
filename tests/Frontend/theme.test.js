import assert from 'node:assert/strict';
import test from 'node:test';
import {
    applyTheme,
    DARK_THEME,
    LIGHT_THEME,
    preferredTheme,
    savedTheme,
    setTheme,
    THEME_STORAGE_KEY,
    toggleTheme,
} from '../../resources/js/theme.js';

function storageWith(value = null) {
    const values = new Map(value === null ? [] : [[THEME_STORAGE_KEY, value]]);

    return {
        getItem(key) {
            return values.get(key) ?? null;
        },
        setItem(key, nextValue) {
            values.set(key, nextValue);
        },
    };
}

function themeDocument() {
    const attributes = new Map();
    const label = { textContent: '' };
    const control = {
        setAttribute(name, value) {
            attributes.set(name, value);
        },
        querySelector(selector) {
            return selector === '[data-theme-label]' ? label : null;
        },
    };
    const themeColor = {
        value: '#146d4a',
        setAttribute(_name, value) {
            this.value = value;
        },
    };
    const documentRef = {
        documentElement: { dataset: {}, style: {} },
        querySelector(selector) {
            return selector === 'meta[name="theme-color"]' ? themeColor : null;
        },
        querySelectorAll(selector) {
            return selector === '[data-theme-toggle]' ? [control] : [];
        },
    };

    return { attributes, control, documentRef, label, themeColor };
}

test('saved theme wins over the device preference and invalid values are ignored', () => {
    assert.equal(savedTheme(storageWith(DARK_THEME)), DARK_THEME);
    assert.equal(savedTheme(storageWith('sepia')), null);
    assert.equal(preferredTheme(storageWith(LIGHT_THEME), { matches: true }), LIGHT_THEME);
    assert.equal(preferredTheme(storageWith(), { matches: true }), DARK_THEME);
});

test('applying dark mode updates the page, browser color, and accessible control label', () => {
    const { attributes, documentRef, label, themeColor } = themeDocument();

    assert.equal(applyTheme(DARK_THEME, documentRef), DARK_THEME);
    assert.equal(documentRef.documentElement.dataset.theme, DARK_THEME);
    assert.equal(documentRef.documentElement.style.colorScheme, DARK_THEME);
    assert.equal(themeColor.value, '#071f17');
    assert.equal(attributes.get('aria-pressed'), 'true');
    assert.equal(attributes.get('aria-label'), 'Switch to light mode');
    assert.equal(label.textContent, 'Switch to light mode');
});

test('theme changes persist and can be toggled back to light mode', () => {
    const storage = storageWith();
    const { documentRef } = themeDocument();

    assert.equal(setTheme(DARK_THEME, { documentRef, storage }), DARK_THEME);
    assert.equal(storage.getItem(THEME_STORAGE_KEY), DARK_THEME);
    assert.equal(toggleTheme({ documentRef, storage }), LIGHT_THEME);
    assert.equal(storage.getItem(THEME_STORAGE_KEY), LIGHT_THEME);
});
