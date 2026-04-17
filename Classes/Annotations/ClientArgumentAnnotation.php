<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Annotations;

use TYPO3Fluid\Fluid\Core\Definition\Annotation\ArgumentAnnotationInterface;

class ClientArgumentAnnotation implements ArgumentAnnotationInterface
{
    public function compile(): string
    {
        return 'new ' . static::class . '()';
    }
}
