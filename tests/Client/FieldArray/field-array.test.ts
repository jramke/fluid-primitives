import { afterEach, describe, expect, test, vi } from 'vitest';
import { mountAll } from '../../../Resources/Private/Client';
import { Collapsible } from '../../../Resources/Private/Primitives/Collapsible/Collapsible';
import { Field } from '../../../Resources/Private/Primitives/Field/Field';
import {
    FieldArray,
    type FieldArrayProps,
} from '../../../Resources/Private/Primitives/FieldArray/FieldArray';
import { Form } from '../../../Resources/Private/Primitives/Form/Form';

const people = ['Ada', 'Alan', 'Grace'];

/**
 * What PHP renders for one row of `people`: a Field with its input, a Collapsible (a Zag machine
 * that finds its parts by the ids derived from its own id) and the remove trigger.
 */
const row = (index: number | '', fieldId: string, name: string, value: string) => `
    <div data-field-array-item="fa1" data-value="${index}">
        <div data-field-root="${fieldId}" data-name="${name}">
            <input id="field:${fieldId}:control" data-field-control="${fieldId}" name="${name}" value="${value}">
        </div>
        <div data-collapsible-root="c${fieldId}">
            <button type="button" data-collapsible-trigger="c${fieldId}">Details</button>
            <div data-collapsible-content="c${fieldId}">${value}</div>
        </div>
        <button type="button" data-field-array-remove-trigger="fa1" data-value="${index}"
            >Remove</button>
    </div>
`;

const fieldEntry = (id: string, name: string) => ({
    autoMount: true,
    props: { id, ids: {}, name },
});

const instances: Array<{ destroy(): void }> = [];

/** Hydrates in the order of a page: fields, then the form, then the field array. */
function mountPeople(options: Partial<FieldArrayProps> = {}, { inForm = true } = {}) {
    document.body.innerHTML = `
        <${inForm ? 'form data-form-root="form1" novalidate' : 'div'}>
            <div data-field-array-root="fa1" data-name="people">
                <p data-field-array-empty-state="fa1" hidden>Nobody yet</p>
                <div data-field-array-item-group="fa1">
                    ${people.map((name, index) => row(index, `f${index}`, `people[${index}][name]`, name)).join('')}
                </div>
                <template data-field-array-item-template="fa1">
                    ${row('', 'fs', 'people[][name]', '')}
                </template>
                <button type="button" data-field-array-add-trigger="fa1">Add</button>
            </div>
        </${inForm ? 'form' : 'div'}>
    `;
    window.FluidPrimitives = {
        hydrationData: {
            primitives: {
                field: {
                    ...Object.fromEntries(
                        people.map((_, index) => [
                            `f${index}`,
                            fieldEntry(`f${index}`, `people[${index}][name]`),
                        ])
                    ),
                    fs: fieldEntry('fs', 'people[][name]'),
                },
                collapsible: Object.fromEntries(
                    [...people.map((_, index) => `f${index}`), 'fs'].map(id => [
                        `c${id}`,
                        { autoMount: true, props: { id: `c${id}`, ids: {} } },
                    ])
                ),
            },
        },
        componentInstances: {},
        nestedComponents: {
            'field-array:fa1:item-template': [
                { name: 'primitives:field', id: 'fs' },
                { name: 'primitives:collapsible', id: 'cfs' },
            ],
        },
    } as unknown as Window['FluidPrimitives'];

    const mountFields = () => {
        mountAll('primitives:field', ({ props }) => {
            const field = new Field(props as never);
            field.init();
            instances.push(field);
            return field;
        });
        mountAll('primitives:collapsible', ({ props }) => {
            const collapsible = new Collapsible(props as never);
            collapsible.init();
            instances.push(collapsible);
            return collapsible;
        });
    };
    mountFields();

    const form = inForm ? new Form({ id: 'form1' }) : undefined;
    if (form) {
        form.init();
        instances.push(form);
    }

    const onItemAdded = vi.fn(() => mountFields());
    const onItemRemoved = vi.fn();
    const fieldArray = new FieldArray({
        id: 'fa1',
        name: 'people',
        onItemAdded,
        onItemRemoved,
        ...options,
    });
    fieldArray.init();
    instances.push(fieldArray);

    return { form, fieldArray, onItemAdded, onItemRemoved };
}

const rowIndexes = () =>
    Array.from(
        document.querySelectorAll('[data-field-array-item-group] > [data-field-array-item]')
    ).map(el => el.getAttribute('data-value'));
const inputs = () =>
    Array.from(document.querySelectorAll<HTMLInputElement>('[data-field-array-item-group] input'));

afterEach(() => {
    instances.splice(0).forEach(instance => instance.destroy());
    document.body.innerHTML = '';
});

describe('FieldArray rows', () => {
    test('removing a middle row keeps the names of the later rows contiguous and what the form submits right', () => {
        const { form, onItemRemoved } = mountPeople();

        document.querySelectorAll<HTMLElement>('[data-field-array-remove-trigger]')[1].click();

        expect(rowIndexes()).toEqual(['0', '1']);
        expect(inputs().map(input => [input.name, input.value])).toEqual([
            ['people[0][name]', 'Ada'],
            ['people[1][name]', 'Grace'],
        ]);
        expect(form!.api.getValues().toObject()).toEqual({
            people: [{ name: 'Ada' }, { name: 'Grace' }],
        });
        expect(onItemRemoved).toHaveBeenCalledWith({ index: 1 });
        // the row that moved up keeps the id it was mounted with, the removed one is gone
        const fieldInstances = window.FluidPrimitives.componentInstances.primitives.field;
        expect(Object.keys(fieldInstances).filter(id => id !== 'fs')).toEqual(['f0', 'f2']);
    });

    test('a row added after a removal continues the numbering and cannot collide with an existing field', () => {
        const { form, fieldArray, onItemAdded } = mountPeople();
        document.querySelectorAll<HTMLElement>('[data-field-array-remove-trigger]')[0].click();

        fieldArray.api.append();

        expect(onItemAdded).toHaveBeenCalledWith({
            index: 2,
            componentNames: ['primitives:field', 'primitives:collapsible'],
        });
        expect(rowIndexes()).toEqual(['0', '1', '2']);
        const added = inputs()[2];
        expect(added.name).toBe('people[2][name]');

        const rootIds = Array.from(
            document.querySelectorAll('[data-field-array-item-group] [data-field-root]')
        ).map(el => el.getAttribute('data-field-root'));
        expect(new Set(rootIds).size).toBe(3);
        expect(new Set(inputs().map(input => input.id)).size).toBe(3);

        added.value = 'Linus';
        expect(form!.api.getValues().toObject()).toEqual({
            people: [{ name: 'Alan' }, { name: 'Grace' }, { name: 'Linus' }],
        });
    });

    test('a row that moved up keeps its names and its machines apart from a row added later, also without a form', async () => {
        const { fieldArray } = mountPeople({}, { inForm: false });
        const remove = (index: number) =>
            document
                .querySelectorAll<HTMLElement>('[data-field-array-remove-trigger]')
                [index].click();

        fieldArray.api.append();
        remove(0);
        fieldArray.api.append();

        expect(rowIndexes()).toEqual(['0', '1', '2', '3']);
        expect(inputs().map(input => input.name)).toEqual([
            'people[0][name]',
            'people[1][name]',
            'people[2][name]',
            'people[3][name]',
        ]);

        // every machine runs under the id its parts carry, and no part id is shared by two rows
        const collapsibles = Object.entries(
            window.FluidPrimitives.componentInstances.primitives.collapsible
        ).filter(([id]) => id !== 'cfs') as unknown as Array<[string, Collapsible]>;
        expect(collapsibles).toHaveLength(4);
        for (const [id, collapsible] of collapsibles) {
            expect(collapsible.machine.scope.id).toBe(id);
            expect(collapsible.hydrator.rootId).toBe(id);
        }
        const ids = Array.from(document.querySelectorAll('[id]')).map(el => el.id);
        expect(new Set(ids).size).toBe(ids.length);

        // the clone that moved up opens by itself, the row added after it stays closed
        const states = () =>
            Array.from(document.querySelectorAll('[data-collapsible-root]')).map(el =>
                el.getAttribute('data-state')
            );
        document.querySelectorAll<HTMLElement>('[data-collapsible-trigger]')[2].click();
        await vi.waitFor(() => expect(states()).toEqual(['closed', 'closed', 'open', 'closed']));
    });

    test('a removed row does not come back as a component when the next row is mounted', () => {
        const { fieldArray } = mountPeople();
        const collapsibles = () => window.FluidPrimitives.hydrationData.primitives.collapsible;

        fieldArray.api.append();
        expect(Object.keys(collapsibles())).toHaveLength(5);

        fieldArray.api.remove(3);

        expect(Object.keys(collapsibles())).toHaveLength(4);
        expect(
            Object.keys(window.FluidPrimitives.componentInstances.primitives.collapsible)
        ).toHaveLength(4);
    });

    test('minItems and maxItems stop the add and the remove, and the empty state follows the rows', () => {
        const { fieldArray, onItemAdded, onItemRemoved } = mountPeople({
            minItems: 1,
            maxItems: 3,
        });
        const addTrigger = document.querySelector<HTMLElement>('[data-field-array-add-trigger]')!;
        const emptyState = document.querySelector<HTMLElement>('[data-field-array-empty-state]')!;

        expect(addTrigger.getAttribute('aria-disabled')).toBe('true');
        fieldArray.api.append();
        expect(onItemAdded).not.toHaveBeenCalled();

        fieldArray.api.remove(0);
        fieldArray.api.remove(0);
        expect(rowIndexes()).toEqual(['0']);
        expect(
            document
                .querySelector('[data-field-array-remove-trigger]')!
                .getAttribute('aria-disabled')
        ).toBe('true');
        fieldArray.api.remove(0);
        expect(onItemRemoved).toHaveBeenCalledTimes(2);
        expect(addTrigger.hasAttribute('aria-disabled')).toBe(false);
        expect(emptyState.hidden).toBe(true);
    });

    test('focus moves to the next remove trigger, or the add trigger once no row is left', async () => {
        const { fieldArray } = mountPeople();
        const removeTriggers = () =>
            Array.from(document.querySelectorAll<HTMLElement>('[data-field-array-remove-trigger]'));

        fieldArray.api.remove(0);
        await vi.waitFor(() => expect(document.activeElement).toBe(removeTriggers()[0]));

        fieldArray.api.remove(0);
        fieldArray.api.remove(0);
        await vi.waitFor(() =>
            expect(document.activeElement).toBe(
                document.querySelector('[data-field-array-add-trigger]')
            )
        );
        expect(document.querySelector<HTMLElement>('[data-field-array-empty-state]')!.hidden).toBe(
            false
        );
    });

    test('a removed row is announced by its position, in the polite live region', async () => {
        const { fieldArray } = mountPeople({
            translations: { rowRemoved: 'Row %number% removed.', rowAdded: false },
        });

        fieldArray.api.remove(1);

        await vi.waitFor(() =>
            expect(document.getElementById('__live-region-polite__')?.textContent).toBe(
                'Row 2 removed.'
            )
        );
    });
});
