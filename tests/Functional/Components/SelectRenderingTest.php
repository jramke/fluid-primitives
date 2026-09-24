<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SelectRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersWithCollectionOmittedEntirely(): void
    {
        // Regression test: SelectContext::getCollection() was `protected`, which crashed with a
        // visibility error the moment Fluid's own property-path resolution (context.collection.items
        // in HiddenSelect.html) tried to read it while genuinely null (collection not provided at all).
        $html = $this->renderTemplate('
            <primitives:select.root>
                <primitives:select.hiddenSelect />
            </primitives:select.root>
        ');

        $this->assertStringContainsString('data-scope="select"', $html);
    }

    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        // Regression test: `defaultOpen` used to have no explicit default, so `context.defaultOpen`
        // was `null` rather than `false` when unset. TYPO3 Fluid's inline ternary shorthand
        // (`{x ? a : b}`) treats a bare `null` as truthy - unlike `f:if`, which correctly treats it
        // as falsy - so the content/control/indicator/trigger parts rendered `data-state="open"` by
        // default.
        $collection = new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
        ]);

        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}">
                <primitives:select.control>
                    <primitives:select.trigger>Select an option</primitives:select.trigger>
                </primitives:select.control>
            </primitives:select.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-state="closed"', $html);
        $this->assertStringNotContainsString('data-state="open"', $html);
    }

    #[Test]
    public function rendersSelectRootWithDataAttributes(): void
    {
        $collection = new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
            ['value' => 'opt-2', 'label' => 'Option 2'],
        ]);

        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}">
                <primitives:select.control>
                    <primitives:select.trigger>Select an option</primitives:select.trigger>
                </primitives:select.control>
            </primitives:select.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('data-scope="select"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersTriggerAsComboboxButton(): void
    {
        $collection = new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
        ]);

        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}">
                <primitives:select.control>
                    <primitives:select.trigger>Select</primitives:select.trigger>
                </primitives:select.control>
            </primitives:select.root>
        ', ['collection' => $collection]);

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-haspopup="listbox"', $html);
    }

    #[Test]
    public function normalizesStringDefaultValueToArrayInHydrationData(): void
    {
        $collection = new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
            ['value' => 'opt-2', 'label' => 'Option 2'],
        ]);

        $this->renderTemplate('
            <primitives:select.root collection="{collection}" defaultValue="opt-1">
                <primitives:select.control>
                    <primitives:select.trigger>Select</primitives:select.trigger>
                </primitives:select.control>
            </primitives:select.root>
        ', ['collection' => $collection]);

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $selectData = array_values($hydrationData['select'])[0];

        $this->assertSame(['opt-1'], $selectData['props']['defaultValue']);
    }

    #[Test]
    public function passesArrayDefaultValueAsIs(): void
    {
        $collection = new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
            ['value' => 'opt-2', 'label' => 'Option 2'],
        ]);

        $this->renderTemplate('
            <primitives:select.root collection="{collection}" defaultValue="{0: \'opt-1\', 1: \'opt-2\'}" multiple="{true}">
                <primitives:select.control>
                    <primitives:select.trigger>Select</primitives:select.trigger>
                </primitives:select.control>
            </primitives:select.root>
        ', ['collection' => $collection]);

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $selectData = array_values($hydrationData['select'])[0];

        $this->assertSame(['opt-1', 'opt-2'], $selectData['props']['defaultValue']);
    }

    #[Test]
    public function excludesDefaultValueFromHydrationWhenEmpty(): void
    {
        $collection = new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
        ]);

        $this->renderTemplate('
            <primitives:select.root collection="{collection}">
                <primitives:select.control>
                    <primitives:select.trigger>Select</primitives:select.trigger>
                </primitives:select.control>
            </primitives:select.root>
        ', ['collection' => $collection]);

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $selectData = array_values($hydrationData['select'])[0];

        $this->assertArrayNotHasKey('defaultValue', $selectData['props']);
    }

    /**
     * Select's Root, unlike Popover/Dialog/Tooltip's, renders a real wrapping `<div>` (needed to
     * group Label/Control/Content and carry invalid/readonly state) - so it's still detected for
     * hydration purely from that inline root ref, with Content itself portaled away entirely.
     */
    #[Test]
    public function rendersContentInsidePortalAndStillRegistersForHydration(): void
    {
        HydrationRegistry::getInstance()->clear();
        PortalRegistry::getInstance()->clearAll();

        $collection = new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
        ]);

        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}" rootId="portaled-select">
                <primitives:select.control>
                    <primitives:select.trigger>Select</primitives:select.trigger>
                </primitives:select.control>
                <ui:portal>
                    <primitives:select.content>
                        <f:for each="{collection.items}" as="item">
                            <primitives:select.item item="{item}">{item.label}</primitives:select.item>
                        </f:for>
                    </primitives:select.content>
                </ui:portal>
            </primitives:select.root>
        ', ['collection' => $collection]);

        $this->assertStringNotContainsString('data-part="content"', $html);

        $portaled = implode('', PortalRegistry::getInstance()->getAllByName('default'));
        $this->assertStringContainsString('data-part="content"', $portaled);

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $this->assertArrayHasKey('portaled-select', $hydrationData['select'] ?? []);
    }
}
