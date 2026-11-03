<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Jramke\FluidPrimitives\Exception\HydrationException;

/**
 * The `rootId` of every root rendered in this request, hydrated or server-only. Keyed without the
 * collection: two roots of the same component share their part attributes (`data-dialog-trigger="<rootId>"`)
 * whatever collection rendered them.
 */
final class RenderedRootRegistry
{
    /** @var array<string, true> */
    private static array $rendered = [];

    public static function claim(string $clientBaseName, string $rootId): void
    {
        $key = $clientBaseName . ':' . $rootId;
        if (array_key_exists($key, self::$rendered)) {
            throw new HydrationException(
                sprintf('The rootId "%s" is already used by another <%s> component.', $rootId, $clientBaseName),
                1_788_200_003,
            );
        }

        self::$rendered[$key] = true;
    }

    public static function clear(): void
    {
        self::$rendered = [];
    }
}
