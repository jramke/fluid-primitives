<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Domain\Dto\ProvidedRoot;
use Jramke\FluidPrimitives\Exception\DuplicateProviderException;
use Jramke\FluidPrimitives\Exception\MissingProviderException;
use Jramke\FluidPrimitives\Utility\Typed;

/**
 * The roots a `<component>.provider` created ahead of their own render, by collection, component and
 * `id` (the `rootId` of the root). The root and its parts look them up from anywhere later in the request. Static like
 * {@see ReferencedRootRegistry}: `ui:context` reads it without a DI container.
 */
final class ProvidedRootRegistry
{
    /** @var array<string, ProvidedRoot> */
    private static array $provided = [];

    /**
     * @param string $collection The collection's class name, see `ComponentCollectionInterface::getNamespace()`.
     */
    public static function provide(string $collection, string $baseName, string $rootId, ProvidedRoot $root): void
    {
        $key = self::key($collection, $baseName, $rootId);
        if (array_key_exists($key, self::$provided)) {
            throw new DuplicateProviderException(
                sprintf('There already is a <%s.provider id="%s">. Each root has one provider.', $baseName, $rootId),
                1_789_100_002,
            );
        }

        self::$provided[$key] = $root;
    }

    public static function get(string $collection, string $baseName, string $rootId): ?ProvidedRoot
    {
        return self::$provided[self::key($collection, $baseName, $rootId)] ?? null;
    }

    /**
     * The provided root the `provider` argument of a root names, or `null` without one.
     *
     * @param array<string, mixed> $arguments
     */
    public static function forArguments(string $collection, string $baseName, array $arguments): ?ProvidedRoot
    {
        return self::get($collection, $baseName, Typed::string($arguments[Constants::PROP_PROVIDER] ?? null));
    }

    /**
     * The root one of `$collections` provided for `$rootId`, or an exception naming `$usedBy`.
     *
     * @param list<string> $collections
     */
    public static function require(array $collections, string $baseName, string $rootId, string $usedBy): ProvidedRoot
    {
        foreach ($collections as $collection) {
            $provided = self::get($collection, $baseName, $rootId);
            if ($provided instanceof ProvidedRoot) {
                return $provided;
            }
        }

        throw new MissingProviderException(
            sprintf(
                '%s references the provider "%s", but no <%s.provider id="%s"> was rendered before it. Render the provider first, or place the part inside its root.',
                $usedBy,
                $rootId,
                $baseName,
                $rootId,
            ),
            1_789_100_003,
        );
    }

    public static function clear(): void
    {
        self::$provided = [];
    }

    private static function key(string $collection, string $baseName, string $rootId): string
    {
        return $collection . ':' . $baseName . ':' . $rootId;
    }
}
