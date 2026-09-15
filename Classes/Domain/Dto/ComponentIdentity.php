<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

/**
 * Result of {@see ComponentIdentityResolver::resolve()}.
 */
final readonly class ComponentIdentity
{
    public function __construct(
        public bool $isRootComponent,
        public bool $isComposableComponent,
        public ?string $rootId,
        /**
         * The canonical, as-authored component name (e.g. "fileUpload") - used for context
         * storage/lookup, the `component.baseName` Fluid variable, and any same-component-type
         * comparison. Not kebab-case; see `$clientBaseName` for that.
         */
        public string $baseName,
        /**
         * `$baseName`, kebab-cased (e.g. "file-upload") - for the few things that genuinely need
         * it: `data-scope`/hydration keys and {@see \Jramke\FluidPrimitives\Utility\ComponentPartIdUtility}'s
         * override maps.
         */
        public string $clientBaseName,
    ) {}
}
