import assert from 'node:assert/strict';
import test from 'node:test';
import { initLoyaltyCarousels } from '../../resources/js/loyalty-carousel.js';

test('loyalty carousel controls scroll by one card and disable at the edges', () => {
    const buttons = ['previous', 'next'].map((direction) => ({
        dataset: { loyaltyDirection: direction },
        disabled: false,
        addEventListener(_event, callback) { this.click = callback; },
    }));
    const [previous, next] = buttons;
    const moves = [];
    let onScroll;
    const scroller = {
        scrollWidth: 1300,
        clientWidth: 600,
        scrollLeft: 0,
        querySelector: () => ({ getBoundingClientRect: () => ({ width: 400 }) }),
        addEventListener(_event, callback) { onScroll = callback; },
        scrollBy(move) {
            moves.push(move);
            this.scrollLeft = Math.min(700, Math.max(0, this.scrollLeft + move.left));
            onScroll();
        },
    };
    const controls = {
        querySelector: (selector) => selector.includes('previous') ? previous : next,
    };
    const documentRef = {
        querySelectorAll: () => [controls],
        getElementById: () => scroller,
    };
    const windowRef = {
        getComputedStyle: () => ({ columnGap: '20px' }),
        addEventListener() {},
        requestAnimationFrame(callback) { callback(); },
    };

    initLoyaltyCarousels(documentRef, windowRef);
    assert.equal(previous.disabled, true);
    assert.equal(next.disabled, false);

    next.click();
    assert.deepEqual(moves[0], { left: 420, behavior: 'smooth' });
    assert.equal(previous.disabled, false);

    next.click();
    assert.equal(next.disabled, true);

    previous.click();
    assert.deepEqual(moves[2], { left: -420, behavior: 'smooth' });
    assert.equal(next.disabled, false);
});
