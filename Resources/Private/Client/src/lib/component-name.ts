/**
 * @internal
 *
 * A part's own name is lowerCamelCase (mirroring zag-js's own `ids` prop keys), but its attribute
 * always renders lower-kebab. Keep in sync with: Classes/Utility/ComponentNameUtility.php's
 * camelCaseToLowerCaseDashed().
 */
export function toKebabCase(part: string): string {
    return part.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/**
 * @internal
 *
 * Splits a required `"namespace:name"` string (e.g. `"ui:select"`) into its two parts - every
 * public lookup (`getHydrationData`, `mountAll`, `mount`, `getComponentInstance`) requires this
 * form, with no bare-name fallback: two collections can legitimately define a same-named component
 * with a different shape (a styled wrapper around a primitive it doesn't expose every prop of), so
 * the caller always states which one it means.
 */
export function parseNamespacedComponentName(componentName: string): {
    namespace: string;
    baseName: string;
} {
    const separatorIndex = componentName.indexOf(':');
    if (separatorIndex === -1) {
        throw new Error(
            `[fluid-primitives] "${componentName}" must include a namespace, e.g. "ui:${componentName}" - ` +
                'mountAll/mount/getHydrationData/getComponentInstance always require one.'
        );
    }
    return {
        namespace: componentName.slice(0, separatorIndex),
        baseName: componentName.slice(separatorIndex + 1),
    };
}

/** @internal The prefix of every part attribute of a component, e.g. `data-accordion-`. */
export function partAttributePrefix(clientComponentName: string): string {
    return `data-${clientComponentName.replaceAll('.', '-')}-`;
}
