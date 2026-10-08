<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum InputMode: string
{
    case None = 'none';
    case Text = 'text';
    case Tel = 'tel';
    case Url = 'url';
    case Email = 'email';
    case Numeric = 'numeric';
    case Decimal = 'decimal';
    case Search = 'search';
}
