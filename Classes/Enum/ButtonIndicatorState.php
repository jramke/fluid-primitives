<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ButtonIndicatorState: string
{
    case Idle = 'idle';
    case Loading = 'loading';
    case Disabled = 'disabled';
}
