<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class CheckboxGroupContext extends AbstractComponentContext
{
    /**
     * Provides variables to be merged into child Checkbox components.
     * Similar to how FieldContext provides variables to child components.
     *
     * @param array $childArguments Arguments from the child Checkbox component
     */
    public function getChildVariables(array $childArguments = []): array
    {
        $value = $childArguments['value'] ?? null;
        $itemDisabled = $childArguments['disabled'] ?? null;
        $itemInvalid = $childArguments['invalid'] ?? null;

        // Calculate defaultChecked based on whether the value is in defaultValue array
        $defaultChecked = null;
        if ($value !== null) {
            $defaultChecked = $this->isValueChecked((string)$value);
        }

        // Calculate disabled state considering max selection
        $disabled = $itemDisabled;
        if ($disabled === null && $value !== null && $this->isValueDisabledByMax((string)$value)) {
            $disabled = true;
        }

        $name = $this->get('name') ?? null;
        if ($name !== null) {
            // Append [] to name for checkbox groups to handle multiple values
            $name .= '[]';
        }

        return [
            'name' => $name,
            'disabled' => $disabled ?? $this->get('disabled') ?? null,
            'readOnly' => $this->get('readOnly') ?? null,
            'invalid' => $itemInvalid ?? $this->get('invalid') ?? null,
            'defaultChecked' => $defaultChecked,
        ];
    }

    /**
     * Get the checked state for a specific value.
     * Used by child Checkbox components to determine their defaultChecked state.
     */
    public function isValueChecked(string $value): bool
    {
        $defaultValue = $this->get('defaultValue');
        return is_array($defaultValue) && in_array($value, $defaultValue, true);
    }

    /**
     * Check if a checkbox with the given value should be disabled due to max selection.
     */
    public function isValueDisabledByMax(string $value): bool
    {
        if (!$this->isAtMax()) {
            return false;
        }
        // If at max, only disable unchecked items
        return !$this->isValueChecked($value);
    }

    /**
     * Check if the maximum number of selected values has been reached.
     */
    public function isAtMax(): bool
    {
        $maxSelectedValues = $this->get('maxSelectedValues');
        if ($maxSelectedValues === null) {
            return false;
        }

        $defaultValue = $this->get('defaultValue');
        $currentCount = is_array($defaultValue) ? count($defaultValue) : 0;

        return $currentCount >= (int)$maxSelectedValues;
    }

    /**
     * Get item state for a checkbox. This is the main method used by child Checkbox components
     * to determine their checked/disabled state when inside a CheckboxGroup.
     *
     * @param string $value The checkbox value
     * @param bool|null $itemDisabled Whether the item is explicitly disabled
     * @param bool|null $itemInvalid Whether the item is explicitly invalid
     */
    public function getCheckboxState(string $value, ?bool $itemDisabled = null, ?bool $itemInvalid = null): array
    {
        $checked = $this->isValueChecked($value);
        $groupDisabled = $this->get('disabled') ?? false;
        $groupInvalid = $this->get('invalid') ?? false;
        $disabledByMax = $this->isValueDisabledByMax($value);

        return [
            'defaultChecked' => $checked,
            'disabled' => $itemDisabled ?? $groupDisabled || $disabledByMax,
            'invalid' => $itemInvalid ?? $groupInvalid,
            'readOnly' => $this->get('readOnly') ?? false,
            'name' => $this->get('name') ?? null,
        ];
    }
}
