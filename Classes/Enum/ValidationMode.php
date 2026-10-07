<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ValidationMode: string
{
    case OnSubmit = 'onSubmit';
    case OnBlur = 'onBlur';
    case OnChange = 'onChange';
}
