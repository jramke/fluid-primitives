<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum FileUploadItemType: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
