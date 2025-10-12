<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

class PortalRegistry
{
    private static array $registry = [];

    public static function add(string $name, string $html): void
    {
        self::$registry[$name][] = $html;
    }

    public static function getAll(): array
    {
        return self::$registry;
    }

    public static function getAllByName(string $name): array
    {
        return self::$registry[$name] ?? [];
    }

    public static function clearByName(string $name): void
    {
        unset(self::$registry[$name]);
    }

    public static function clearAll(): void
    {
        self::$registry = [];
    }
}
