<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum NumberInputMode: string
{
    case Text = 'text';
    case Tel = 'tel';
    case Numeric = 'numeric';
    case Decimal = 'decimal';
}
