<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum SliderThumbCollisionBehavior: string
{
    case None = 'none';
    case Push = 'push';
    case Swap = 'swap';
}
