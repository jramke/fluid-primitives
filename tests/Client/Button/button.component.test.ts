import { afterEach, expect, test, vi } from 'vitest';
import { Button } from '../../../Resources/Private/Primitives/Button/Button';

let button: Button | undefined;

afterEach(() => {
    button?.destroy();
    button = undefined;
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

function mountButton(
    attributes = '',
    props: Partial<ConstructorParameters<typeof Button>[0]> = {}
) {
    document.body.innerHTML = `
        <form>
            <button type="submit" data-button-root="b1" ${attributes}>
                <span data-button-indicator="b1" data-value="idle">Save</span>
                <span data-button-indicator="b1" data-value="loading" hidden>Saving</span>
            </button>
        </form>`;
    button = new Button({ id: 'b1', ...props });
    button.init();
    return {
        buttonEl: document.querySelector<HTMLButtonElement>('button')!,
        formEl: document.querySelector('form')!,
        visibleIndicator: () =>
            [...document.querySelectorAll<HTMLElement>('[data-button-indicator]')]
                .filter(el => !el.hidden)
                .map(el => el.dataset.value),
    };
}

test('turns loading on and off with the attributes and the indicator of each state', () => {
    const { buttonEl, visibleIndicator } = mountButton();
    expect(visibleIndicator()).toEqual(['idle']);

    button!.api.setLoading(true);
    expect(buttonEl.disabled).toBe(true);
    expect(buttonEl.hasAttribute('data-disabled')).toBe(true);
    expect(buttonEl.hasAttribute('data-loading')).toBe(true);
    expect(visibleIndicator()).toEqual(['loading']);

    button!.api.setLoading(false);
    expect(buttonEl.disabled).toBe(false);
    expect(buttonEl.hasAttribute('data-disabled')).toBe(false);
    expect(buttonEl.hasAttribute('data-loading')).toBe(false);
    expect(visibleIndicator()).toEqual(['idle']);
});

test('follows a change of its props like the setters, and keeps disabled apart from loading', () => {
    const { buttonEl, visibleIndicator } = mountButton();

    button!.updateProps({ disabled: true });
    expect(buttonEl.disabled).toBe(true);
    expect(visibleIndicator()).toEqual([]);

    button!.api.setLoading(true);
    expect(visibleIndicator()).toEqual(['loading']);
    button!.api.setLoading(false);
    expect(buttonEl.disabled).toBe(true);

    button!.api.setDisabled(false);
    expect(buttonEl.disabled).toBe(false);

    button!.updateProps({ loading: true });
    expect(visibleIndicator()).toEqual(['loading']);
    button!.updateProps({ loading: false });
    expect(buttonEl.disabled).toBe(false);
});

test('stays focusable with aria-disabled instead of the disabled attribute when asked to', () => {
    const { buttonEl } = mountButton('', { focusableWhenDisabled: true });

    button!.api.setLoading(true);
    expect(buttonEl.disabled).toBe(false);
    expect(buttonEl.getAttribute('aria-disabled')).toBe('true');

    button!.api.setLoading(false);
    expect(buttonEl.hasAttribute('aria-disabled')).toBe(false);
});

test('ignores a click and the activation keys while disabled, before any other listener sees them', () => {
    const { buttonEl, formEl } = mountButton('', { focusableWhenDisabled: true });
    const onClick = vi.fn();
    const onSubmit = vi.fn((event: Event) => event.preventDefault());
    button!.api.addEventListener('click', onClick);
    formEl.addEventListener('submit', onSubmit);
    const press = (type: string, key: string) => {
        const event = new KeyboardEvent(type, { key, bubbles: true, cancelable: true });
        buttonEl.dispatchEvent(event);
        return event.defaultPrevented;
    };

    // positive control: an idle button lets everything through
    buttonEl.click();
    expect(onClick).toHaveBeenCalledTimes(1);
    expect(onSubmit).toHaveBeenCalledTimes(1);
    expect(press('keydown', 'Enter')).toBe(false);

    button!.api.setLoading(true);
    buttonEl.click();
    expect(onClick).toHaveBeenCalledTimes(1);
    expect(onSubmit).toHaveBeenCalledTimes(1);
    expect(press('keydown', 'Enter')).toBe(true);
    expect(press('keyup', ' ')).toBe(true);
    expect(press('keydown', 'Tab')).toBe(false);

    button!.api.setLoading(false);
    buttonEl.click();
    expect(onClick).toHaveBeenCalledTimes(2);
});

test('leaves the attributes of a trigger it is merged into alone', () => {
    const { buttonEl } = mountButton('data-state="open" aria-expanded="true"');

    button!.api.setLoading(true);
    button!.api.setLoading(false);

    expect(buttonEl.getAttribute('data-state')).toBe('open');
    expect(buttonEl.getAttribute('aria-expanded')).toBe('true');
});

test('warns about a focused button that is disabled without focusableWhenDisabled', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const { buttonEl } = mountButton();
    buttonEl.focus();

    button!.api.setDisabled(true);
    expect(warn).toHaveBeenCalledTimes(1);

    button!.destroy();
    warn.mockClear();
    const focusable = mountButton('', { focusableWhenDisabled: true });
    focusable.buttonEl.focus();

    button!.api.setDisabled(true);
    expect(warn).not.toHaveBeenCalled();
});
