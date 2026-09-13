<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use PHPUnit\Framework\Attributes\Test;

final class ComponentNameUtilityTest extends TestCase
{
    #[Test]
    public function handlesComplexNamesWithMultipleCapitals(): void
    {
        $result = ComponentNameUtility::getComponentFullNameFromViewHelperName('ScrollArea.Root');
        $this->assertSame('scroll-area.root', $result);
    }

    #[Test]
    public function skipsPrimitivesNamespace(): void
    {
        $result = ComponentNameUtility::getComponentBaseNameFromViewHelperName('Primitives.Dialog.Root');
        $this->assertSame('dialog', $result);
    }

    #[Test]
    public function extractsBaseNameFromCompoundComponent(): void
    {
        $result = ComponentNameUtility::getComponentBaseNameFromViewHelperName('Accordion.Item');
        $this->assertSame('accordion', $result);
    }

    #[Test]
    public function returnsFullSubcomponentPathForDeepNames(): void
    {
        $result = ComponentNameUtility::getSubcomponentNameFromViewHelperName('Accordion.Item.Trigger');
        $this->assertSame('item.trigger', $result);
    }

    #[Test]
    public function returnsTrueForSinglePartComponentName(): void
    {
        $this->assertTrue(ComponentNameUtility::isRootComponent('Collapsible'));
    }

    #[Test]
    public function returnsTrueWhenSecondPartIsRoot(): void
    {
        $this->assertTrue(ComponentNameUtility::isRootComponent('Accordion.Root'));
    }

    #[Test]
    public function returnsFalseForItemComponents(): void
    {
        $this->assertFalse(ComponentNameUtility::isRootComponent('Accordion.Item'));
    }

    #[Test]
    public function handlesPrimitivesNamespaceSecondPartIsNotRoot(): void
    {
        $this->assertFalse(ComponentNameUtility::isRootComponent('Primitives.Dialog.Root'));
    }
}
