<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Enum\ButtonIndicatorState;
use Jramke\FluidPrimitives\Traits\HasIndicatorStateTrait;
use Jramke\FluidPrimitives\Utility\Typed;

class ButtonContext extends AbstractComponentContext
{
    use HasIndicatorStateTrait;

    // A loading button is disabled too.
    public function getDisabled(): bool
    {
        return Typed::bool($this->get('disabled')) || Typed::bool($this->get('loading'));
    }

    public function getState(): string
    {
        if (Typed::bool($this->get('loading'))) {
            return ButtonIndicatorState::Loading->value;
        }

        return $this->getDisabled() ? ButtonIndicatorState::Disabled->value : ButtonIndicatorState::Idle->value;
    }
}
