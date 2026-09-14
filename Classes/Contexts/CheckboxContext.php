<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Traits\HasCheckedStateDataAttributesTrait;

class CheckboxContext extends AbstractComponentContext
{
    use HasCheckedStateDataAttributesTrait;

    public function isValueValid(): bool
    {
        return $this->getCheckedState() !== null;
    }

    public function isChecked(): bool
    {
        return $this->isIndeterminate() ? false : (bool)$this->getCheckedState();
    }

    public function isIndeterminate(): bool
    {
        return $this->getCheckedState() === 'indeterminate';
    }

    /**
     * `defaultChecked` is declared `mixed` (not `boolean`) because, unlike every other checked-state
     * prop in this codebase, it also accepts the literal string 'indeterminate' as a third state.
     */
    private function getCheckedState(): bool|string|null
    {
        // Tri-state (bool|'indeterminate') checked below - Typed::bool() can't express this, since it
        // only recognizes boolean-keyword strings, not the literal 'indeterminate' value.
        // @mago-expect analysis:mixed-assignment
        $value = $this->get('defaultChecked');
        return is_bool($value) || $value === 'indeterminate' ? $value : null;
    }

    protected function getState(): string
    {
        if ($this->isIndeterminate()) {
            return 'indeterminate';
        }

        if ($this->isChecked()) {
            return 'checked';
        }

        return 'unchecked';
    }
}
