<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

/**
 * Parses a raw HTML attribute string (e.g. `class="test" disabled`) into a plain associative array,
 * as accepted by {@see TagAttributes}'s constructor.
 */
final class TagAttributesStringParser
{
    public static function parse(string $attributesString): array
    {
        if ($attributesString === '') {
            return [];
        }

        $attributes = [];
        $parts = explode(' ', trim($attributesString));
        foreach ($parts as $part) {
            if (!str_contains($part, '=')) {
                $attributes[trim($part)] = true; // boolean attribute
                continue;
            }

            [$key, $value] = explode('=', $part, limit: 2);
            $attributes[trim($key)] = trim($value, characters: '"');
        }
        return $attributes;
    }
}
