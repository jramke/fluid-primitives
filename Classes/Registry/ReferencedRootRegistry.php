<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

/**
 * Which root components had at least one `ui:ref`/`ui:template` rendered for them this request -
 * {@see \Jramke\FluidPrimitives\Service\Component\ComponentHydrationCollector} only registers a
 * root for hydration when it was referenced (or explicitly exposed). Static rather than a
 * container-backed singleton like its sibling registries: it's written from `RefViewHelper`, which
 * is exercised without a DI container.
 */
final class ReferencedRootRegistry
{
    /** @var array<string, true> */
    private static array $referenced = [];

    public static function mark(string $clientBaseName, string $rootId): void
    {
        self::$referenced[$clientBaseName . ':' . $rootId] = true;
    }

    public static function isReferenced(string $clientBaseName, string $rootId): bool
    {
        return self::$referenced[$clientBaseName . ':' . $rootId] ?? false;
    }

    public static function clear(): void
    {
        self::$referenced = [];
    }
}
