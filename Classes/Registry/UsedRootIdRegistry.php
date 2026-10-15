<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

/**
 * Explicit `rootId`s already taken by a root component this request, so
 * {@see \Jramke\FluidPrimitives\Service\Component\ComponentIdentityResolver} can reject a repeated one
 * instead of letting both roots silently share (and overwrite) their hydration data. Static like its
 * sibling {@see ReferencedRootRegistry}; a long-lived process rendering unrelated templates
 * (CLI, tests) must call {@see clear()} between them.
 */
final class UsedRootIdRegistry
{
    /** @var array<string, true> */
    private static array $used = [];

    public static function mark(string $clientBaseName, string $rootId): void
    {
        self::$used[$clientBaseName . ':' . $rootId] = true;
    }

    public static function isUsed(string $clientBaseName, string $rootId): bool
    {
        return self::$used[$clientBaseName . ':' . $rootId] ?? false;
    }

    public static function clear(): void
    {
        self::$used = [];
    }
}
