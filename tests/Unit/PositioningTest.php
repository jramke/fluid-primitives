<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\Positioning;
use Jramke\FluidPrimitives\Enum\Placement;
use Jramke\FluidPrimitives\Enum\PositioningStrategy;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PositioningTest extends TestCase
{
    #[Test]
    public function sendsOnlyTheOptionsThatAreSetWithEnumsAsTheirValues(): void
    {
        $positioning = new Positioning(
            placement: Placement::RightStart,
            strategy: PositioningStrategy::Fixed,
            gutter: 12,
            offset: ['mainAxis' => 4],
            flip: [Placement::Bottom, Placement::Left],
            slide: false,
        );

        $this->assertSame(
            '{"placement":"right-start","strategy":"fixed","gutter":12,"offset":{"mainAxis":4},"flip":["bottom","left"],"slide":false}',
            json_encode($positioning, JSON_THROW_ON_ERROR),
        );
        // an empty object, not `[]`, would be a list for the client
        $this->assertSame('[]', json_encode(new Positioning(), JSON_THROW_ON_ERROR));
    }
}
