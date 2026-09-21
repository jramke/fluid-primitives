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
        /**
         * Field names known - from the class file's own source text, not a filesystem lookup - to
         * belong only to a local intersection's own extension, not to {@see $typeName} itself (e.g.
         * FileUpload's own `existingFilesCount` in `type FileUploadPrimitiveProps = fileUpload.Props
         * & { existingFilesCount?: number }`). `Pick<X, K>`ing one of these would be wrong in a way
         * `npm run types` doesn't reliably catch through a bundled `.d.ts` consumer (see
         * {@see HydrationPropsSourceResolver}'s own docblock) - almost always empty; only
         * `HydrationPropsSourceResolver::resolveLocalIntersectionType()` ever populates it.
         *
         * @var list<string>
         */
        public array $excludedKeys = [],
    ) {}
}
