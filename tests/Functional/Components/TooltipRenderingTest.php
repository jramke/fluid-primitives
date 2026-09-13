<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TooltipRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersTooltipRootComponent(): void
    {
        $html = $this->renderTemplate('
            <primitives:tooltip.root>
                <primitives:tooltip.trigger>Hover me</primitives:tooltip.trigger>
                <primitives:tooltip.positioner>
                    <primitives:tooltip.content>Tooltip text</primitives:tooltip.content>
                </primitives:tooltip.positioner>
            </primitives:tooltip.root>
        ');

        $this->assertStringContainsString('data-scope="tooltip"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('data-part="content"', $html);
    }

    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        // Regression test: `defaultOpen` used to have no explicit default, so `context.defaultOpen`
        // was `null` rather than `false` when unset. TYPO3 Fluid's inline ternary shorthand
        // (`{x ? a : b}`) treats a bare `null` as truthy - unlike `f:if`, which correctly treats it
        // as falsy - so the trigger/content rendered `data-state="open"` (and a stray
        // `data-expanded` on the trigger) by default.
        $html = $this->renderTemplate('
            <primitives:tooltip.root>
                <primitives:tooltip.trigger>Hover me</primitives:tooltip.trigger>
                <primitives:tooltip.positioner>
                    <primitives:tooltip.content>Tooltip text</primitives:tooltip.content>
                </primitives:tooltip.positioner>
            </primitives:tooltip.root>
        ');

        $this->assertMatchesRegularExpression('/data-part="content"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-part="content"[^>]*data-state="closed"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-part="trigger"[^>]*data-expanded/', $html);
    }

    #[Test]
    public function rendersOpenStateWhenDefaultOpenIsTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:tooltip.root defaultOpen="{true}">
                <primitives:tooltip.trigger>Hover me</primitives:tooltip.trigger>
                <primitives:tooltip.positioner>
                    <primitives:tooltip.content>Tooltip text</primitives:tooltip.content>
                </primitives:tooltip.positioner>
            </primitives:tooltip.root>
        ');

        $this->assertDoesNotMatchRegularExpression('/data-part="content"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-part="trigger"[^>]*data-state="open"/', $html);
    }

    #[Test]
    public function registersConfiguredDelaysInHydrationRegistry(): void
    {
        $this->renderTemplate('
            <primitives:tooltip.root openDelay="800" closeDelay="200">
                <primitives:tooltip.trigger>Hover me</primitives:tooltip.trigger>
                <primitives:tooltip.positioner>
                    <primitives:tooltip.content>Tooltip text</primitives:tooltip.content>
                </primitives:tooltip.positioner>
            </primitives:tooltip.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $tooltipData = array_values($hydrationData['tooltip'])[0];

        $this->assertSame(800, $tooltipData['props']['openDelay']);
        $this->assertSame(200, $tooltipData['props']['closeDelay']);
    }
}
