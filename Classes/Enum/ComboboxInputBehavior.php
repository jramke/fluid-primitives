<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ComboboxInputBehavior: string
{
    case Autohighlight = 'autohighlight';
    case Autocomplete = 'autocomplete';
    case None = 'none';
}
