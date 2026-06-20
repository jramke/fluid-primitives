<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use PHPUnit\Framework\Attributes\Test;

final class ComponentUtilityTest extends TestCase
{
    #[Test]
    public function generatesUniqueIdsWithPrefix(): void
    {
        $id1 = ComponentUtility::id();
        $id2 = ComponentUtility::id();
        $id3 = ComponentUtility::id('custom');

        $this->assertStringStartsWith('«f', $id1);
        $this->assertStringEndsWith('»', $id1);
        $this->assertStringStartsWith('«custom', $id3);
        $this->assertNotSame($id1, $id2);
    }

    #[Test]
    public function handlesComplexNamesWithMultipleCapitals(): void
    {
        $result = ComponentUtility::getComponentFullNameFromViewHelperName('ScrollArea.Root');
        $this->assertSame('scroll-area.root', $result);
    }

    #[Test]
    public function skipsPrimitivesNamespace(): void
    {
        $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Primitives.Dialog.Root');
        $this->assertSame('dialog', $result);
    }

    #[Test]
    public function extractsBaseNameFromCompoundComponent(): void
    {
        $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Accordion.Item');
        $this->assertSame('accordion', $result);
    }

    #[Test]
    public function returnsFullSubcomponentPathForDeepNames(): void
    {
        $result = ComponentUtility::getSubcomponentNameFromViewHelperName('Accordion.Item.Trigger');
        $this->assertSame('item.trigger', $result);
    }

    #[Test]
    public function returnsTrueForSinglePartComponentName(): void
    {
        $this->assertTrue(ComponentUtility::isRootComponent('Collapsible'));
    }

    #[Test]
    public function returnsTrueWhenSecondPartIsRoot(): void
    {
        $this->assertTrue(ComponentUtility::isRootComponent('Accordion.Root'));
    }

    #[Test]
    public function returnsFalseForItemComponents(): void
    {
        $this->assertFalse(ComponentUtility::isRootComponent('Accordion.Item'));
    }

    #[Test]
    public function handlesPrimitivesNamespaceSecondPartIsNotRoot(): void
    {
        $this->assertFalse(ComponentUtility::isRootComponent('Primitives.Dialog.Root'));
    }
}
