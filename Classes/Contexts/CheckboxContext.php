<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Traits\HasCheckedStateDataAttributesTrait;

class CheckboxContext extends AbstractComponentContext
{
    use HasCheckedStateDataAttributesTrait;

    public function isValueValid(): bool
    {
        $defaultValue = $this->get('defaultChecked') ?? null;
        return is_bool($defaultValue) || $defaultValue === 'indeterminate';
    }

    public function isChecked(): bool
    {
        $checked = $this->get('defaultChecked') ?? null;
        return $this->isIndeterminate() ? false : (bool)$checked;
    }

    public function isIndeterminate(): bool
    {
        $checked = $this->get('defaultChecked') ?? null;
        return $checked === 'indeterminate';
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
