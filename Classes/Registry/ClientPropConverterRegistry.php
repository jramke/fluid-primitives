<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Jramke\FluidPrimitives\Contracts\ClientPropConverterInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Collects every {@see ClientPropConverterInterface} tagged service (auto-tagged via the
 * `_instanceof` block in `Configuration/Services.yaml`) and finds the one matching a given
 * client-marked object prop value - unlike {@see HydrationRegistry}/{@see PortalRegistry}, this
 * holds no per-request state of its own, so it's a plain DI-collected service rather than a
 * `getInstance()` singleton.
 */
final readonly class ClientPropConverterRegistry
{
    /**
     * @param iterable<ClientPropConverterInterface> $converters
     */
    public function __construct(
        private iterable $converters,
    ) {}

    public function findFor(mixed $value, ArgumentDefinition $definition): ?ClientPropConverterInterface
    {
        foreach ($this->converters as $converter) {
            if ($converter->supports($value, $definition)) {
                return $converter;
            }
        }

        return null;
    }
}
