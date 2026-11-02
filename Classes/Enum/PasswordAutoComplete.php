<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum PasswordAutoComplete: string
{
    case CurrentPassword = 'current-password';
    case NewPassword = 'new-password';
}
