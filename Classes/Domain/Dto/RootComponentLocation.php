<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

/**
 * One root component {@see \Jramke\FluidPrimitives\Utility\ComponentEnumerator} found while walking
 * a collection's own template root paths.
 */
final readonly class RootComponentLocation
{
    public function __construct(
        /** PascalCase folder/primitive name, e.g. "FieldArray" - matches its own `<Name>.ts`/`<Name>.hydration.ts`. */
        public string $name,
        /** The name {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::getComponentDefinition()} accepts, e.g. "fieldArray.root". */
        public string $viewHelperName,
        /** Absolute path to the primitive's own folder (containing `Root.fluid.html`, and usually `<Name>.ts`). */
        public string $path,
    ) {}
}
