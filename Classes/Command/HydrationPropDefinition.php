<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;

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
        /**
         * The real spatie node for this prop's own value - a scalar/union/enum literal
         * ({@see HydrationValueTypeResolver::mapScalarOrEnumType()}) or a
         * `TypeScriptReference::referencingPhpClass()` pointing at a
         * {@see \Jramke\FluidPrimitives\Contracts\ClientTypeAwareInterface}/converter shape class.
         * Null exactly when {@see $pickFromPropsType} is true - the value is `Pick`ed from the
         * primitive's own Props type instead of carrying its own node here.
         */
        public ?TypeScriptNode $tsType,
        /**
         * Whether {@see $tsType} should be `Pick`ed from the primitive's own Props type rather than
         * used as a standalone type - see the plan's "Wire type vs. machine type" precedence order.
         */
        public bool $pickFromPropsType,
    ) {}
}
