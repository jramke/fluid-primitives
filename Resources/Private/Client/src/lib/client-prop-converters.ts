import { toKebabCase } from './hydration';

export type ClientPropConverter<TWire = unknown, TMachine = unknown> = (
    wireValue: TWire,
    props: Record<string, unknown>
) => TMachine;

export type ClientPropConverterMap = Record<string, ClientPropConverter<any, any>>;

/**
 * The post-conversion ("machine") shape a converters object actually produces per prop - what a
 * primitive's own `HydrationPropsOverrides` augmentation should declare, derived from the real
 * converters `const` via `typeof` instead of hand-typed a second time next to it. See `Select.ts`.
 */
export type ConverterMachineProps<T extends ClientPropConverterMap> = {
    [K in keyof T]: T[K] extends ClientPropConverter<any, infer TMachine> ? TMachine : never;
};

const converters = new Map<string, ClientPropConverterMap>();

/**
 * Registers, per component, which wire props need converting before a `mountAll`/`mount` callback
 * receives them (e.g. Select's `collection`: JSON on the wire, a real `ListCollection` instance
 * once converted) - always shipped as part of the primitive itself, independent of whether any
 * project ever generates hydration types for it.
 */
export function registerClientPropConverters<T extends ClientPropConverterMap>(
    componentName: string,
    propConverters: T
): void {
    converters.set(toKebabCase(componentName), propConverters);
}

/**
 * Runs every registered converter for `componentName` against `props`, called for every
 * registered key regardless of whether it's actually present on `props` - a converter is
 * registered per prop *name*, not per wire value, so it can supply a default for a prop the wire
 * data omitted entirely (e.g. Combobox's `collection` when a purely async, searchUrl-only combobox
 * sends none).
 */
export function applyClientPropConverters(
    componentName: string,
    props: Record<string, unknown>
): Record<string, unknown> {
    const componentConverters = converters.get(toKebabCase(componentName));
    if (!componentConverters) return props;

    const result = { ...props };
    for (const [propName, convert] of Object.entries(componentConverters)) {
        result[propName] = convert(result[propName], result);
    }
    return result;
}
