<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum DatePickerSelectionMode: string
{
    case Single = 'single';
    case Multiple = 'multiple';
    case Range = 'range';
}
