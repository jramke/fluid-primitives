<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ScrollAreaRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersRootViewportAndContentWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:scrollArea.root>
                <primitives:scrollArea.viewport>
                    <primitives:scrollArea.content>Scrollable content</primitives:scrollArea.content>
                </primitives:scrollArea.viewport>
            </primitives:scrollArea.root>
        ');

        $this->assertStringContainsString('data-scope="scroll-area"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
        $this->assertStringContainsString('data-part="viewport"', $html);
        $this->assertStringContainsString('data-part="content"', $html);
        $this->assertStringContainsString('Scrollable content', $html);
    }

    #[Test]
    public function defaultsScrollbarOrientationToVertical(): void
    {
        $html = $this->renderTemplate('
            <primitives:scrollArea.root>
                <primitives:scrollArea.scrollbar>
                    <primitives:scrollArea.thumb />
                </primitives:scrollArea.scrollbar>
            </primitives:scrollArea.root>
        ');

        $this->assertStringContainsString('data-value="vertical"', $html);
        $this->assertMatchesRegularExpression(
            '/data-part="scrollbar"[^>]*style="[^"]*bottom: var\(--corner-height\)/',
            $html,
        );
    }

    #[Test]
    public function appliesHorizontalScrollbarStylesWhenOrientationIsHorizontal(): void
    {
        $html = $this->renderTemplate('
            <primitives:scrollArea.root>
                <primitives:scrollArea.scrollbar orientation="{f:constant(name: \'Jramke\FluidPrimitives\Enum\Orientation::Horizontal\')}">
                    <primitives:scrollArea.thumb />
                </primitives:scrollArea.scrollbar>
            </primitives:scrollArea.root>
        ');

        $this->assertStringContainsString('data-value="horizontal"', $html);
        $this->assertMatchesRegularExpression(
            '/data-part="scrollbar"[^>]*style="[^"]*inset-inline-end: var\(--corner-width\)/',
            $html,
        );
    }

    #[Test]
    public function thumbStylesMatchScrollbarOrientation(): void
    {
        $html = $this->renderTemplate('
            <primitives:scrollArea.root>
                <primitives:scrollArea.scrollbar orientation="{f:constant(name: \'Jramke\FluidPrimitives\Enum\Orientation::Horizontal\')}">
                    <primitives:scrollArea.thumb />
                </primitives:scrollArea.scrollbar>
            </primitives:scrollArea.root>
        ');

        $this->assertMatchesRegularExpression('/data-part="thumb"[^>]*style="width: var\(--thumb-width\);"/', $html);
    }
}
