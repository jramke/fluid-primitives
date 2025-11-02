<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class CheckboxContext extends AbstractComponentContext
{
    public function isValueValid(): bool
    {
        $defaultValue = $this->get('defaultChecked') ?? null;
        return is_bool($defaultValue) || $defaultValue === 'indeterminate';
    }

    public function getDataAttributes(): array
    {
        return [
            'readonly' => $this->get('readOnly') ?? null,
            'disabled' => $this->get('disabled') ?? null,
            'state' => $this->isIndeterminate() ? 'indeterminate' : ($this->isChecked() ? 'checked' : 'unchecked'),
            'invalid' => $this->get('invalid') ?? null,
            'required' => $this->get('required') ?? null,
        ];
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
}
