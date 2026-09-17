<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum SliderOrigin: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';
}
