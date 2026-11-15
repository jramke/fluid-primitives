<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Exception\HydrationException;
use JsonSerializable;
use ReflectionClass;
use stdClass;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * The null-safety and object rules {@see ComponentHydrationCollector} applies to each
 * client-marked prop's resolved value, before it's placed into the JSON hydration payload - split
 * out into its own collaborator purely to keep the collector's own class-level complexity down.
 */
final readonly class ClientPropValueResolver
{
    /**
     * A required prop (no default, optional="{false}") resolving to null means it was explicitly
     * passed null at the call site - Fluid's own required-argument check only guards presence, not
     * nullability. Silently dropping it later via `array_filter` would undermine the "required"
     * guarantee the generated hydration TS types assert.
     */
    public function assertRequiredPropNotNull(
        mixed $value,
        string $propName,
        string $clientBaseName,
        ?ArgumentDefinition $argumentDefinition,
    ): void {
        if ($value !== null || !($argumentDefinition?->isRequired() ?? false)) {
            return;
        }

        throw new HydrationException(
            sprintf(
                'The required prop "%s" for component "%s" resolved to null. A required prop must not be explicitly null.',
                $propName,
                $clientBaseName,
            ),
            1_788_300_001,
        );
    }

    /**
     * An object reaches the client as JSON: a `JsonSerializable` one as whatever it returns, any other one as its
     * public properties. An object without public properties (an Extbase model, a closure) would reach it as `{}`,
     * and an internal class such as `DateTime` as the internal shape of PHP, so both fail here, scoped to this one
     * component/prop. Non-object values pass through unchanged.
     */
    public function resolveClientValue(mixed $value, string $propName, string $clientBaseName): mixed
    {
        if (!is_object($value) || $value instanceof JsonSerializable || $value instanceof stdClass) {
            return $value;
        }

        if ((new ReflectionClass($value))->isInternal() || get_object_vars($value) === []) {
            throw new HydrationException(
                sprintf(
                    'The prop "%s" for component "%s" is a "%s" instance, which has no public properties the client ' .
                    'could use. Pass a plain value, such as a formatted date string, or an object with public ' .
                    'properties or one that implements JsonSerializable.',
                    $propName,
                    $clientBaseName,
                    $value::class,
                ),
                1_788_300_002,
            );
        }

        return $value;
    }
}
