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
     * The TS type name (or an inline literal, e.g. `'{ items: unknown[] }'`) this value serializes
     * to on the client.
     */
    public function getTsType(): string;

    /**
     * An `import type { ... }` statement for {@see getTsType()}'s type, or null when it's an inline
     * literal needing none.
     */
    public function getTsImport(): ?string;
}
