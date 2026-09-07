<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ComboboxSelectionBehavior: string
{
    case Clear = 'clear';
    case Replace = 'replace';
    case Preserve = 'preserve';
}
