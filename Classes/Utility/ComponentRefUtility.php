<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

/**
 * Names what `ui:ref` (and `ui:template`) mark a component's parts with, so the server and the
 * client agree on it.
 */
class ComponentRefUtility
{
    /**
     * The one attribute a part is marked with, mirroring zag's own `parts.<part>.attr`
     * (`data-<component>-<part>`), whose value is the component's root id. Dots of a tiered
     * `$clientBaseName` (`molecules.checkbox-group`) become dashes - a dot isn't valid in a CSS
     * attribute selector. Keep in sync with: Resources/Private/Client/src/lib/hydration.ts `attr()`.
     */
    public static function getAttributeName(string $clientBaseName, string $part): string
    {
        return (
            'data-' .
            str_replace('.', replace: '-', subject: $clientBaseName) .
            '-' .
            ComponentNameUtility::camelCaseToLowerCaseDashed($part)
        );
    }

    /**
     * Identifies one stencil (`ui:template`) or one repeated row (a `FieldArray` item) as a key of the
     * nested-component tracking registry, without needing an element id. Keep in sync with:
     * Resources/Private/Client/src/lib/hydration.ts `scopeKey()`.
     */
    public static function getScopeKey(
        string $clientBaseName,
        string $rootId,
        string $part,
        ?string $value = null,
    ): string {
        $key = $clientBaseName . ':' . $rootId . ':' . ComponentNameUtility::camelCaseToLowerCaseDashed($part);

        return $value === null || $value === '' ? $key : $key . ':' . $value;
    }
}
