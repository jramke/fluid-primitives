<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Utility\Typed;

class CheckboxGroupContext extends AbstractComponentContext
{
    /**
     * Provides variables to be merged into child Checkbox components.
     * Similar to how FieldContext provides variables to child components.
     *
     * @param array $childArguments Arguments from the child Checkbox component
     * @return array{name: ?string, disabled: ?bool, readOnly: ?bool, invalid: ?bool, defaultChecked: ?bool}
     */
    public function getChildVariables(array $childArguments = []): array
    {
        $value = Typed::stringOrNull($childArguments['value'] ?? null);
        $itemDisabled = Typed::boolOrNull($childArguments['disabled'] ?? null);
        $itemInvalid = Typed::boolOrNull($childArguments['invalid'] ?? null);

        // Calculate defaultChecked based on whether the value is in defaultValue array
        $defaultChecked = null;
        if ($value !== null) {
            $defaultChecked = $this->isValueChecked($value);
        }

        // Calculate disabled state considering max selection
        $disabled = $itemDisabled;
        if ($disabled === null && $value !== null && $this->isValueDisabledByMax($value)) {
            $disabled = true;
        }

        return [
            'name' => Typed::stringOrNull($this->get('name')),
            'disabled' => $disabled ?? Typed::boolOrNull($this->get('disabled')),
            'readOnly' => Typed::boolOrNull($this->get('readOnly')),
            'invalid' => $itemInvalid ?? Typed::boolOrNull($this->get('invalid')),
            'defaultChecked' => $defaultChecked,
        ];
    }

    /**
     * Get the checked state for a specific value.
     * Used by child Checkbox components to determine their defaultChecked state.
     */
    public function isValueChecked(string $value): bool
    {
        $defaultValue = Typed::arrayOrNull($this->get('defaultValue')) ?? [];
        return in_array($value, $defaultValue, strict: true);
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
        $maxSelectedValues = Typed::intOrNull($this->get('maxSelectedValues'));
        if ($maxSelectedValues === null) {
            return false;
        }

        $currentCount = count(Typed::arrayOrNull($this->get('defaultValue')) ?? []);

        return $currentCount >= $maxSelectedValues;
    }
}
