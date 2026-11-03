<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;

/**
 * What a `<component>.provider` registered: its root's resolved arguments and the context created from them.
 */
final readonly class ProvidedRoot
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public array $arguments,
        public ComponentContextInterface $context,
    ) {}
}
