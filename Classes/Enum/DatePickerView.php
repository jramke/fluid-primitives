<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum DatePickerView: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';
}
