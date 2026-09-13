<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Model;

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
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $attributes[trim($key)] = trim($value, '"');
            } else {
                $attributes[trim($part)] = true; // boolean attribute
            }
        }
        return $attributes;
    }
}
