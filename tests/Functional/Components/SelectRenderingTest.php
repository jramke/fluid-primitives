<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SelectRenderingTest extends FunctionalTestCase
{
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

        $hydrationData = HydrationRegistry::getInstance()->getAll();
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

        $hydrationData = HydrationRegistry::getInstance()->getAll();
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

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $selectData = array_values($hydrationData['select'])[0];

        $this->assertArrayNotHasKey('defaultValue', $selectData['props']);
    }
}
