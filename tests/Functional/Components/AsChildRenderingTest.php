<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class AsChildRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersComponentTemplateElementWhenAsChildFalse(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{false}">Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('Open', $html);
    }

    #[Test]
    public function rendersChildElementWithComponentAttributesWhenAsChildTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <a href="/some-link">Open Dialog</a>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<a', $html);
        $this->assertStringContainsString('href="/some-link"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('data-scope="dialog"', $html);
        $this->assertStringContainsString('Open Dialog', $html);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-part="trigger"/', $html);
    }

    #[Test]
    public function preservesChildElementAttributesWhenSpreading(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <button type="submit" class="my-custom-class" data-custom="value">Open</button>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('class="my-custom-class"', $html);
        $this->assertStringContainsString('data-custom="value"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('data-scope="dialog"', $html);
    }

    #[Test]
    public function childAttributesTakePrecedenceOverComponentAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <button class="child-class">Open</button>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('class="child-class"', $html);
    }

    #[Test]
    public function worksWithDivElements(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <div role="button" tabindex="0">Clickable Div</div>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<div', $html);
        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function worksWithSpanElements(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <span class="trigger-span">Click me</span>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<span', $html);
        $this->assertStringContainsString('class="trigger-span"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function worksWithCustomDataAttributesOnElements(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <button data-variant="primary" data-size="large">Custom Button</button>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('data-variant="primary"', $html);
        $this->assertStringContainsString('data-size="large"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function spreadsAttributesToCustomTriggerElementInAccordion(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger asChild="{true}">
                        <div class="custom-accordion-trigger">
                            <span>Toggle Section</span>
                            <svg class="icon"></svg>
                        </div>
                    </primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('<div', $html);
        $this->assertStringContainsString('class="custom-accordion-trigger"', $html);
        $this->assertStringContainsString('data-part="item-trigger"', $html);
        $this->assertStringContainsString('Toggle Section', $html);
    }

    #[Test]
    public function spreadsAttributesToCustomCloseElement(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>
                    <primitives:dialog.closeTrigger asChild="{true}">
                        <span class="close-icon" aria-label="Close">×</span>
                    </primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<span', $html);
        $this->assertStringContainsString('class="close-icon"', $html);
        $this->assertStringContainsString('aria-label="Close"', $html);
        $this->assertStringContainsString('data-part="close-trigger"', $html);
    }

    #[Test]
    public function correctlyRegistersHydrationDataWhenUsingAsChild(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="as-child-dialog">
                <primitives:dialog.trigger asChild="{true}">
                    <button class="custom-btn">Open</button>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertArrayHasKey('dialog', $hydrationData);
        $this->assertArrayHasKey('as-child-dialog', $hydrationData['dialog']);
        $this->assertStringContainsString('data-hydrate-dialog="as-child-dialog"', $html);
    }

    #[Test]
    public function worksWithMultipleAsChildComponentsInSameDialog(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="multi-aschild">
                <primitives:dialog.trigger asChild="{true}">
                    <a href="#" class="open-link">Open</a>
                </primitives:dialog.trigger>
                <primitives:dialog.content>
                    <p>Dialog content here</p>
                    <primitives:dialog.closeTrigger asChild="{true}">
                        <a href="#" class="close-link">Close</a>
                    </primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('class="open-link"', $html);
        $this->assertStringContainsString('class="close-link"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('data-part="close-trigger"', $html);

        preg_match_all('/data-hydrate-dialog="multi-aschild"/', $html, $matches);
        $this->assertGreaterThanOrEqual(3, count($matches[0]));
    }

    #[Test]
    public function handlesBooleanAttributesOnChildElement(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <button disabled autofocus>Open</button>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('autofocus', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function handlesEmptyChildContentGracefully(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <button></button>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function handlesSelfClosingChildElements(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <input type="button" value="Open" />
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('value="Open"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function handlesChildWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}">
                    <button data-testid="dialog-trigger" data-analytics="open-dialog">Open</button>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-testid="dialog-trigger"', $html);
        $this->assertStringContainsString('data-analytics="open-dialog"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('data-scope="dialog"', $html);
    }
}
