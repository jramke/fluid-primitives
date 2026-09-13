<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

/**
 * Result of {@see ComponentArgumentResolver::resolve()}.
 */
final readonly class ResolvedComponentArguments
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $additionalArguments
     * @param array<string, true> $propsMarkedForClient
     * @param array<string, true> $propsMarkedForContext
     */
    public function __construct(
        public array $arguments,
        public array $additionalArguments,
        public array $propsMarkedForClient,
        public array $propsMarkedForContext,
    ) {}
}
