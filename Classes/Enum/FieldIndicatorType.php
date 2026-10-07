<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum FieldIndicatorType: string
{
    case Required = 'required';
    case Invalid = 'invalid';
    case Valid = 'valid';
    case Validating = 'validating';
}
