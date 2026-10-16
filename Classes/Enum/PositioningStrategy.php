<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum PositioningStrategy: string
{
    case Absolute = 'absolute';
    case Fixed = 'fixed';
}
