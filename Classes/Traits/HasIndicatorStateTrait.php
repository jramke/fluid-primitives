<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use BackedEnum;

/**
 * Shared by Context classes whose indicator parts (e.g. an open/closed icon) are hidden unless they
 * match the component's current state. Requires the using class to have a `getState(): string` method.
 */
trait HasIndicatorStateTrait
{
    abstract public function getState(): string;

    public function isIndicatorHidden(BackedEnum $state): bool
    {
        return $this->getState() !== $state->value;
    }
}
