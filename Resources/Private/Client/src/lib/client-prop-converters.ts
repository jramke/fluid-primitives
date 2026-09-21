import { toKebabCase } from './hydration';

/**
 * Converts one wire-shape hydration prop value into the shape a primitive's own machine expects -
 * the client-side counterpart to `ClientPropConverterInterface`/`ClientPropConverterRegistry`
 * (`Classes/Contracts`, `Classes/Registry`), minus the DI container: there's no tagged-service
 * collection here, just a plain runtime registry a primitive populates at module scope (e.g.
 * Select's `collection`, FileUpload's `translations` - see each primitive's own registration call).
 *
 * Registering one is what lets `mountAll`/`mount` (`hydration.ts`) apply the conversion *before*
 * constructing the component, unlike `Component.transformProps()` - which only runs inside the
 * constructor, too late to fix the constructor's own argument type (see that method's docblock).
 */
type ClientPropConverter<TWire = unknown, TMachine = unknown> = (
    wireValue: TWire,
    props: Record<string, unknown>
) => TMachine;

// Keyed by clientComponentName -> propName, mirroring how hydration data itself is looked up
// (see toKebabCase's own callers in hydration.ts) so registration is forgiving of camelCase vs.
// kebab-case the same way mountAll()/mount() already are.
const converters = new Map<string, Map<string, ClientPropConverter>>();

/**
 * Registers every converter a component needs in one call, keyed by prop name - e.g. Select's own
 * `{ collection: (collection: ListCollectionData) => getListCollectionFromHydrationData(collection) }`.
 */
export function registerClientPropConverters(
    componentName: string,
    propConverters: Record<string, ClientPropConverter>
): void {
    const clientComponentName = toKebabCase(componentName);
    let byPropName = converters.get(clientComponentName);
    if (!byPropName) {
        byPropName = new Map();
        converters.set(clientComponentName, byPropName);
    }
    for (const [propName, convert] of Object.entries(propConverters)) {
        byPropName.set(propName, convert);
    }
}

/**
 * Runs every converter registered for `componentName` against `props`, returning a new props
 * object with each converted prop replaced - called once per hydration instance by `mountAll`/
 * `mount`, before the instance is handed to their caller's `callback`. Runs a converter even when
 * its prop is absent from `props` (`wireValue` is then `undefined`) - Combobox's `collection`
 * relies on this to supply a default for that optional wire prop.
 */
export function applyClientPropConverters(
    componentName: string,
    props: Record<string, unknown>
): Record<string, unknown> {
    const byPropName = converters.get(toKebabCase(componentName));
    if (!byPropName) return props;

    const converted = { ...props };
    for (const [propName, convert] of byPropName) {
        converted[propName] = convert(props[propName], props);
    }
    return converted;
}
