<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Annotations;

use TYPO3Fluid\Fluid\Core\Definition\Annotation\ArgumentAnnotationInterface;

/**
 * Marks the argument definition that is the reason a component allows undeclared additional
 * arguments - {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::getComponentDefinition()}
 * scans every argument definition's annotations for this one to decide
 * {@see \TYPO3Fluid\Fluid\Core\Component\ComponentDefinition::additionalArgumentsAllowed()}.
 */
class AdditionalArgumentsAllowedAnnotation implements ArgumentAnnotationInterface
{
    public function compile(): string
    {
        return 'new ' . static::class . '()';
    }
}
