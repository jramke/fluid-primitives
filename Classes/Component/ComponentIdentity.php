<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

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
    ) {}
}
