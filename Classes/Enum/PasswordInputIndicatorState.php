<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum PasswordInputIndicatorState: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
}
