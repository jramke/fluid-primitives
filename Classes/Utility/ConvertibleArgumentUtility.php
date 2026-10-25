<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Contracts\ConvertibleArgumentInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Stand-in for the argument conversion Fluid's `StrictArgumentProcessor` may get (TYPO3/Fluid#1415):
 * Fluid validates a component's arguments at the call site before any component code runs, so a prop
 * has to declare `array|ThatClass` (plus `string` for a shorthand) to let the value through, and the
 * conversion happens afterwards.
 */
final class ConvertibleArgumentUtility
{
    /**
     * The value as an instance of the first {@see ConvertibleArgumentInterface} class in the
     * definition's type, when it is an array, a `Traversable` or a scalar that is not one yet. A scalar
     * is converted even when the type has `string` too, so declare `string` only to let the shorthand
     * through. Anything else comes back unchanged, so converting twice is harmless.
     *
     * @throws \InvalidArgumentException when the class does not accept the value
     */
    public static function convert(mixed $value, ArgumentDefinition $definition): mixed
    {
        if (!is_scalar($value) && !is_array($value) && !$value instanceof \Traversable) {
            return $value;
        }

        foreach ($definition->getUnionTypes() as $type) {
            if (!class_exists($type) || !is_subclass_of($type, ConvertibleArgumentInterface::class)) {
                continue;
            }

            if ($value instanceof $type) {
                return $value;
            }

            // class_exists() above leaves out the interface itself, which mago cannot see.
            // @mago-expect analysis:possibly-static-access-on-interface
            return $type::fromArgument($value);
        }

        return $value;
    }
}
