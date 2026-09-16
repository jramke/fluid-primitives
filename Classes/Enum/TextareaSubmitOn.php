<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum TextareaSubmitOn: string
{
    case Enter = 'enter';
    case ModEnter = 'mod+enter';
}
