<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ToastType: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
    case Loading = 'loading';
}
