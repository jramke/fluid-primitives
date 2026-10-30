<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ScrollBehavior: string
{
    case Auto = 'auto';
    case Instant = 'instant';
    case Smooth = 'smooth';
}
