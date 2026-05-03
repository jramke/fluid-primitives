<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ClipboardIndicatorState: string
{
    case Idle = 'idle';
    case Copied = 'copied';
}
