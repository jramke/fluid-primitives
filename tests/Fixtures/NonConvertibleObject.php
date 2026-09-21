<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

/**
 * A plain object that implements neither {@see \Jramke\FluidPrimitives\Contracts\ClientTypeAwareInterface}
 * nor `JsonSerializable`, and that no {@see \Jramke\FluidPrimitives\Contracts\ClientPropConverterInterface}
 * is registered for - stands in for something like an unconverted Extbase model passed as a
 * `client="{true}"` prop value, to exercise {@see \Jramke\FluidPrimitives\Service\Component\ClientPropValueResolver}'s
 * fail-fast behavior.
 */
final class NonConvertibleObject
{
    public function __construct(
        public string $value = 'irrelevant',
    ) {}
}
