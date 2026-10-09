<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ScrollAreaRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function marksTheRootTheViewportAndTheContentWithTheSameRootId(): void
    {
        $html = $this->renderTemplate('
            <primitives:scrollArea.root rootId="my-scroll-area">
                <primitives:scrollArea.viewport>
                    <primitives:scrollArea.content>Scrollable content</primitives:scrollArea.content>
                </primitives:scrollArea.viewport>
            </primitives:scrollArea.root>
        ');

        foreach (['root', 'viewport', 'content'] as $part) {
            $this->assertSame(['my-scroll-area'], $this->valuesOf($html, 'data-scroll-area-' . $part), $part);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function orientations(): iterable
    {
        yield 'vertical by default' => ['', 'vertical', 'bottom: var(--corner-height)', 'height: var(--thumb-height);'];
        yield 'horizontal' => [
            'orientation="{f:constant(name: \'Jramke\FluidPrimitives\Enum\Orientation::Horizontal\')}"',
            'horizontal',
            'inset-inline-end: var(--corner-width)',
            'width: var(--thumb-width);',
        ];
    }

    #[Test]
    #[DataProvider('orientations')]
    public function givesTheScrollbarAndItsThumbTheirOrientationAndMatchingStyles(
        string $arguments,
        string $orientation,
        string $scrollbarStyle,
        string $thumbStyle,
    ): void {
        $html = $this->renderTemplate('
            <primitives:scrollArea.root>
                <primitives:scrollArea.scrollbar ' . $arguments . '>
                    <primitives:scrollArea.thumb />
                </primitives:scrollArea.scrollbar>
            </primitives:scrollArea.root>
        ');

        $scrollbar = $this->elementWith($html, 'data-scroll-area-scrollbar');
        $thumb = $this->elementWith($html, 'data-scroll-area-thumb');

        // the client reads data-orientation of both parts
        $this->assertSame($orientation, $scrollbar->get('data-orientation'));
        $this->assertSame($orientation, $thumb->get('data-orientation'));
        $this->assertStringContainsString($scrollbarStyle, $scrollbar->get('style') ?? '');
        $this->assertStringContainsString($thumbStyle, $thumb->get('style') ?? '');
    }
}
