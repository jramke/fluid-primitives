import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import {
    ComponentHydrator,
    destroyComponentsWithin,
    devWarn,
    getGlobal,
    getGlobals,
    isDebug,
    mount,
    type Component,
} from '../../../Resources/Private/Client/src/lib';

function setGlobals(globals: Record<string, unknown> | undefined, hydrationData = {}) {
    window.FluidPrimitives = {
        hydrationData,
        componentInstances: {},
        globals,
    } as Window['FluidPrimitives'];
}

function removeFluidPrimitives() {
    // @ts-expect-error a page without a component or a global read has no object at all
    delete window.FluidPrimitives;
}

beforeEach(() => {
    document.body.innerHTML = '';
    removeFluidPrimitives();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('getGlobal', () => {
    beforeEach(() => {
        setGlobals({
            locale: 'de-DE',
            'dotted.key': 'literal',
            dotted: { key: 'nested' },
            lightbox: {
                icons: { close: '<svg/>' },
                list: [{ name: 'first' }, { name: 'second' }],
            },
            empty: {},
            nothing: null,
        });
    });

    test('reads a top level key', () => {
        expect(getGlobal('locale')).toBe('de-DE');
    });

    test('walks nested objects with a dot path', () => {
        expect(getGlobal('lightbox.icons.close')).toBe('<svg/>');
        expect(getGlobal('lightbox.icons')).toEqual({ close: '<svg/>' });
    });

    test('walks into arrays by index', () => {
        expect(getGlobal('lightbox.list.1.name')).toBe('second');
    });

    test('prefers a key that literally contains a dot', () => {
        expect(getGlobal('dotted.key')).toBe('literal');
    });

    test('keeps null and empty values', () => {
        expect(getGlobal('nothing', 'fallback')).toBeNull();
        expect(getGlobal('empty')).toEqual({});
    });

    test('returns undefined or the fallback for a missing path and never throws', () => {
        expect(getGlobal('missing')).toBeUndefined();
        expect(getGlobal('lightbox.missing.deeper')).toBeUndefined();
        expect(getGlobal('locale.deeper')).toBeUndefined();
        expect(getGlobal('nothing.deeper', 'x')).toBe('x');
        expect(getGlobal('missing', 42)).toBe(42);
        expect(getGlobal('lightbox.icons.toString')).toBeUndefined();
    });

    test('works without window.FluidPrimitives', () => {
        removeFluidPrimitives();

        expect(getGlobals()).toBeNull();
        expect(getGlobal('locale')).toBeUndefined();
        expect(getGlobal('locale', 'en')).toBe('en');
    });
});

describe('devWarn', () => {
    test('is silent unless debug is true', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        setGlobals({ debug: false });
        devWarn('hidden');
        setGlobals({});
        devWarn('hidden');
        removeFluidPrimitives();
        devWarn('hidden');

        expect(warn).not.toHaveBeenCalled();
        expect(isDebug()).toBe(false);
    });

    test('warns with a prefix when debug is true', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        setGlobals({ debug: true });

        devWarn('visible', 'detail');

        expect(isDebug()).toBe(true);
        expect(warn).toHaveBeenCalledWith('[fluid-primitives] visible', 'detail');
    });
});

describe('dev only output of the hydration helpers', () => {
    const fakeInstance = () =>
        ({
            hydrator: new ComponentHydrator('widget', 'a'),
            getClientName: () => 'widget',
            destroy: vi.fn(),
        }) as unknown as Component<unknown, unknown>;

    test('the destroy log only shows with debug', () => {
        const log = vi.spyOn(console, 'log').mockImplementation(() => {});
        document.body.innerHTML = '<div data-widget-root="a"></div>';

        for (const debug of [false, true]) {
            setGlobals({ debug });
            window.FluidPrimitives.componentInstances = { ui: { widget: { a: fakeInstance() } } };
            destroyComponentsWithin(document);
        }

        expect(log).toHaveBeenCalledTimes(1);
    });

    test('mount() warns about a rootId without hydration data and returns undefined', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        setGlobals({ debug: true });

        expect(mount('ui:widget', 'nope', () => fakeInstance())).toBeUndefined();
        expect(warn).toHaveBeenCalledWith(
            expect.stringContaining('no hydration data for ui:widget "nope"')
        );
    });

    test('mount() warns when it replaces a live instance', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        setGlobals({ debug: true }, { ui: { widget: { a: { autoMount: true, props: {} } } } });

        mount('ui:widget', 'a', () => fakeInstance());
        expect(warn).not.toHaveBeenCalled();

        mount('ui:widget', 'a', () => fakeInstance());
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('already mounted'));
    });
});
