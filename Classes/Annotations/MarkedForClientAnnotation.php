<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace Jramke\FluidPrimitives\Annotations;

use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentAnnotationInterface;

class MarkedForClientAnnotation implements ArgumentAnnotationInterface
{
    private bool $state;

    public function __construct(bool $state)
    {
        $this->state = $state;
    }
}
