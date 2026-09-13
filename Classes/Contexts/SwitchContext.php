<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Traits\HasCheckedStateDataAttributesTrait;

class SwitchContext extends AbstractComponentContext
{
    use HasCheckedStateDataAttributesTrait;

    public function getState(): string
    {
        return $this->isChecked() ? 'checked' : 'unchecked';
    }

    public function isChecked(): bool
    {
        return (bool)($this->get('defaultChecked') ?? false);
    }
}
