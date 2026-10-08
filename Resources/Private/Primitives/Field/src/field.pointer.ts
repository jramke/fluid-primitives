/**
 * Whether a pointer button is held down, and a way to wait for its release. A field blurs on the
 * mousedown that moves the focus away, and showing its error text then shifts the layout: by the
 * mouseup the button the user pressed has moved, and the click is lost.
 */

// a release outside the window may never arrive
const MAX_PRESS_MS = 1500;

const waiting = new Set<() => void>();
let pressed = false;
let installed = false;
let giveUp: ReturnType<typeof setTimeout> | undefined;

function release() {
    pressed = false;
    clearTimeout(giveUp);

    const callbacks = Array.from(waiting);
    waiting.clear();
    callbacks.forEach(callback => callback());
}

function install() {
    if (installed) return;
    installed = true;

    document.addEventListener(
        'pointerdown',
        () => {
            pressed = true;
            clearTimeout(giveUp);
            giveUp = setTimeout(release, MAX_PRESS_MS);
        },
        true
    );

    // the click is dispatched in the task of the pointerup, so the layout may only move after it
    for (const type of ['pointerup', 'pointercancel']) {
        document.addEventListener(type, () => setTimeout(release, 0), true);
    }
}

export function isPointerPressed() {
    install();
    return pressed;
}

/** Runs `callback` once no pointer button is held down, right away if none is. Returns a cancel function. */
export function whenPointerReleased(callback: () => void) {
    install();
    if (!pressed) {
        callback();
        return () => {};
    }

    waiting.add(callback);
    return () => waiting.delete(callback);
}
