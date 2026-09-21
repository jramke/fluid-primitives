<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

use Jramke\FluidPrimitives\Contracts\ClientPropConverterInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Converts {@see ConvertibleLegacyObject} for client hydration - matches by exact class, per this
 * interface's own "never duck-type" guidance.
 */
final class LegacyObjectConverter implements ClientPropConverterInterface
{
    public function supports(mixed $value, ArgumentDefinition $definition): bool
    {
        return $value instanceof ConvertibleLegacyObject;
    }

    public function convert(mixed $value): mixed
    {
        return ['legacyValue' => $value->legacyValue];
    }

    public function getTsType(): string
    {
        return 'ConvertedLegacyObjectData';
    }

    public function getTsImport(): ?string
    {
        return null;
    }
}
