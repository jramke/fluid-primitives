<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

/**
 * Resolves how a Field's generic ("label"/"control") ids map onto a specific field-aware
 * component's own part names, so a Field's `<label for>` reaches that component's real native input.
 */
class FieldIdMapping
{
    // Maps a component's `ui:ref` part name to the enclosing Field's `fieldIds` key ('label' or
    // 'control') it represents. Keep in sync with each field-aware Primitive's `propsWithField()`
    // override in its .ts file (client-side counterpart, via field.dom.ts's getLabelId/getControlId).
    private const array FIELD_ID_PARTS = [
        'select' => ['label' => 'label', 'control' => 'hiddenSelect'],
        'combobox' => ['label' => 'label', 'control' => 'input'],
        'input' => ['label' => 'label', 'control' => 'input'],
        'number-input' => ['label' => 'label', 'control' => 'input'],
        'textarea' => ['label' => 'label', 'control' => 'textarea'],
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
}
