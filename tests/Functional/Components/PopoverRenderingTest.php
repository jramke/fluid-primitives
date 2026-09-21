<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class PopoverRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersPopoverRootComponent(): void
    {
        $html = $this->renderTemplate('
            <primitives:popover.root>
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>Content</primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $this->assertStringContainsString('data-scope="popover"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('data-part="content"', $html);
    }

    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:popover.root>
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>Content</primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $this->assertMatchesRegularExpression('/data-part="content"[^>]*hidden/', $html);
        // Regression test: `defaultOpen` used to have no explicit default, so `context.defaultOpen`
        // was `null` rather than `false` when unset. TYPO3 Fluid's inline ternary shorthand
        // (`{x ? a : b}`) treats a bare `null` as truthy - unlike `f:if`, which correctly treats it
        // as falsy - so the `expanded` variable computed from it rendered a stray `data-expanded`
        // attribute on an otherwise-closed popover.
        $this->assertDoesNotMatchRegularExpression('/data-part="content"[^>]*data-expanded/', $html);
    }

    #[Test]
    public function rendersOpenStateWhenDefaultOpenIsTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:popover.root defaultOpen="{true}">
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>Content</primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $this->assertStringContainsString('data-state="open"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-part="content"[^>]*hidden/', $html);
    }

    #[Test]
    public function rendersEnglishCloseTriggerLabelByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:popover.root>
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>
                        Content
                        <primitives:popover.closeTrigger>Close</primitives:popover.closeTrigger>
                    </primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $this->assertStringContainsString('aria-label="Close"', $html);
    }

    #[Test]
    public function rendersGermanCloseTriggerLabelWhenLocaleIsGerman(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:popover.root>
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>
                        Content
                        <primitives:popover.closeTrigger>Close</primitives:popover.closeTrigger>
                    </primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $this->assertStringContainsString('aria-label="Schließen"', $html);
    }

    #[Test]
    public function registersInHydrationRegistry(): void
    {
        $this->renderTemplate('
            <primitives:popover.root modal="{true}">
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>Content</primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $popoverData = array_values($hydrationData['popover'])[0];

        $this->assertTrue($popoverData['props']['modal']);
    }
}
