<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PopoverRenderingTest extends FunctionalTestCase
{
    private const array PARTS = ['trigger', 'positioner', 'content', 'title', 'description', 'close-trigger'];

    #[Test]
    public function marksEveryPartWithTheRootIdOfItsPopover(): void
    {
        $html = $this->renderTemplate('
            <primitives:popover.root rootId="my-popover">
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>
                        <primitives:popover.title>Title</primitives:popover.title>
                        <primitives:popover.description>Description</primitives:popover.description>
                        <primitives:popover.closeTrigger>Close</primitives:popover.closeTrigger>
                    </primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        foreach (self::PARTS as $part) {
            $this->assertSame(['my-popover'], $this->valuesOf($html, 'data-popover-' . $part), $part);
        }
    }

    #[Test]
    public function rendersAClosedPopoverHiddenAndAnOpenOneVisible(): void
    {
        $popover = static fn(string $arguments): string => (
            '
            <primitives:popover.root ' . $arguments . '>
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>Content</primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        '
        );

        $closed = $this->renderTemplate($popover(''));
        $content = $this->elementWith($closed, 'data-popover-content');
        $this->assertTrue($content->has('hidden'));
        $this->assertSame('closed', $content->get('data-state'));
        // Regression test: `defaultOpen` used to have no explicit default, so `context.defaultOpen`
        // was `null` rather than `false` when unset. TYPO3 Fluid's inline ternary shorthand
        // (`{x ? a : b}`) treats a bare `null` as truthy - unlike `f:if`, which correctly treats it
        // as falsy - so the `expanded` variable computed from it rendered a stray `data-expanded`
        // attribute on an otherwise-closed popover.
        $this->assertFalse($content->has('data-expanded'));

        $open = $this->renderTemplate($popover('defaultOpen="{true}"'));
        $content = $this->elementWith($open, 'data-popover-content');
        $this->assertFalse($content->has('hidden'));
        $this->assertSame('open', $content->get('data-state'));
        $this->assertTrue($content->has('data-expanded'));
        $this->assertSame('open', $this->elementWith($open, 'data-popover-trigger')->get('data-state'));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: string}>
     */
    public static function closeTriggerLabels(): iterable
    {
        yield 'English by default' => [null, 'Close'];
        yield 'German for a German request' => ['de_DE', 'Schließen'];
    }

    #[Test]
    #[DataProvider('closeTriggerLabels')]
    public function labelsTheCloseTriggerInTheLanguageOfTheRequest(?string $locale, string $expectedLabel): void
    {
        if ($locale !== null) {
            $this->setRequestLocale($locale);
        }

        $html = $this->renderTemplate('
            <primitives:popover.root>
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>
                        <primitives:popover.closeTrigger>x</primitives:popover.closeTrigger>
                    </primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $this->assertSame($expectedLabel, $this->elementWith($html, 'data-popover-close-trigger')->get('aria-label'));
    }

    #[Test]
    public function shipsModalToTheClient(): void
    {
        $this->renderTemplate('
            <primitives:popover.root modal="{true}">
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>Content</primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>
        ');

        $popoverData = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['popover'])[0];

        $this->assertTrue($popoverData['props']['modal']);
    }
}
