<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Exception\HydrationException;
use JsonException;
use JsonSerializable;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * The null-safety and object-conversion rules {@see ComponentHydrationCollector} applies to each
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
     * public properties. A plain object is encoded once here, so one that cannot be encoded (a circular reference)
     * fails scoped to this one component/prop, rather than in the page-wide `HydrationScriptBuilder::build()` call,
     * where the `JsonException` would break hydration for every component on the page. Non-object values pass
     * through unchanged.
     */
    public function resolveClientValue(mixed $value, string $propName, string $clientBaseName): mixed
    {
        if (!is_object($value) || $value instanceof JsonSerializable) {
            return $value;
        }

        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HydrationException(
                sprintf(
                    'The prop "%s" for component "%s" is a "%s" instance that cannot be encoded as JSON (%s). ' .
                    'Implement JsonSerializable on it to control what the client receives.',
                    $propName,
                    $clientBaseName,
                    $value::class,
                    $exception->getMessage(),
                ),
                1_788_300_002,
                $exception,
            );
        }

        return $value;
    }
}
