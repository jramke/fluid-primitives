<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\TocItem;
use Jramke\FluidPrimitives\Exception\InvalidArgumentException;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TocItemTest extends TestCase
{
    #[Test]
    public function buildsFromAnArrayAndSerializesOnlyWhatTheClientNeeds(): void
    {
        $item = TocItem::fromArray(['value' => 'delivery', 'depth' => '3', 'label' => 'Delivery', 'text' => 'ignored']);

        $this->assertSame(['delivery', 3, 'Delivery'], [$item->value, $item->depth, $item->label]);
        $this->assertSame(['value' => 'delivery', 'depth' => 3], $item->jsonSerialize());
    }

    #[Test]
    public function rejectsAnItemWithoutValueOrWithoutAPositiveIntegerDepth(): void
    {
        $invalid = [
            'no value' => fn() => TocItem::fromArray(['depth' => 2]),
            'no depth' => fn() => TocItem::fromArray(['value' => 'a']),
            'depth that is not a number' => fn() => TocItem::fromArray(['value' => 'a', 'depth' => 'h2']),
            'depth below 1' => fn() => new TocItem('a', 0),
        ];

        foreach ($invalid as $case => $create) {
            try {
                $create();
                $this->fail($case . ' did not throw');
            } catch (InvalidArgumentException $exception) {
                $this->assertGreaterThan(0, $exception->getCode(), $case);
            }
        }
    }
}
