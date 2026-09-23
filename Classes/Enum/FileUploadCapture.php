<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum FileUploadCapture: string
{
    case User = 'user';
    case Environment = 'environment';
}
