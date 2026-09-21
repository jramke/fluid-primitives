<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

/**
 * Stands in for something like an Extbase model you don't control the class of - implements
 * neither `JsonSerializable` nor {@see \Jramke\FluidPrimitives\Contracts\ClientTypeAwareInterface};
 * only convertible via a registered {@see \Jramke\FluidPrimitives\Contracts\ClientPropConverterInterface}.
 */
final class ConvertibleLegacyObject
{
    public function __construct(
        public string $legacyValue = 'legacy',
    ) {}
}
