<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

/**
 * Generates deterministic per-part DOM ids following the zag-js convention, and resolves how a
 * Field's generic ("label"/"control") ids map onto a specific field-aware component's own part names.
 */
class ComponentPartIdUtility
{
    // Keep in sync with: Resources/Private/Client/src/lib/hydration.ts
    private const array ID_NAMESPACE_OVERRIDES = [
        'navigation-menu' => 'nav-menu',
        'clipboard' => 'clip',
        'file-upload' => 'file',
    ];

    // Maps a component's `ui:ref` part name to the enclosing Field's `fieldIds` key ('label' or
    // 'control') it represents. Keep in sync with each field-aware Primitive's `propsWithField()`
    // override in its .ts file (client-side counterpart, via field.dom.ts's getLabelId/getControlId).
    private const array FIELD_ID_PARTS = [
        'select' => ['label' => 'label', 'control' => 'hiddenSelect'],
        'combobox' => ['label' => 'label', 'control' => 'input'],
        'input' => ['label' => 'label', 'control' => 'input'],
        'number-input' => ['label' => 'label', 'control' => 'input'],
        'switch' => ['label' => 'label', 'control' => 'hiddenInput'],
        'checkbox' => ['label' => 'label', 'control' => 'hiddenInput'],
        'file-upload' => ['label' => 'label', 'control' => 'hiddenInput'],
        'checkbox-group' => ['label' => 'label'],
    ];

    private const array FIELD_ID_OVERRIDE_KEYS = ['label', 'control'];

    // A component's FIELD_ID_PARTS override is suppressed while an ancestor context of this name
    // is on the ContextService stack. Mirrors Checkbox.ts's client-side getClosestCheckboxGroup()
    // check: a checkbox nested in a CheckboxGroup must not claim the enclosing Field's label/control
    // id for itself - each checkbox in the group has its own, separate hidden input, so all of them
    // doing so would produce duplicate ids. The group itself (not the individual checkbox) owns it.
    private const array FIELD_ID_EXCLUDED_WHEN_NESTED_IN = [
        'checkbox' => ['checkbox-group'],
    ];

    // Keep in sync with: Resources/Private/Client/src/lib/hydration.ts
    private const array PART_SEGMENT_OVERRIDES = [
        // TODO: Revisit this override map after upgrading to zag-js v2.
        'radio-group' => [
            'item' => 'radio',
            'itemHiddenInput' => 'radio:input',
            'itemControl' => 'radio:control',
            'itemText' => 'radio:label',
        ],
        'accordion' => [
            'itemTrigger' => 'trigger',
            'itemContent' => 'content',
        ],
        'select' => [
            'hiddenSelect' => 'select',
            'itemGroup' => 'optgroup',
            'itemGroupLabel' => 'optgroup-label',
            'item' => 'option',
        ],
        'combobox' => [
            'positioner' => 'popper',
            'trigger' => 'toggle-btn',
            'clearTrigger' => 'clear-btn',
            'itemGroup' => 'optgroup',
            'itemGroupLabel' => 'optgroup-label',
            'item' => 'option',
        ],
        'tabs' => [
            'trigger' => ['segment' => 'trigger', 'valueSeparator' => '-'],
            'content' => ['segment' => 'content', 'valueSeparator' => '-'],
        ],
        'number-input' => [
            'incrementTrigger' => 'inc',
            'decrementTrigger' => 'dec',
        ],
        'popover' => [
            'positioner' => 'popper',
            'description' => 'desc',
            'closeTrigger' => 'close',
        ],
        'switch' => [
            'hiddenInput' => 'input',
        ],
        'file-upload' => [
            'hiddenInput' => 'input',
            'itemSizeText' => 'item-size',
            'itemDeleteTrigger' => 'item-delete',
        ],
        'tooltip' => [
            'positioner' => 'popper',
        ],
        'dialog' => [
            'closeTrigger' => 'close',
        ],
        'scroll-area' => [
            'root' => ['segment' => 'root', 'rootIdSeparator' => '-'],
            'viewport' => ['segment' => 'viewport', 'rootIdSeparator' => '-'],
            'content' => ['segment' => 'content', 'rootIdSeparator' => '-'],
        ],
    ];

    /**
     * Generates a deterministic part ID following the zag-js DOM convention.
     *
     * - Explicit override in `$idsOverrides[$part]` takes priority.
     * - The root part returns `{idNamespace}:{rootId}` (no suffix), matching zag-js.
     *   Any provided `$value` is ignored for the root part.
     * - Multi-instance parts with a `$value` return `{idNamespace}:{rootId}:{partSegment}:{value}`.
     * - All other parts return `{idNamespace}:{rootId}:{partSegment}`.
     */
    public static function generatePartId(
        string $componentName,
        string $rootId,
        string $part,
        ?string $value = null,
        array $idsOverrides = [],
    ): string {
        if (($idsOverrides[$part] ?? null) !== null && $idsOverrides[$part] !== '') {
            return (string)$idsOverrides[$part];
        }

        $idNamespace = self::getIdNamespace($componentName);
        [
            'segment' => $partSegment,
            'valueSeparator' => $valueSeparator,
            'rootIdSeparator' => $rootIdSeparator,
        ] = self::getPartConfig($componentName, $part);

        if ($part === 'root') {
            return "{$idNamespace}{$rootIdSeparator}{$rootId}";
        }

        if ($value !== null && $value !== '') {
            return "{$idNamespace}{$rootIdSeparator}{$rootId}:{$partSegment}{$valueSeparator}{$value}";
        }

        return "{$idNamespace}{$rootIdSeparator}{$rootId}:{$partSegment}";
    }

    public static function getOverrideFieldIdKey(string $componentName, string $part): ?string
    {
        return self::FIELD_ID_PARTS[$componentName][$part] ?? null;
    }

    /**
     * @return string[]
     */
    public static function shouldSkipFieldIdsInheritanceWhenNestedIn(string $nestedComponent): array
    {
        return self::FIELD_ID_EXCLUDED_WHEN_NESTED_IN[$nestedComponent] ?? [];
    }

    /**
     * @return string[]
     */
    public static function getFieldIdOverrideKeys(): array
    {
        return self::FIELD_ID_OVERRIDE_KEYS;
    }

    private static function getIdNamespace(string $componentName): string
    {
        return self::ID_NAMESPACE_OVERRIDES[$componentName] ?? $componentName;
    }

    /**
     * @return array{segment: string, valueSeparator: string, rootIdSeparator: string}
     */
    private static function getPartConfig(string $componentName, string $part): array
    {
        $override = self::PART_SEGMENT_OVERRIDES[$componentName][$part] ?? null;
        if (!is_array($override)) {
            return [
                'segment' => is_string($override) ? $override : $part,
                'valueSeparator' => ':',
                'rootIdSeparator' => ':',
            ];
        }

        $segment = $override['segment'];
        $valueSeparator = $override['valueSeparator'] ?? ':';
        $rootIdSeparator = $override['rootIdSeparator'] ?? ':';
        return ['segment' => $segment, 'valueSeparator' => $valueSeparator, 'rootIdSeparator' => $rootIdSeparator];
    }
}
