<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum MaxLengthBehavior: string
{
    case Block = 'block';
    case Warn = 'warn';
    case Invalid = 'invalid';
}
