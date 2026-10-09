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

    /**
     * The midnight of an ISO date in the time zone PHP runs in, as the W3C date-time
     * (`2025-06-15T00:00:00+02:00`) Extbase maps to a `DateTime` without any configuration. It has to be
     * the server's offset: Extbase converts any other to the server's time zone, which can move the date.
     */
    public static function toW3cMidnight(string $isoDate): string
    {
        return (new \DateTimeImmutable($isoDate))->format(\DateTimeInterface::W3C);
    }
}
