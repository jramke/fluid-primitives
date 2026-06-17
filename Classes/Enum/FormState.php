<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum FormState: string
{
    case Ready = 'ready';
    case Invalid = 'invalid';
    case Submitting = 'submitting';
    case Success = 'success';
    case Error = 'error';
}
