import { afterEach, describe, expect, test, vi } from 'vitest';
import { Field } from '../../Resources/Private/Primitives/Field/Field';
import { Switch } from '../../Resources/Private/Primitives/Switch/Switch';

const instances: Array<{ destroy(): void }> = [];

/** A Switch inside a Field, as PHP renders them, with the two ways to hydrate them. */
function render() {
    document.body.innerHTML = `
        <div data-field-root="f1" data-name="terms">
            <label data-switch-root="s1">
                <input type="checkbox" id="field:f1:control" data-switch-hidden-input="s1" name="terms">
                <span data-switch-control="s1"></span>
            </label>
        </div>`;

    return {
        field(props: { disabled?: boolean } = {}) {
            const field = new Field({ id: 'f1', name: 'terms', required: true, ...props });
            field.init();
            instances.push(field);
            return field;
        },
        toggle() {
            const toggle = new Switch({ id: 's1' });
            toggle.init();
            instances.push(toggle);
            return toggle;
        },
    };
}

afterEach(() => {
    instances.splice(0).forEach(instance => instance.destroy());
    document.body.innerHTML = '';
});

describe('a primitive inside a Field', () => {
    test('init() still returns the instance when the primitive does more after it', () => {
        render();
        const field = new Field({ id: 'f1', name: 'terms' });
        const toggle = new Switch({ id: 's1' });
        instances.push(field, toggle);

        expect(field.init()).toBe(field);
        expect(toggle.init()).toBe(toggle);
    });

    test.each(['the field', 'the switch'])(
        'takes its name and state from the field when %s hydrates first',
        first => {
            const { field, toggle } = render();

            const toggleFirst = first === 'the switch';
            const switchInstance = toggleFirst ? toggle() : undefined;
            field({ disabled: true });
            const hydrated = switchInstance ?? toggle();

            expect(hydrated.machine.prop('name')).toBe('terms');
            expect(hydrated.machine.prop('required')).toBe(true);
            expect(hydrated.api.disabled).toBe(true);
        }
    );

    test('follows the state the field changes to later', async () => {
        const { field, toggle } = render();
        const fieldInstance = field();
        const hydrated = toggle();
        expect(hydrated.api.disabled).toBeFalsy();

        fieldInstance.api.setDisabled(true);

        await vi.waitFor(() => expect(hydrated.api.disabled).toBe(true));
    });
});
