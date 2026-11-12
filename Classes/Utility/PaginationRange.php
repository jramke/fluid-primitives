<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

/**
 * The pages a pagination shows, as `@zag-js/pagination` computes them on the client (`getRange()`
 * in `pagination.utils`), so the server renders the same list the machine takes over.
 */
final class PaginationRange
{
    /**
     * @return list<int|null> The page numbers in order, `null` for an ellipsis
     */
    public static function compute(int $page, int $totalPages, int $siblingCount, int $boundaryCount): array
    {
        if ($totalPages <= 0) {
            return [];
        }
        if ($totalPages === 1) {
            return [1];
        }

        $leftSibling = max($page - $siblingCount, 1);
        $rightSibling = min($page + $siblingCount, $totalPages);
        $totalPageNumbers = min(($siblingCount * 2) + 3 + ($boundaryCount * 2), $totalPages);

        if ($totalPages <= $totalPageNumbers) {
            return range(1, $totalPages);
        }

        $itemCount = $totalPageNumbers - 1 - $boundaryCount;
        $showLeftEllipsis = $leftSibling > ($boundaryCount + 2);
        $showRightEllipsis = $rightSibling < ($totalPages - $boundaryCount - 1);

        $pages = match (true) {
            !$showLeftEllipsis && $showRightEllipsis => [
                ...range(1, $itemCount),
                null,
                ...self::range($totalPages - $boundaryCount + 1, $totalPages),
            ],
            $showLeftEllipsis && !$showRightEllipsis => [
                ...self::range(1, $boundaryCount),
                null,
                ...range($totalPages - $itemCount + 1, $totalPages),
            ],
            $showLeftEllipsis => [
                ...self::range(1, $boundaryCount),
                null,
                ...range($leftSibling, $rightSibling),
                null,
                ...self::range($totalPages - $boundaryCount + 1, $totalPages),
            ],
            default => range(1, $totalPages),
        };

        return self::showSinglePages($pages, $totalPages);
    }

    /**
     * An ellipsis that would hide a single page shows that page instead.
     *
     * @param list<int|null> $pages
     * @return list<int|null>
     */
    private static function showSinglePages(array $pages, int $totalPages): array
    {
        foreach ($pages as $index => $entry) {
            if ($entry !== null) {
                continue;
            }
            $previous = $pages[$index - 1] ?? 0;
            $next = $pages[$index + 1] ?? ($totalPages + 1);
            if (($next - $previous) === 2) {
                $pages[$index] = $previous + 1;
            }
        }

        return $pages;
    }

    /**
     * `range()` that is empty when `$end` is before `$start`, as the JavaScript original, for a
     * `boundaryCount` of 0.
     *
     * @return list<int>
     */
    private static function range(int $start, int $end): array
    {
        return $end < $start ? [] : range($start, $end);
    }
}
