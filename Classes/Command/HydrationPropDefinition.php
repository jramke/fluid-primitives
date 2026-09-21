<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

/**
 * One client-facing prop {@see GenerateHydrationTypesCommand} resolved for a primitive's own
 * generated `<Name>HydrationProps` type - either from a `ui:prop client="{true}"` declaration or
 * from a `#[ExposeToClient]` context method, normalized to the same shape either way.
 */
final readonly class HydrationPropDefinition
{
    public function __construct(
        public string $name,
        public bool $required,
        /** The TS type expression for this prop's value, e.g. `'boolean'` or `'ListCollectionData'`. */
        public string $tsType,
        /** An `import type { ... }` statement {@see $tsType} needs, or null if none does. */
        public ?string $tsImport,
        /**
         * Whether {@see $tsType} should be `Pick`ed from the primitive's own Props type rather than
         * used as a standalone type - see the plan's "Wire type vs. machine type" precedence order.
         */
        public bool $pickFromPropsType,
    ) {}
}
