<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\Positioning;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PositioningTest extends TestCase
{
    #[Test]
    public function buildsFromTheArrayAFluidTemplateGivesAndShipsOnlyWhatWasSet(): void
    {
        $positioning = Positioning::fromArgument([
            'placement' => 'bottom-end',
            // what an inline `{gutter: '8'}` or a `'{true}'` default arrives as
            'gutter' => '8',
            'shift' => '2.5',
            'sameWidth' => 'true',
            'flip' => ['top', 'bottom'],
            'offset' => ['mainAxis' => 4],
            'slide' => null,
            'boundary' => 'clipping-ancestors',
            'listeners' => ['ancestorScroll' => false],
        ]);

        // in the order of the constructor, not of the template, and without the null `slide`
        $this->assertSame(
            [
                'placement' => 'bottom-end',
                'offset' => ['mainAxis' => 4],
                'gutter' => 8,
                'shift' => 2.5,
                'flip' => ['top', 'bottom'],
                'sameWidth' => true,
                'boundary' => 'clipping-ancestors',
                'listeners' => ['ancestorScroll' => false],
            ],
            $positioning->jsonSerialize(),
        );
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'a misspelled option' => [['placment' => 'top'], 'Unknown positioning option "placment"'];
        yield 'an unknown placement' => [
            ['placement' => 'middle'],
            'The positioning option "placement" must be one of',
        ];
        yield 'an unknown flip placement' => [
            ['flip' => ['top', 'nowhere']],
            'The positioning option "flip" must be one of',
        ];
        yield 'an unknown strategy' => [['strategy' => 'sticky'], 'The positioning option "strategy" must be one of'];
        yield 'a number that is none' => [['gutter' => 'wide'], 'The positioning option "gutter" must be a number'];
        yield 'a boolean that is none' => [
            ['sameWidth' => 'maybe'],
            'The positioning option "sameWidth" must be a boolean',
        ];
        yield 'a boundary that is not a string name' => [
            ['boundary' => 'viewport'],
            'The positioning option "boundary" must be one of',
        ];
        yield 'an unknown listeners key' => [
            ['listeners' => ['scroll' => true]],
            'The positioning option "listeners" takes',
        ];
        yield 'an unknown offset key' => [
            ['offset' => ['main' => 1]],
            'The positioning option "offset" takes mainAxis, crossAxis',
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('invalidOptions')]
    public function rejectsOptionsZagWouldSilentlyIgnore(array $options, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Positioning::fromArgument($options);
    }
}
