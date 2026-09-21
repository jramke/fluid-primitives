<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Contracts\ClientTypeAwareInterface;
use Jramke\FluidPrimitives\Registry\ClientPropConverterRegistry;
use JsonSerializable;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * The null-safety and object-conversion rules {@see ComponentHydrationCollector} applies to each
 * client-marked prop's resolved value, before it's placed into the JSON hydration payload - split
 * out into its own collaborator purely to keep the collector's own class-level complexity down.
 */
final readonly class ClientPropValueResolver
{
    public function __construct(
        private ClientPropConverterRegistry $converterRegistry,
    ) {}

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

        throw new \RuntimeException(
            sprintf(
                'The required prop "%s" for component "%s" resolved to null. A required prop must not be explicitly null.',
                $propName,
                $clientBaseName,
            ),
            1_788_300_001,
        );
    }

    /**
     * Resolves how a client-marked object prop value actually reaches the JSON hydration payload -
     * left untouched if it implements {@see ClientTypeAwareInterface} (presumed to also handle its
     * own JSON encoding, e.g. via `JsonSerializable`, same as {@see \Jramke\FluidPrimitives\Domain\Dto\ListCollection}),
     * converted via a matching {@see \Jramke\FluidPrimitives\Contracts\ClientPropConverterInterface}
     * if the registry finds one, or - for anything else - left untouched only if it's at least
     * `JsonSerializable` itself (today's pre-existing, "works by accident" behavior). Anything else
     * throws immediately, scoped to this one component/prop, rather than reaching the page-wide
     * `HydrationScriptBuilder::toJson()` call, where a `JsonException` would break hydration for
     * every component on the page, not just the offending one. Non-object values pass through
     * unchanged - this is only about the object-prop hole described in the plan.
     */
    public function resolveClientValue(
        mixed $value,
        string $propName,
        string $clientBaseName,
        ?ArgumentDefinition $argumentDefinition,
    ): mixed {
        if (!is_object($value)) {
            return $value;
        }

        if ($value instanceof ClientTypeAwareInterface) {
            return $value;
        }

        // $argumentDefinition is only null for a prop the candidate's own $argumentDefinitions map
        // doesn't (any longer) carry an entry for - defensive, since every name iterated here comes
        // from $candidate->propsMarkedForClient, itself built from that same map. Converters need a
        // real ArgumentDefinition to match against, so this case skips straight to the
        // JsonSerializable fallback below rather than fabricating one.
        $converter = $argumentDefinition instanceof ArgumentDefinition
            ? $this->converterRegistry->findFor($value, $argumentDefinition)
            : null;
        if ($converter !== null) {
            return $converter->convert($value);
        }

        if ($value instanceof JsonSerializable) {
            return $value;
        }

        throw new \RuntimeException(
            sprintf(
                'The prop "%s" for component "%s" is a "%s" instance, which is neither JSON-serializable ' .
                'nor convertible for client hydration. Implement ClientTypeAwareInterface on it, or ' .
                'register a ClientPropConverterInterface for it.',
                $propName,
                $clientBaseName,
                $value::class,
            ),
            1_788_300_002,
        );
    }
}
