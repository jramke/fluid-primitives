<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

/**
 * A primitive's own `<Name>Props`/`select.Props` TS type, as resolved by
 * {@see HydrationPropsSourceResolver} from its `<Name>.ts` class file - the `Pick` source for any
 * client prop not already handled by `ClientTypeAwareInterface`/a converter (see the plan's "Wire
 * type vs. machine type" precedence order).
 */
final readonly class HydrationPropsTypeSource
{
    public function __construct(
        /** The (possibly aliased) local name to reference in a generated `Pick<X, ...>`, e.g. `SelectProps`. */
        public string $typeName,
        /** The `import type { ... }` statement declaring {@see $typeName} in the generated file. */
        public string $tsImport,
        /** Raw source text searched for a prop name's presence - see HydrationPropsSourceResolver's own docblock. */
        public string $haystack,
    ) {}

    public function hasKey(string $propName): bool
    {
        return preg_match('/\b' . preg_quote($propName, delimiter: '/') . '\??\s*:/', $this->haystack) === 1;
    }
}
