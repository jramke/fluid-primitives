import { toKebabCase } from './hydration';

/**
 * Converts a prop from the shape it has on the wire (JSON) to the one the component needs. It gets
 * the wire value and all props of the component.
 */
export type ClientPropConverter<TWire = unknown, TMachine = unknown> = (
    wireValue: TWire,
    props: Record<string, unknown>
) => TMachine;

/** The converters of one component, by prop name. */
export type ClientPropConverterMap = Record<string, ClientPropConverter<any, any>>;

/**
 * The shape each prop of a converters object has after conversion. Use it for the
 * `HydrationPropsOverrides` of a component, derived from the converters with `typeof` instead of
 * typed a second time.
 */
export type ConverterMachineProps<T extends ClientPropConverterMap> = {
    [K in keyof T]: T[K] extends ClientPropConverter<any, infer TMachine> ? TMachine : never;
};

const converters = new Map<string, ClientPropConverterMap>();

/**
 * Registers which props of a component need converting before a `mountAll()` or `mount()` callback
 * receives them, e.g. the `collection` of a Select: JSON on the wire, a `ListCollection` once
 * converted.
 *
 * @param componentName - The component, e.g. `select`.
 * @param propConverters - The converters, by prop name.
 */
export function registerClientPropConverters<T extends ClientPropConverterMap>(
    componentName: string,
    propConverters: T
): void {
    converters.set(toKebabCase(componentName), propConverters);
}

/**
 * @internal
 *
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
