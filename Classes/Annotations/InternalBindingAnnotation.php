<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Annotations;

use TYPO3Fluid\Fluid\Core\Definition\Annotation\ArgumentAnnotationInterface;

/**
 * Marks an argument definition as a component's own internal wiring (e.g. the prop-name bundle
 * `ui:useProps`'s `as=` binds for a later `spreadProps` forward) rather than a real prop of whatever
 * it was imported from. Unlike the fixed names in {@see \Jramke\FluidPrimitives\Constants::NON_FORWARDABLE_PROPS},
 * a binding like this has an author-chosen name, so it can't be excluded from
 * {@see \Jramke\FluidPrimitives\Utility\PropsUtility::cleanupNonForwardableProps()} by name - it
 * carries its own "don't forward me" fact instead.
 */
class InternalBindingAnnotation implements ArgumentAnnotationInterface
{
    public function compile(): string
    {
        return 'new ' . static::class . '()';
    }
}
