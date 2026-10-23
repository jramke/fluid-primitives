import { beforeEach, describe, expect, test } from 'vitest';
import {
    ComponentHydrator,
    mount,
    mountAll,
    registerClientPropConverters,
    type Component,
} from '../../Resources/Private/Client/src/lib';
import partNaming from '../Fixtures/part-naming.json';

const field = (id: string, props: object = {}) => ({
    autoMount: true,
    props: { id, ids: {}, ...props },
});

function setGlobals(globals: Partial<Window['FluidPrimitives']> = {}) {
    window.FluidPrimitives = {
        hydrationData: {},
        componentInstances: {},
        ...globals,
    } as Window['FluidPrimitives'];
}

beforeEach(() => {
    document.body.innerHTML = '';
    setGlobals({
        hydrationData: {
            ui: {
                widget: {
                    auto: { autoMount: true, props: { id: 'auto', ids: {} } },
                    manual: { autoMount: false, props: { id: 'manual', ids: {} } },
                },
            },
        },
    });
});

describe('mounting components', () => {
    test('mountAll leaves components rendered with autoMount false to mount, which reaches them by rootId', () => {
        const created: string[] = [];
        const create = ({ props }: { props: { id: string } }) => {
            created.push(props.id);
            return {} as Component<unknown, unknown>;
        };

        mountAll('ui:widget', create);
        expect(created).toEqual(['auto']);

        mount('ui:widget', 'manual', create);
        expect(created).toEqual(['auto', 'manual']);
        expect(Object.keys(window.FluidPrimitives.componentInstances.ui.widget)).toEqual([
            'auto',
            'manual',
        ]);
    });

    test('the callback gets the props run through the registered converters, one that was omitted too', () => {
        registerClientPropConverters('converted', {
            size: (wire: string | undefined) => wire?.toUpperCase() ?? 'MD',
        });
        setGlobals({
            hydrationData: {
                ui: { converted: { a: field('a', { size: 'lg' }), b: field('b') } },
            },
        });
        const sizes: unknown[] = [];
        const create = ({ props }: { props: Record<string, unknown> }) => {
            sizes.push(props.size);
            return {} as Component<unknown, unknown>;
        };

        mountAll('ui:converted', create);
        mount('ui:converted', 'b', create);

        expect(sizes).toEqual(['LG', 'MD', 'MD']);
    });
});

describe('ComponentHydrator', () => {
    test.each(partNaming)(
        'names the $part part of $componentName exactly like the server renders it',
        ({ componentName, clientBaseName, part, value, rootId, attribute, scopeKey }) => {
            const hydrator = new ComponentHydrator(componentName, rootId);

            expect(hydrator.clientComponentName).toBe(clientBaseName);
            expect(hydrator.attr(part)).toBe(attribute);
            expect(hydrator.selector(part)).toBe(`[${attribute}="${rootId}"]`);
            expect(hydrator.scopeKey(part, value)).toBe(scopeKey);
        }
    );

    test('keeps a quote or bracket of a root id inside the attribute selector', () => {
        const rootId = 'a"],[data-dialog-root';
        const own = document.createElement('div');
        own.setAttribute('data-dialog-root', rootId);
        const other = document.createElement('div');
        other.setAttribute('data-dialog-root', 'other');
        document.body.replaceChildren(other, own);

        expect(new ComponentHydrator('dialog', rootId).queryAll('root')).toEqual([own]);
    });

    test('finds the parts of its own root id anywhere in the document, portaled content included', () => {
        document.body.innerHTML = `
            <div data-dialog-root="d1"><button id="inside" data-dialog-close-trigger="d1"></button></div>
            <div id="portal">
                <button id="portaled" data-dialog-close-trigger="d1"></button>
                <button id="other" data-dialog-close-trigger="d2"></button>
            </div>
        `;
        const hydrator = new ComponentHydrator('dialog', 'd1');

        expect(hydrator.queryAll('closeTrigger').map(el => el.id)).toEqual(['inside', 'portaled']);
        expect(hydrator.query('closeTrigger')?.id).toBe('inside');
        expect(hydrator.query('closeTrigger', document.getElementById('portal')!)?.id).toBe(
            'portaled'
        );
        expect(
            hydrator.query('closeTrigger', document.querySelector('[data-dialog-root]')!)?.id
        ).toBe('inside');
    });

    test('stamp marks an element created on the client like ui:ref does, so it is found afterwards', () => {
        const hydrator = new ComponentHydrator('combobox', 'c1');
        const item = document.createElement('li');
        document.body.append(item);

        hydrator.stamp(item, 'item', 'apple');

        expect(item.getAttribute('data-combobox-item')).toBe('c1');
        expect(item.dataset.value).toBe('apple');
        expect(hydrator.queryAll('item')).toEqual([item]);
    });
});

describe('ComponentHydrator nested components of a row', () => {
    // a FieldArray row (own part `item`) holding a Field, the way PHP renders it and records it
    const row = (value: string, fieldId: string) => `
        <div data-field-array-item="fa1" data-value="${value}">
            <div data-field-root="${fieldId}" data-name="people[${value}][name]">
                <label id="field:${fieldId}:label" data-field-label="${fieldId}"></label>
                <input id="field:${fieldId}:control" data-field-control="${fieldId}">
            </div>
            <button data-field-array-remove-trigger="fa1" data-value="${value}"></button>
        </div>
    `;
    const fieldEntry = (id: string, name: string) =>
        field(id, { name, ids: { control: `field:${id}:control` } });
    const attr = (el: Element, selector: string, name: string) =>
        el.querySelector(selector)!.getAttribute(name);

    test('restampValue gives a cloned row its value and its nested Field a fresh id and hydration data', () => {
        setGlobals({
            hydrationData: {
                primitives: { field: { fld: fieldEntry('fld', 'people[][name]') } },
            },
            nestedComponents: {
                'field-array:fa1:item-template': [{ name: 'primitives:field', id: 'fld' }],
            },
        });
        document.body.innerHTML = `<template>${row('', 'fld')}</template>`;
        const clone = document.querySelector('template')!.content.firstElementChild!;
        const hydrator = new ComponentHydrator('fieldArray', 'fa1');

        const names = hydrator.restampValue(clone, '3', hydrator.scopeKey('itemTemplate'));

        expect(names).toEqual(['primitives:field']);
        // the row's own parts take the value, the nested Field is another component's scope
        expect(clone.getAttribute('data-value')).toBe('3');
        expect(attr(clone, '[data-field-array-remove-trigger]', 'data-value')).toBe('3');
        expect(attr(clone, '[data-field-root]', 'data-value')).toBeNull();
        // every reference to the stencil's Field id follows it, ids included
        expect(attr(clone, '[data-field-root]', 'data-field-root')).toBe('fld:3');
        expect(attr(clone, 'label', 'data-field-label')).toBe('fld:3');
        expect(attr(clone, 'input', 'data-field-control')).toBe('fld:3');
        expect(attr(clone, 'input', 'id')).toBe('field:fld:3:control');
        // the clone's hydration data is a copy, the stencil's stays for the next clone
        const fields = window.FluidPrimitives.hydrationData.primitives.field;
        expect(Object.keys(fields)).toEqual(['fld', 'fld:3']);
        expect(fields['fld:3'].props).toMatchObject({
            id: 'fld:3',
            name: 'people[3][name]',
            ids: { control: 'field:fld:3:control' },
        });
        expect(window.FluidPrimitives.nestedComponents).toMatchObject({
            'field-array:fa1:item:3': [{ name: 'primitives:field', id: 'fld:3' }],
        });
    });

    test('restampValue writes a value like $& literally instead of expanding it', () => {
        setGlobals({
            hydrationData: {
                primitives: { field: { fld: fieldEntry('fld', 'people[][name]') } },
            },
            nestedComponents: {
                'field-array:fa1:item-template': [{ name: 'primitives:field', id: 'fld' }],
            },
        });
        document.body.innerHTML = `<template>${row('', 'fld')}</template>`;
        const clone = document.querySelector('template')!.content.firstElementChild!;
        const hydrator = new ComponentHydrator('fieldArray', 'fa1');

        hydrator.restampValue(clone, '$&', hydrator.scopeKey('itemTemplate'));

        expect(attr(clone, 'input', 'id')).toBe('field:fld:$&:control');
        const fields = window.FluidPrimitives.hydrationData.primitives.field;
        expect(Object.keys(fields)).toEqual(['fld', 'fld:$&']);
        expect(fields['fld:$&'].props.name).toBe('people[$&][name]');
    });

    test('renameValue moves a live row to its new value without touching the row of a longer number', () => {
        const hydrator2 = new ComponentHydrator('field', 'fld:2');
        const hydrator20 = new ComponentHydrator('field', 'fld:20');
        setGlobals({
            hydrationData: {
                primitives: {
                    field: {
                        'fld:2': fieldEntry('fld:2', 'people[2][name]'),
                        'fld:20': fieldEntry('fld:20', 'people[20][name]'),
                    },
                },
            },
            componentInstances: {
                primitives: {
                    field: {
                        'fld:2': { hydrator: hydrator2 } as unknown as Component<unknown, unknown>,
                        'fld:20': { hydrator: hydrator20 } as unknown as Component<
                            unknown,
                            unknown
                        >,
                    },
                },
            },
            nestedComponents: {
                'field-array:fa1:item:2': [{ name: 'primitives:field', id: 'fld:2' }],
                'field-array:fa1:item:20': [{ name: 'primitives:field', id: 'fld:20' }],
            },
        });
        document.body.innerHTML = row('2', 'fld:2') + row('20', 'fld:20');
        const [rowTwo, rowTwenty] = Array.from(
            document.querySelectorAll('[data-field-array-item]')
        );
        const hydrator = new ComponentHydrator('fieldArray', 'fa1');

        hydrator.renameValue(rowTwo, '1');

        expect(rowTwo.getAttribute('data-value')).toBe('1');
        expect(attr(rowTwo, '[data-field-array-remove-trigger]', 'data-value')).toBe('1');
        expect(attr(rowTwo, '[data-field-root]', 'data-field-root')).toBe('fld:1');
        expect(attr(rowTwo, 'input', 'id')).toBe('field:fld:1:control');
        // `fld:2` is the start of `fld:20`: only a whole id segment counts as a reference
        expect(attr(rowTwenty, '[data-field-root]', 'data-field-root')).toBe('fld:20');
        expect(attr(rowTwenty, 'input', 'id')).toBe('field:fld:20:control');

        // the live instance and its data follow the id, so later lookups still find them
        const instances = window.FluidPrimitives.componentInstances.primitives.field;
        expect(Object.keys(instances)).toEqual(['fld:20', 'fld:1']);
        expect(hydrator2.rootId).toBe('fld:1');
        expect(hydrator20.rootId).toBe('fld:20');
        expect(Object.keys(window.FluidPrimitives.hydrationData.primitives.field)).toEqual([
            'fld:20',
            'fld:1',
        ]);
        expect(window.FluidPrimitives.hydrationData.primitives.field['fld:1'].props).toMatchObject({
            id: 'fld:1',
            ids: { control: 'field:fld:1:control' },
        });
        expect(Object.keys(window.FluidPrimitives.nestedComponents!)).toEqual([
            'field-array:fa1:item:20',
            'field-array:fa1:item:1',
        ]);
    });
});
