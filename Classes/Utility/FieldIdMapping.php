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

    // A component listed here takes its field state from the listed ancestor, not from the enclosing
    // Field. Mirrors Checkbox.ts's client-side getClosestField(): a checkbox nested in a CheckboxGroup
    // must not claim the Field's label/control id for itself (each checkbox has its own hidden input,
    // so all of them doing so would produce duplicate ids) nor take its `required` ("all of them have
    // to be checked", where the group means "at least one"). The group owns both and carries its name,
    // disabled, readOnly and invalid state down to its checkboxes itself.
    private const array FIELD_STATE_FROM_ANCESTOR = [
        'checkbox' => ['checkbox-group'],
    ];

    public static function getOverrideFieldIdKey(string $componentName, string $part): ?string
    {
        return self::FIELD_ID_PARTS[$componentName][$part] ?? null;
    }

    /**
     * @return string[] The ancestor components (kebab-case) the given component takes its field state from.
     */
    public static function getAncestorsProvidingFieldState(string $component): array
    {
        return self::FIELD_STATE_FROM_ANCESTOR[$component] ?? [];
    }
}
