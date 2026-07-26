<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

// @mago-expect lint:too-many-methods
final class AccordionRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersAccordionRootWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger 1</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('data-scope="accordion"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function generatesUniqueRootIdForHydration(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertMatchesRegularExpression('/data-hydrate-accordion="[^"]+"/', $html);
    }

    #[Test]
    public function rendersAccordionItemsWithValueAttribute(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="my-unique-value">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('data-part="item"', $html);
        $this->assertStringContainsString('data-value="my-unique-value"', $html);
    }

    #[Test]
    public function rendersAsButtonElement(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Click me</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('data-part="item-trigger"', $html);
    }

    #[Test]
    public function hasAriaDisabledAttribute(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('aria-disabled="false"', $html);
    }

    #[Test]
    public function rendersDisabledStateCorrectly(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1" disabled="{true}">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('data-disabled', $html);
    }

    #[Test]
    public function rendersContentContainerWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>My Content Here</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('data-part="item-content"', $html);
        $this->assertStringContainsString('My Content Here', $html);
    }

    #[Test]
    public function rendersMultipleAccordionItems(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="first">
                    <primitives:accordion.itemTrigger>First Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>First Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
                <primitives:accordion.item value="second">
                    <primitives:accordion.itemTrigger>Second Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Second Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('data-value="first"', $html);
        $this->assertStringContainsString('data-value="second"', $html);
        $this->assertStringContainsString('First Trigger', $html);
        $this->assertStringContainsString('Second Trigger', $html);
    }

    #[Test]
    public function appliesCustomClassToRoot(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root class="my-custom-class">
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('class="my-custom-class"', $html);
    }

    #[Test]
    public function passesOrientationPropThroughDataAttribute(): void
    {
        $html = $this->renderTemplate(<<<'FLUID'
            <primitives:accordion.root orientation="{f:constant(name: 'Jramke\FluidPrimitives\Enum\Orientation::Horizontal')}">
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        FLUID);

        $this->assertStringContainsString('data-orientation="horizontal"', $html);
    }

    #[Test]
    public function registersComponentInHydrationRegistry(): void
    {
        $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertArrayHasKey('accordion', $hydrationData);
        $this->assertIsArray($hydrationData['accordion']);
        $this->assertCount(1, $hydrationData['accordion']);
    }

    #[Test]
    public function includesClientPropsInHydrationData(): void
    {
        $this->renderTemplate('
            <primitives:accordion.root multiple="{true}" collapsible="{true}">
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $accordionData = array_values($hydrationData['accordion'])[0];

        $this->assertArrayHasKey('multiple', $accordionData['props']);
        $this->assertTrue($accordionData['props']['multiple']);
        $this->assertArrayHasKey('collapsible', $accordionData['props']);
        $this->assertTrue($accordionData['props']['collapsible']);
    }

    #[Test]
    public function includesDefaultValueInHydrationData(): void
    {
        $this->renderTemplate('
            <primitives:accordion.root defaultValue="{0: \'item-1\'}">
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $accordionData = array_values($hydrationData['accordion'])[0];

        $this->assertArrayHasKey('defaultValue', $accordionData['props']);
        $this->assertSame(['item-1'], $accordionData['props']['defaultValue']);
    }

    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root>
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('data-state="closed"', $html);
    }

    #[Test]
    public function rendersOpenStateWhenItemIsInDefaultValueArray(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root defaultValue="{0: \'item-1\', 1: \'item-2\'}">
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger 1</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                </primitives:accordion.item>
                <primitives:accordion.item value="item-2">
                    <primitives:accordion.itemTrigger>Trigger 2</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 2</primitives:accordion.itemContent>
                </primitives:accordion.item>
                <primitives:accordion.item value="item-3">
                    <primitives:accordion.itemTrigger>Trigger 3</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 3</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        // 2 items x 3 parts = 6 open
        $this->assertSame(6, preg_match_all('/data-state="open"/', $html));
        // 1 item x 3 parts = 3 closed
        $this->assertSame(3, preg_match_all('/data-state="closed"/', $html));
    }

    #[Test]
    public function inheritsDisabledStateFromRootToAllItems(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root disabled="{true}">
                <primitives:accordion.item value="item-1">
                    <primitives:accordion.itemTrigger>Trigger 1</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                </primitives:accordion.item>
                <primitives:accordion.item value="item-2">
                    <primitives:accordion.itemTrigger>Trigger 2</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 2</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertSame(2, preg_match_all('/aria-disabled="true"/', $html));
    }

    #[Test]
    public function itemLevelDisabledOverridesRootDisabled(): void
    {
        $html = $this->renderTemplate('
            <primitives:accordion.root disabled="{true}">
                <primitives:accordion.item value="item-1" disabled="{false}">
                    <primitives:accordion.itemTrigger>Enabled Item</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                </primitives:accordion.item>
                <primitives:accordion.item value="item-2">
                    <primitives:accordion.itemTrigger>Disabled Item</primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>Content 2</primitives:accordion.itemContent>
                </primitives:accordion.item>
            </primitives:accordion.root>
        ');

        $this->assertStringContainsString('aria-disabled="false"', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }
}
