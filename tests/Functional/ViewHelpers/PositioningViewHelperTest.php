<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\ViewHelperTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * `ui:positioning` builds the `positioning` of the floating components and refuses what the client would silently
 * ignore: an unknown placement, an unknown offset key or something that is not a number.
 */
final class PositioningViewHelperTest extends ViewHelperTestCase
{
    #[Test]
    public function keepsNumbersAsNumbersWhetherTheyComeAsNumbersOrNumericStrings(): void
    {
        $html = $this->renderTemplate(
            '<f:variable name="p" value="{ui:positioning(placement: \'top-end\', gutter: \'8.5\', shift: 3, offset: {mainAxis: 4, crossAxis: \'2\'}, flip: {0: \'bottom\'})}" />' .
            '{p.placement.value}|{p.gutter}|{p.shift}|{p.offset.mainAxis}|{p.offset.crossAxis}|{p.flip.0.value}',
        );

        $this->assertSame('top-end|8.5|3|4|2|bottom', $html);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'a placement that does not exist' => ['placement: \'middle\'', 'Unknown placement "middle"'];
        yield 'a placement in flip that does not exist' => [
            'flip: {0: \'bottom\', 1: \'nowhere\'}',
            'Unknown placement "nowhere"',
        ];
        yield 'a strategy that does not exist' => ['strategy: \'sticky\'', 'Unknown positioning strategy "sticky"'];
        yield 'an offset key that does not exist' => ['offset: {mainAxe: 4}', 'Unknown offset key "mainAxe"'];
        yield 'a gutter that is not a number' => [
            'gutter: \'wide\'',
            'The "gutter" argument of ui:positioning must be a number.',
        ];
    }

    #[Test]
    #[DataProvider('invalidArguments')]
    public function failsWhileRenderingInsteadOfLettingTheClientIgnoreIt(string $arguments, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->renderTemplate('<f:variable name="p" value="{ui:positioning(' . $arguments . ')}" />');
    }
}
