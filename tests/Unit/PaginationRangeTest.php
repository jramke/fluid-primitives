<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\PaginationRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PaginationRangeTest extends TestCase
{
    /**
     * `tests/Client/Pagination/pagination-range.test.ts` runs the same cases through the machine: the
     * pages the server renders are the pages the client takes over.
     *
     * @param list<int|null> $expected
     */
    #[Test]
    #[DataProvider('rangeCases')]
    public function computesThePagesTheMachineShows(
        int $page,
        int $totalPages,
        int $siblingCount,
        int $boundaryCount,
        array $expected,
    ): void {
        $this->assertSame($expected, PaginationRange::compute($page, $totalPages, $siblingCount, $boundaryCount));
    }

    /**
     * @return iterable<string, array{int, int, int, int, list<int|null>}>
     */
    public static function rangeCases(): iterable
    {
        $cases = json_decode(
            (string)file_get_contents(__DIR__ . '/../Fixtures/pagination-range.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($cases as $case) {
            yield $case['name'] => [
                $case['page'],
                $case['totalPages'],
                $case['siblingCount'],
                $case['boundaryCount'],
                $case['expected'],
            ];
        }
    }
}
