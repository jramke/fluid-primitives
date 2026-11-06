<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum ToastPlacement: string
{
    case TopStart = 'top-start';
    case Top = 'top';
    case TopEnd = 'top-end';
    case BottomStart = 'bottom-start';
    case Bottom = 'bottom';
    case BottomEnd = 'bottom-end';
}
