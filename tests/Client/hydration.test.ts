import { beforeEach, describe, expect, test, vi } from 'vitest';
import {
    ComponentHydrator,
    destroyComponentsWithin,
    getComponentInstance,
    getGlobals,
    getHydrationData,
    mount,
    mountAll,
    mountWithin,
    registerClientPropConverters,
    type Component,
} from '../../Resources/Private/Client/src/lib';
import { Collapsible } from '../../Resources/Private/Primitives/Collapsible/Collapsible';
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
                ui: {
                    converted: {
                        a: field('a', { size: 'lg' }),
                        b: field('b'),
                        c: { ...field('c'), autoMount: false },
                    },
                },
            },
        });
        const sizes: unknown[] = [];
        const create = ({ props }: { props: Record<string, unknown> }) => {
            sizes.push(props.size);
            return {} as Component<unknown, unknown>;
        };

        mountAll('ui:converted', create);
        mount('ui:converted', 'c', create);

        expect(sizes).toEqual(['LG', 'MD', 'MD']);
    });

    test('mount returns the mounted instance instead of creating a second one, and a new one once it is destroyed', () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = '<div data-collapsible-root="c1"></div>';
        const create = vi.fn(({ props }) => new Collapsible(props as never).init());

        const first = mount('ui:collapsible', 'c1', create);
        expect(mount('ui:collapsible', 'c1', create)).toBe(first);
        expect(create).toHaveBeenCalledOnce();

        first!.destroy();
        expect(getComponentInstance('ui:collapsible', 'c1')).toBeUndefined();

        const second = mount('ui:collapsible', 'c1', create);
        expect(second).not.toBe(first);
        expect(getComponentInstance('ui:collapsible', 'c1')).toBe(second);
        second!.destroy();
    });

    test('mounts a rootId that is also the name of an Object.prototype member', () => {
        setGlobals({
            hydrationData: {
                ui: {
                    widget: {
                        constructor: field('constructor'),
                        toString: { ...field('toString'), autoMount: false },
                    },
                },
            },
        });
        const created: string[] = [];
        const create = ({ props }: { props: { id: string } }) => {
            created.push(props.id);
            return { id: props.id } as unknown as Component<unknown, unknown>;
        };

        mountAll('ui:widget', create);
        const manual = mount('ui:widget', 'toString', create);
        const unrendered = mount('ui:widget', 'valueOf', create);

        expect(created).toEqual(['constructor', 'toString']);
        expect(unrendered).toBeUndefined();
        expect(getComponentInstance('ui:widget', 'toString')).toBe(manual);
    });

    test('keeps a component named like an Object.prototype member, and a __proto__ rootId, in buckets of their own', () => {
        // JSON.parse keeps "__proto__" as an own key, as a fragment's payload would
        setGlobals({
            hydrationData: JSON.parse(
                '{"ui": {"constructor": {"__proto__": {"autoMount": true, "props": {"id": "__proto__", "ids": {}}}}}}'
            ),
        });
        document.body.innerHTML = '<div data-collapsible-root="__proto__"></div>';
        const create = vi.fn(({ props }) => new Collapsible(props as never).init());

        const instance = mount('ui:constructor', '__proto__', create);
        expect(mount('ui:constructor', '__proto__', create)).toBe(instance);
        expect(create).toHaveBeenCalledOnce();
        expect(Object.hasOwn(Object, '__proto__')).toBe(false);

        instance!.destroy();
        expect(getComponentInstance('ui:constructor', '__proto__')).toBeUndefined();
    });
});

describe('markup that arrives or leaves after mountAll', () => {
    const collapsible = (id: string) => `
        <div data-collapsible-root="${id}">
            <button type="button" data-collapsible-trigger="${id}">Toggle</button>
            <div data-collapsible-content="${id}">Content</div>
        </div>`;
    const dataBlock = (ids: string[]) =>
        `<script type="application/json" data-fluid-primitives-hydration-data>${JSON.stringify({
            hydrationData: {
                ui: { collapsible: Object.fromEntries(ids.map(id => [id, field(id)])) },
            },
        })}</script>`;
    // The observer runs in a microtask, a removal is destroyed in the task after that.
    const nextTask = () => new Promise(resolve => setTimeout(resolve));
    const settled = async () => {
        await nextTask();
        await nextTask();
    };
    const mountCollapsibles = () =>
        mountAll('ui:collapsible', ({ props }) => new Collapsible(props as never).init());
    const instance = (id: string) => getComponentInstance<Collapsible>('ui:collapsible', id);

    test('a swapped-in region mounts with its own data block, the swapped-out one is destroyed and mounts again when it comes back', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = `<main>${collapsible('c1')}</main>`;
        mountCollapsibles();
        const first = instance('c1')!;
        const destroyFirst = vi.spyOn(first, 'destroy');
        const main = document.querySelector('main')!;
        const oldContent = main.innerHTML;

        // what Turbo does with a frame: new markup, and the data block of its components
        main.innerHTML = collapsible('c2') + dataBlock(['c2']);
        await settled();

        expect(destroyFirst).toHaveBeenCalledOnce();
        expect(instance('c1')).toBeUndefined();
        // read and removed, so the block of the next swap does not pile up next to it
        expect(document.querySelector('[data-fluid-primitives-hydration-data]')).toBeNull();
        document.querySelector<HTMLElement>('[data-collapsible-trigger="c2"]')!.click();
        await vi.waitFor(() =>
            expect(
                document.querySelector('[data-collapsible-root="c2"]')!.getAttribute('data-state')
            ).toBe('open')
        );

        // the data of c1 stayed, but c1 mounts only once its markup is back, also from a second
        // mountAll(), as one on `turbo:load` would be
        document.body.append(document.createElement('p'));
        await settled();
        mountCollapsibles();
        expect(instance('c1')).toBeUndefined();

        main.innerHTML = oldContent;
        await settled();
        expect(instance('c1')).toBeDefined();
        expect(instance('c1')).not.toBe(first);
        expect(instance('c2')).toBeUndefined();
    });

    test('a root that moves to another parent keeps its instance', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = `<section>${collapsible('c1')}</section><aside></aside>`;
        mountCollapsibles();
        const mounted = instance('c1');

        document
            .querySelector('aside')!
            .append(document.querySelector('[data-collapsible-root="c1"]')!);
        await settled();

        expect(instance('c1')).toBe(mounted);
    });

    test('a root that is detached and put back across an await keeps its instance', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = `<section>${collapsible('c1')}</section>`;
        mountCollapsibles();
        const mounted = instance('c1');
        const root = document.querySelector('[data-collapsible-root="c1"]')!;

        // as Turbo does with a data-turbo-permanent element: the observer sees it gone in between
        root.remove();
        await Promise.resolve();
        document.body.append(root);
        await settled();

        expect(instance('c1')).toBe(mounted);
    });

    test('the markup of the next page with the rootId of a mounted component mounts once the old one is destroyed', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { nav: field('nav') } } } });
        document.body.innerHTML = `<main>${collapsible('nav')}</main>`;
        mountCollapsibles();
        const first = instance('nav');

        // a layout component of every page that Turbo Drive swaps in with the next body
        const next = document.createElement('main');
        next.innerHTML = collapsible('nav');
        document.querySelector('main')!.replaceWith(next);
        await settled();
        await settled();

        expect(instance('nav')).toBeDefined();
        expect(instance('nav')).not.toBe(first);
        next.querySelector<HTMLElement>('[data-collapsible-trigger]')!.click();
        await vi.waitFor(() =>
            expect(next.firstElementChild!.getAttribute('data-state')).toBe('open')
        );
    });

    test('an in-place swap mounts the new markup, also when cleanup code destroys the old instance afterwards', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = `<section>${collapsible('c1')}</section>`;
        mountCollapsibles();
        await settled();
        const first = instance('c1')!;

        // a swap whose cleanup runs after the new markup is in, as htmx or a turbo:load handler does
        document.querySelector('section')!.innerHTML = collapsible('c1');
        await Promise.resolve();
        first.destroy();
        await settled();

        expect(instance('c1')).toBeDefined();
        expect(instance('c1')).not.toBe(first);
    });

    test('a part of an intact instance added elsewhere does not mount it again once it leaves', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = `<section>${collapsible('c1')}</section>`;
        mountCollapsibles();
        await settled();

        // a part the component only queries, like the status text of a FieldArray
        document.body.insertAdjacentHTML('beforeend', '<p data-collapsible-status="c1">2 of 3</p>');
        await settled();
        document.querySelector('section')!.remove();
        await settled();

        expect(instance('c1')).toBeUndefined();
    });

    test('a component whose state changes after its markup left is destroyed all the same', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = `<section>${collapsible('c1')}</section>`;
        mountCollapsibles();
        const mounted = instance('c1')!;
        const destroy = vi.spyOn(mounted, 'destroy');

        document.querySelector('section')!.remove();
        await Promise.resolve();
        mounted.api.setOpen(true);
        await settled();

        expect(destroy).toHaveBeenCalledOnce();
    });

    test('an instance that stays on the page and is destroyed by hand does not mount the parts that arrived for it', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        const trigger = '<button type="button" data-collapsible-trigger="c1">Toggle</button>';
        document.body.innerHTML = `${collapsible('c1')}<ul><li>${trigger}</li></ul>`;
        mountCollapsibles();
        await settled();
        const mounted = instance('c1')!;

        // one trigger of a list leaves and a new one arrives, as an htmx filter does
        document.querySelector('li')!.remove();
        document.querySelector('ul')!.insertAdjacentHTML('beforeend', `<li>${trigger}</li>`);
        await settled();
        mounted.destroy();
        await settled();

        expect(instance('c1')).toBeUndefined();
    });

    test('an instance destroyed on purpose stays destroyed when other markup leaves or new data arrives', async () => {
        setGlobals({
            hydrationData: { ui: { collapsible: { c1: field('c1'), c2: field('c2') } } },
        });
        document.body.innerHTML = collapsible('c1') + `<section>${collapsible('c2')}</section>`;
        mountCollapsibles();
        await settled();
        instance('c1')!.destroy();

        document.querySelector('section')!.remove();
        document.body.insertAdjacentHTML('beforeend', collapsible('c3') + dataBlock(['c3']));
        await settled();

        expect(instance('c2')).toBeUndefined();
        expect(instance('c3')).toBeDefined();
        expect(instance('c1')).toBeUndefined();
    });

    test('a callback that returns no instance sets up a component without parts, once', async () => {
        setGlobals({ hydrationData: { ui: { 'command-menu': { m1: field('m1') } } } });
        const setUp = vi.fn();
        mountAll('ui:commandMenu', setUp);
        expect(setUp).toHaveBeenCalledOnce();

        const part = document.createElement('div');
        part.setAttribute('data-command-menu-status', 'm1');
        document.body.append(part);
        await settled();
        mountAll('ui:commandMenu', setUp);

        expect(setUp).toHaveBeenCalledOnce();
    });

    test('a component without a root element is destroyed once the last of its parts left the page, its portaled content too', async () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        // like a Popover: the trigger in the content, the content portaled to the end of the body
        document.body.innerHTML = `
            <section><button type="button" data-collapsible-trigger="c1">Toggle</button></section>
            <div data-portal><div data-collapsible-content="c1">Content</div></div>`;
        mountCollapsibles();
        const destroy = vi.spyOn(instance('c1')!, 'destroy');

        document.querySelector('section')!.remove();
        await settled();
        expect(destroy).not.toHaveBeenCalled();

        document.querySelector('[data-portal]')!.remove();
        await settled();
        expect(destroy).toHaveBeenCalledOnce();
    });

    test('mountWithin mounts inserted markup before the next line runs', () => {
        setGlobals({ hydrationData: { ui: { collapsible: { c1: field('c1') } } } });
        document.body.innerHTML = collapsible('c1');
        mountCollapsibles();
        // copied for a clone, as a Template does
        window.FluidPrimitives.hydrationData.ui.collapsible.c2 = field('c2');

        const holder = document.createElement('div');
        holder.innerHTML = collapsible('c2');
        document.body.append(holder);
        expect(instance('c2')).toBeUndefined();

        mountWithin(holder);
        expect(instance('c2')).toBeDefined();
    });
});

describe('reading the hydration data of the page', () => {
    const block = (data: object) =>
        `<script type="application/json" data-fluid-primitives-hydration-data>${JSON.stringify(data)}</script>`;
    const entry = (id: string) => ({ autoMount: true, props: { id, ids: {} } });

    test('combines the block of the cached content and the one of the uncached content, once', () => {
        window.FluidPrimitives = undefined as never;
        document.head.innerHTML =
            block({
                globals: { locale: 'en_US', debug: false },
                hydrationData: {
                    ui: {
                        button: { b1: entry('b1') },
                        molecules: { 'checkbox-group': { c1: entry('c1') } },
                    },
                },
                nestedComponents: {
                    'field-array:fa1:itemTemplate': [{ name: 'ui:field', id: 'f1' }],
                },
            }) +
            block({
                globals: { locale: 'en_US', debug: true },
                hydrationData: {
                    ui: { button: { b2: entry('b2') }, 'flash-messages': { m1: entry('m1') } },
                },
                nestedComponents: {},
            });

        expect(Object.keys(getHydrationData('ui:button')!)).toEqual(['b1', 'b2']);
        expect(getHydrationData('ui:molecules.checkboxGroup', 'c1')).toEqual(entry('c1'));
        expect(getHydrationData('ui:flashMessages', 'm1')).toEqual(entry('m1'));
        expect(getGlobals()?.debug).toBe(true);
        expect(Object.keys(window.FluidPrimitives.nestedComponents!)).toEqual([
            'field-array:fa1:itemTemplate',
        ]);

        // later lookups use what the first one read, which took the blocks out of the page
        expect(document.head.innerHTML).toBe('');
        expect(getHydrationData('ui:button', 'b2')).toEqual(entry('b2'));
    });

    test('reports a block that is no valid JSON, takes it out of the page and reads the others', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        window.FluidPrimitives = undefined as never;
        document.head.innerHTML =
            '<script type="application/json" data-fluid-primitives-hydration-data>{"hydra</script>' +
            block({ hydrationData: { ui: { button: { b1: entry('b1') } } } });

        expect(getHydrationData('ui:button', 'b1')).toEqual(entry('b1'));
        expect(error).toHaveBeenCalledOnce();
        expect(document.head.innerHTML).toBe('');
    });

    test('files a component named like an Object.prototype member under its own name', () => {
        window.FluidPrimitives = undefined as never;
        document.head.innerHTML = block({
            hydrationData: { ui: { constructor: { c1: entry('c1'), name: entry('name') } } },
        });

        expect(getHydrationData('ui:constructor', 'c1')).toEqual(entry('c1'));
        expect(getHydrationData('ui:constructor', 'name')).toEqual(entry('name'));
        expect(Object.hasOwn(Object, 'c1')).toBe(false);
        document.head.innerHTML = '';
    });

    test('files rootIds named like the keys of an entry as entries of their component', () => {
        window.FluidPrimitives = undefined as never;
        document.head.innerHTML =
            block({ hydrationData: { ui: { widget: { w1: entry('w1') } } } }) +
            block({
                hydrationData: {
                    ui: { widget: { autoMount: entry('autoMount'), props: entry('props') } },
                },
            });

        expect(Object.keys(getHydrationData('ui:widget')!)).toEqual(['w1', 'autoMount', 'props']);
        document.head.innerHTML = '';
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

        hydrator.restampValue(clone, '3', hydrator.scopeKey('itemTemplate'));

        // the row's own parts take the value, the nested Field is another component's scope
        expect(clone.getAttribute('data-value')).toBe('3');
        expect(attr(clone, '[data-field-array-remove-trigger]', 'data-value')).toBe('3');
        expect(attr(clone, '[data-field-root]', 'data-value')).toBeNull();
        // every reference to the stencil's Field id follows it, ids included. The new id says
        // nothing about the value: the row moves, the id stays.
        expect(attr(clone, '[data-field-root]', 'data-field-root')).toBe('fld:1');
        expect(attr(clone, 'label', 'data-field-label')).toBe('fld:1');
        expect(attr(clone, 'input', 'data-field-control')).toBe('fld:1');
        expect(attr(clone, 'input', 'id')).toBe('field:fld:1:control');
        // the clone's hydration data is a copy, the stencil's stays for the next clone
        const fields = window.FluidPrimitives.hydrationData.primitives.field;
        expect(Object.keys(fields)).toEqual(['fld', 'fld:1']);
        expect(fields['fld:1'].props).toMatchObject({
            id: 'fld:1',
            name: 'people[3][name]',
            ids: { control: 'field:fld:1:control' },
        });
    });

    test('restampValue remaps a rootId that also occurs inside an earlier segment of an id', () => {
        // `f` is also the first letter of the `field:` prefix
        setGlobals({
            hydrationData: {
                primitives: {
                    field: {
                        f: field('f', {
                            name: 'people[][name]',
                            ids: { control: 'field:f:control', description: 'contact-f' },
                        }),
                    },
                },
            },
            nestedComponents: {
                'field-array:fa1:item-template': [{ name: 'primitives:field', id: 'f' }],
            },
        });
        document.body.innerHTML = `<template>${row('', 'f')}</template>`;
        const clone = document.querySelector('template')!.content.firstElementChild!;
        const hydrator = new ComponentHydrator('fieldArray', 'fa1');

        hydrator.restampValue(clone, '0', hydrator.scopeKey('itemTemplate'));

        expect(attr(clone, 'label', 'id')).toBe('field:f:1:label');
        expect(attr(clone, 'input', 'id')).toBe('field:f:1:control');
        // an id of the project's own that contains the rootId, joined by something else than `:`
        expect(window.FluidPrimitives.hydrationData.primitives.field['f:1'].props.ids).toEqual({
            control: 'field:f:1:control',
            description: 'contact-f:1',
        });
    });

    test('every clone of a stencil gets ids no other clone has, whatever its value', () => {
        setGlobals({
            hydrationData: {
                primitives: { field: { fld: fieldEntry('fld', 'people[][name]') } },
            },
            nestedComponents: {
                'field-array:fa1:item-template': [{ name: 'primitives:field', id: 'fld' }],
            },
        });
        document.body.innerHTML = `<template>${row('', 'fld')}</template>`;
        const stencil = document.querySelector('template')!.content.firstElementChild!;
        const hydrator = new ComponentHydrator('fieldArray', 'fa1');
        const clone = (value: string) => {
            const el = stencil.cloneNode(true) as Element;
            hydrator.restampValue(el, value, hydrator.scopeKey('itemTemplate'));
            return attr(el, '[data-field-root]', 'data-field-root');
        };

        expect([clone('0'), clone('0'), clone('7')]).toEqual(['fld:1', 'fld:2', 'fld:3']);
        expect(Object.keys(window.FluidPrimitives.hydrationData.primitives.field)).toEqual([
            'fld',
            'fld:1',
            'fld:2',
            'fld:3',
        ]);
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

        const fields = window.FluidPrimitives.hydrationData.primitives.field;
        expect(clone.getAttribute('data-value')).toBe('$&');
        expect(fields['fld:1'].props.name).toBe('people[$&][name]');
    });

    test('destroyComponentsWithin destroys what renders inside the element and forgets its data, nothing already detached elsewhere', () => {
        const collapsible = (id: string) => `
            <div data-collapsible-root="${id}">
                <button type="button" data-collapsible-trigger="${id}">Toggle</button>
            </div>`;
        setGlobals({
            hydrationData: {
                primitives: { collapsible: { c1: field('c1'), c2: field('c2'), c3: field('c3') } },
            },
        });
        document.body.innerHTML = `<section>${collapsible('c1')}</section>${collapsible('c2')}${collapsible('c3')}`;
        mountAll('primitives:collapsible', ({ props }) => new Collapsible(props as never).init());
        // detached to be moved somewhere else
        document.querySelector('[data-collapsible-root="c3"]')!.remove();

        destroyComponentsWithin(document.querySelector('section')!);

        expect(
            Object.keys(window.FluidPrimitives.componentInstances.primitives.collapsible)
        ).toEqual(['c2', 'c3']);
        expect(Object.keys(window.FluidPrimitives.hydrationData.primitives.collapsible)).toEqual([
            'c2',
            'c3',
        ]);
    });
});
