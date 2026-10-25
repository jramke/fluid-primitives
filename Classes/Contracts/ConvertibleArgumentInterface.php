<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contracts;

/**
 * A value object a prop takes as a plain array too: declare the prop as `array|ThatClass` and
 * {@see \Jramke\FluidPrimitives\Utility\ConvertibleArgumentUtility} turns a given array into an
 * instance before the component renders, so `{context.x}`, `$context->get('x')` and the hydration
 * payload all see the object, defaults included.
 *
 * Mirrors the `ConvertibleArgumentInterface` proposed for Fluid's `StrictArgumentProcessor`
 * (TYPO3/Fluid#1415), so a class implementing it moves over by changing the `use` statement.
 */
interface ConvertibleArgumentInterface
{
    /**
     * @throws \InvalidArgumentException when the value is not a shape this class accepts
     */
    public static function fromArgument(mixed $value): static;
}
