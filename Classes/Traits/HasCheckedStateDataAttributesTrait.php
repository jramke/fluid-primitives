<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

/**
 * Shared by Context classes for checked/unchecked toggle controls (Checkbox, Switch). Requires the
 * using class to have a `getState(): string` method returning e.g. "checked"/"unchecked".
 */
trait HasCheckedStateDataAttributesTrait
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
}
