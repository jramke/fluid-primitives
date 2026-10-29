import { afterEach, expect, test } from 'vitest';
import { applyClientPropConverters } from '../../../Resources/Private/Client';
import { Clipboard } from '../../../Resources/Private/Primitives/Clipboard/Clipboard';

let clipboard: Clipboard | undefined;

afterEach(() => {
    clipboard?.destroy();
    clipboard = undefined;
    document.body.innerHTML = '';
});

// jsdom has no clipboard: Zag falls back to `execCommand('copy')`
document.execCommand = () => true;

// What `mountAll` does before it calls the entry file: the wire props go through the converters
function mountClipboard(translations: Record<string, unknown>) {
    document.body.innerHTML = `
        <div data-clipboard-root="c1">
            <button data-clipboard-trigger="c1">Copy</button>
        </div>`;
    clipboard = new Clipboard(
        applyClientPropConverters('clipboard', {
            id: 'c1',
            value: 'x',
            translations,
        }) as unknown as ConstructorParameters<typeof Clipboard>[0]
    );
    clipboard.init();
    return document.querySelector('button')!;
}

test.each([
    ['the texts of both states', { triggerLabelIdle: 'Copy it', triggerLabelCopied: 'Copied it' }],
    [
        'a triggerLabel function',
        { triggerLabel: (copied: boolean) => (copied ? 'Copied it' : 'Copy it') },
    ],
])('labels the trigger per state from %s', async (_case, translations) => {
    const triggerEl = mountClipboard(translations);
    expect(triggerEl.getAttribute('aria-label')).toBe('Copy it');

    clipboard!.machine.send({ type: 'COPY' });
    await new Promise(resolve => setTimeout(resolve, 0));

    expect(triggerEl.getAttribute('aria-label')).toBe('Copied it');
});
