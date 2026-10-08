<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

class DateUtility
{
    /**
     * The `Y-m-d` date of what a template, or a Field bound to an object, hands over: a
     * `DateTimeInterface`, or a date string - a date-time string keeps just its date. Anything
     * else gives `null`.
     */
    public static function toIsoDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $matches = [];

        return preg_match('/^\d{4}-\d{2}-\d{2}/', Typed::stringOrNull($date) ?? '', $matches) === 1
            ? $matches[0]
            : null;
    }
}
