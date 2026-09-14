<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

final class EnumUtility
{
    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (!is_array($value)) {
            return $value;
        }

        // Recursively unwrapping enums from an arbitrary array means each element is genuinely
        // mixed - narrower typing would defeat the point of this generic normalizer.
        // @mago-expect analysis:mixed-assignment
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }
}
