import { afterEach, describe, expect, test, vi } from 'vitest';
import { Machine } from '../../../Resources/Private/Client/src/lib/machine';
import { normalizeProps } from '../../../Resources/Private/Client/src/lib/normalize-props';
import { connect } from '../../../Resources/Private/Primitives/CheckboxGroup/src/checkbox-group.connect';
import { machine } from '../../../Resources/Private/Primitives/CheckboxGroup/src/checkbox-group.machine';
import type { CheckboxGroupProps } from '../../../Resources/Private/Primitives/CheckboxGroup/src/checkbox-group.types';

let running: Machine<any>[] = [];

function start(props: Partial<CheckboxGroupProps> = {}) {
    const group = new Machine(machine, { id: 'g1', ...props });
    group.start();
    running.push(group);
    // `connect` reads disabled, readOnly and invalid once, so a fresh api is needed per read
    return () => connect(group.service, normalizeProps);
}

afterEach(() => {
    running.forEach(group => group.stop());
    running = [];
});

describe('checkbox group selection', () => {
    test('add and toggle stop at maxSelectedValues, ignore duplicates, and report each real change', async () => {
        const onValueChange = vi.fn();
        const api = start({ defaultValue: ['a'], maxSelectedValues: 2, onValueChange });

        api().addValue('b');
        api().addValue('b');
        api().addValue('c');
        api().toggleValue('c');
        await vi.waitFor(() => expect(api().value).toEqual(['a', 'b']));

        // unchecking frees a place, removing works while the group is full
        api().toggleValue('a');
        api().toggleValue('c');
        api().removeValue('b');
        await vi.waitFor(() => expect(api().value).toEqual(['c']));

        expect(onValueChange.mock.calls.map(([details]) => details.value)).toEqual([
            ['a', 'b'],
            ['b'],
            ['b', 'c'],
            ['c'],
        ]);
    });

    test.each([{ disabled: true }, { readOnly: true }])(
        'user changes are ignored while the group is %o',
        async state => {
            const api = start({ defaultValue: ['a'], ...state });

            api().addValue('b');
            api().removeValue('a');
            api().toggleValue('a');
            api().toggleValue('b');
            await Promise.resolve();

            expect(api().value).toEqual(['a']);
        }
    );
});

describe('checkbox group item props', () => {
    test('a full group disables only the unchecked items, and an item toggles its own value', async () => {
        const api = start({
            defaultValue: ['a', 'b'],
            maxSelectedValues: 2,
            name: 'tags',
            invalid: true,
        });

        expect(api().getItemProps({ value: 'a' })).toMatchObject({
            checked: true,
            disabled: false,
            name: 'tags',
            invalid: true,
        });
        expect(api().getItemProps({ value: 'c' })).toMatchObject({
            checked: false,
            disabled: true,
        });

        api().getItemProps({ value: 'a' }).onCheckedChange();
        await vi.waitFor(() => expect(api().isChecked('a')).toBe(false));

        expect(api().getItemProps({ value: 'c' }).disabled).toBe(false);
    });
});
