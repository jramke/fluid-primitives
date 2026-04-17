<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class SwitchContext extends AbstractComponentContext
{
    public function getDataAttributes(): array
    {
        return [
            'readonly' => $this->get('readOnly') ?? null,
            'disabled' => $this->get('disabled') ?? null,
            'state' => $this->getState(),
            'invalid' => $this->get('invalid') ?? null,
            'required' => $this->get('required') ?? null,
        ];
    }

    public function getState(): string
    {
        return $this->isChecked() ? 'checked' : 'unchecked';
    }

    public function isChecked(): bool
    {
        return (bool)($this->get('defaultChecked') ?? false);
    }
}
