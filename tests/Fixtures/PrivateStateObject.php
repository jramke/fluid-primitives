<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

/**
 * An object that keeps all of its state private, like an Extbase model: JSON encodes it as `{}`.
 */
final readonly class PrivateStateObject
{
    public function __construct(
        private string $value = 'hidden',
    ) {}

    public function getValue(): string
    {
        return $this->value;
    }
}
