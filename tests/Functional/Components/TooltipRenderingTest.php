<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TooltipRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersAClosedTooltipHiddenAndAnOpenOneVisible(): void
    {
        $tooltip = static fn(string $arguments): string => (
            '
            <primitives:tooltip.root rootId="my-tooltip" ' . $arguments . '>
                <primitives:tooltip.trigger>Hover me</primitives:tooltip.trigger>
                <primitives:tooltip.positioner>
                    <primitives:tooltip.content>Tooltip text</primitives:tooltip.content>
                </primitives:tooltip.positioner>
            </primitives:tooltip.root>
        '
        );

        // Regression test: `defaultOpen` used to have no explicit default, so `context.defaultOpen`
        // was `null` rather than `false` when unset. TYPO3 Fluid's inline ternary shorthand
        // (`{x ? a : b}`) treats a bare `null` as truthy - unlike `f:if`, which correctly treats it
        // as falsy - so the trigger/content rendered `data-state="open"` (and a stray
        // `data-expanded` on the trigger) by default.
        $closed = $this->renderTemplate($tooltip(''));
        $trigger = $this->elementWith($closed, 'data-tooltip-trigger');
        $content = $this->elementWith($closed, 'data-tooltip-content');
        $this->assertSame('my-tooltip', $trigger->get('data-tooltip-trigger'));
        $this->assertSame('my-tooltip', $content->get('data-tooltip-content'));
        $this->assertSame(
            'my-tooltip',
            $this->elementWith($closed, 'data-tooltip-positioner')->get('data-tooltip-positioner'),
        );
        $this->assertTrue($content->has('hidden'));
        $this->assertSame('closed', $content->get('data-state'));
        $this->assertSame('closed', $trigger->get('data-state'));
        $this->assertFalse($trigger->has('data-expanded'));

        $open = $this->renderTemplate($tooltip('defaultOpen="{true}"'));
        $content = $this->elementWith($open, 'data-tooltip-content');
        $this->assertFalse($content->has('hidden'));
        $this->assertSame('open', $content->get('data-state'));
        $this->assertSame('open', $this->elementWith($open, 'data-tooltip-trigger')->get('data-state'));
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

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $tooltipData = array_values($hydrationData['tooltip'])[0];

        $this->assertSame(800, $tooltipData['props']['openDelay']);
        $this->assertSame(200, $tooltipData['props']['closeDelay']);
    }
}
