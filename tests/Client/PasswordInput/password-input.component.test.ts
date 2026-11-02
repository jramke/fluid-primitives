import { afterEach, expect, test } from 'vitest';
import { applyClientPropConverters } from '../../../Resources/Private/Client';
import { PasswordInput } from '../../../Resources/Private/Primitives/PasswordInput/PasswordInput';

let passwordInput: PasswordInput | undefined;

afterEach(() => {
    passwordInput?.destroy();
    passwordInput = undefined;
    document.body.innerHTML = '';
});

// What `mountAll` does before it calls the entry file: the wire props go through the converters
function mountPasswordInput(translations: Record<string, unknown> = {}) {
    document.body.innerHTML = `
        <div data-password-input-root="p1">
            <input type="password" data-password-input-input="p1" />
            <button type="button" data-password-input-visibility-trigger="p1">
                <span data-password-input-indicator="p1" data-value="hidden">show</span>
                <span data-password-input-indicator="p1" data-value="visible" hidden>hide</span>
            </button>
        </div>`;
    const inputEl = document.querySelector('input')!;
    // typed or autofilled before the client hydrated
    inputEl.value = 'hunter2';

    passwordInput = new PasswordInput(
        applyClientPropConverters('passwordInput', {
            id: 'p1',
            translations,
        }) as unknown as ConstructorParameters<typeof PasswordInput>[0]
    );
    passwordInput.init();

    return {
        inputEl,
        triggerEl: document.querySelector('button')!,
        indicatorEls: [...document.querySelectorAll<HTMLElement>('[data-value]')],
    };
}

test.each([
    [
        'the labels of both states',
        { visibilityTriggerLabelHidden: 'Show it', visibilityTriggerLabelVisible: 'Hide it' },
    ],
    [
        'a visibilityTrigger function',
        { visibilityTrigger: (visible: boolean) => (visible ? 'Hide it' : 'Show it') },
    ],
])('labels the visibility trigger per state from %s', async (_case, translations) => {
    const { triggerEl } = mountPasswordInput(translations);
    expect(triggerEl.getAttribute('aria-label')).toBe('Show it');

    passwordInput!.api.setVisible(true);
    await new Promise(resolve => setTimeout(resolve, 0));

    expect(triggerEl.getAttribute('aria-label')).toBe('Hide it');
});

test('shows the password and swaps the indicators, keeping what was typed before hydration', async () => {
    const { inputEl, indicatorEls } = mountPasswordInput();
    expect(inputEl.value).toBe('hunter2');

    passwordInput!.api.toggleVisible();
    await new Promise(resolve => setTimeout(resolve, 0));

    expect(inputEl.type).toBe('text');
    expect(inputEl.value).toBe('hunter2');
    expect(indicatorEls.map(el => el.hidden)).toEqual([true, false]);
});
