<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Traits\HasCheckedStateDataAttributesTrait;
use Jramke\FluidPrimitives\Traits\HasEmptyValueInputTrait;
use Jramke\FluidPrimitives\Utility\Typed;

class SwitchContext extends AbstractComponentContext
{
    use HasCheckedStateDataAttributesTrait;
    use HasEmptyValueInputTrait;

    public function getState(): string
    {
        return $this->isChecked() ? 'checked' : 'unchecked';
    }

    public function isChecked(): bool
    {
        return Typed::bool($this->get('defaultChecked'));
    }
}
