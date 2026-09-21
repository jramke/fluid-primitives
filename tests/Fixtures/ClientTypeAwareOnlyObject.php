<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

use Jramke\FluidPrimitives\Contracts\ClientTypeAwareInterface;

/**
 * Implements {@see ClientTypeAwareInterface} only - deliberately not `JsonSerializable` - to prove
 * {@see \Jramke\FluidPrimitives\Service\Component\ClientPropValueResolver} checks
 * `ClientTypeAwareInterface` on its own terms rather than as a side effect of a `JsonSerializable`
 * fallback check.
 */
final class ClientTypeAwareOnlyObject implements ClientTypeAwareInterface
{
    public function getTsType(): string
    {
        return 'ClientTypeAwareOnlyObjectData';
    }

    public function getTsImport(): ?string
    {
        return null;
    }
}
