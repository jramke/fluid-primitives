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
        public string $baseName,
        /**
         * `$baseName`, camelCased (e.g. "fileUpload" for "file-upload") - the key
         * {@see \Jramke\FluidPrimitives\Service\ContextService}'s stack is stored under, distinct
         * from `$baseName` itself because that one's kebab-case form is also relied on for
         * `data-scope`/hydration/`ComponentPartIdUtility`'s override maps and must stay as-is.
         */
        public string $contextKey,
    ) {}
}
