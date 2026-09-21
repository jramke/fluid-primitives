<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contracts;

/**
 * Implemented directly on a `ui:prop`-able PHP class you control (a `Domain/Dto/`) to declare the
 * TypeScript shape it serializes to on the client - consulted by both
 * {@see \Jramke\FluidPrimitives\Service\Component\ComponentHydrationCollector} (runtime) and
 * `ui:generate-hydration-types` (codegen), always before a plain `Pick` from the primitive's own
 * Props type or a scalar/enum mapping. For a type you don't control the class of (a vendor model),
 * register a {@see ClientPropConverterInterface} instead.
 */
interface ClientTypeAwareInterface
{
    /**
     * @return class-string A plain, never-instantiated, `#[TypeScript]`-attributed PHP class
     *   describing this value's JSON wire shape - reflected by spatie/typescript-transformer into
     *   real TypeScript, rather than hand-typed as a string with nothing checking it against what
     *   actually gets serialized.
     */
    public function getTsShapeClass(): string;
}
