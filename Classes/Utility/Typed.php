<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Narrows `mixed` values pulled from untyped sources - primarily
 * {@see \Jramke\FluidPrimitives\Contexts\AbstractComponentContext::get()}, since Fluid template
 * variables carry no static type - into a concrete scalar/array type at the call site, instead of
 * scattering ad-hoc `is_string()`/cast checks across Context and consumer classes.
 */
final class Typed
{
    public static function int(mixed $value, int $default = 0): int
    {
        return self::intOrNull($value) ?? $default;
    }

    // @mago-expect lint:halstead
    public static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if ((is_string($value) || is_float($value)) && MathUtility::canBeInterpretedAsInteger($value)) {
            return (int)$value;
        }

        return null;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return self::floatOrNull($value) ?? $default;
    }

    // @mago-expect lint:halstead
    public static function floatOrNull(mixed $value): ?float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float)$value;
        }

        if (is_string($value) && MathUtility::canBeInterpretedAsFloat($value)) {
            return (float)$value;
        }

        return null;
    }

    public static function string(mixed $value, string $default = ''): string
    {
        return self::stringOrNull($value) ?? $default;
    }

    public static function stringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return null;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        return self::boolOrNull($value) ?? $default;
    }

    public static function boolOrNull(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool)$value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return match ($normalized) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => null,
            };
        }

        return null;
    }

    /**
     * @return array<mixed>|null
     */
    public static function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }
}
