<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentPartIdUtility;
use PHPUnit\Framework\Attributes\Test;

final class ComponentPartIdUtilityTest extends TestCase
{
    #[Test]
    public function mapsNavigationMenuRootToNavMenuNamespace(): void
    {
        $id = ComponentPartIdUtility::generatePartId('navigation-menu', 'my-id', 'root');
        $this->assertSame('nav-menu:my-id', $id);
    }

    #[Test]
    public function mapsNavigationMenuNonRootPartToNavMenuNamespace(): void
    {
        $id = ComponentPartIdUtility::generatePartId('navigation-menu', 'my-id', 'viewport');
        $this->assertSame('nav-menu:my-id:viewport', $id);
    }

    #[Test]
    public function ignoresValueForRootPartIds(): void
    {
        $id = ComponentPartIdUtility::generatePartId('navigation-menu', 'my-id', 'root', 'ignored');
        $this->assertSame('nav-menu:my-id', $id);
    }

    #[Test]
    public function mapsRadioGroupItemPartToRadioSegment(): void
    {
        $id = ComponentPartIdUtility::generatePartId('radio-group', 'my-id', 'item', 'option-a');
        $this->assertSame('radio-group:my-id:radio:option-a', $id);
    }

    #[Test]
    public function mapsRadioGroupItemControlPartToRadioControlSegment(): void
    {
        $id = ComponentPartIdUtility::generatePartId('radio-group', 'my-id', 'itemControl', 'option-a');
        $this->assertSame('radio-group:my-id:radio:control:option-a', $id);
    }

    #[Test]
    public function mapsRadioGroupItemHiddenInputPartToRadioInputSegment(): void
    {
        $id = ComponentPartIdUtility::generatePartId('radio-group', 'my-id', 'itemHiddenInput', 'option-a');
        $this->assertSame('radio-group:my-id:radio:input:option-a', $id);
    }

    #[Test]
    public function mapsRadioGroupItemTextPartToRadioLabelSegment(): void
    {
        $id = ComponentPartIdUtility::generatePartId('radio-group', 'my-id', 'itemText', 'option-a');
        $this->assertSame('radio-group:my-id:radio:label:option-a', $id);
    }

    #[Test]
    public function mapsTabsTriggerPartToHyphenatedSegment(): void
    {
        $id = ComponentPartIdUtility::generatePartId('tabs', 'my-id', 'trigger', 'tab-1');
        $this->assertSame('tabs:my-id:trigger-tab-1', $id);
    }

    #[Test]
    public function mapsTabsContentPartToHyphenatedSegment(): void
    {
        $id = ComponentPartIdUtility::generatePartId('tabs', 'my-id', 'content', 'tab-1');
        $this->assertSame('tabs:my-id:content-tab-1', $id);
    }

    #[Test]
    public function getOverrideFieldIdKeyReturnsNullForUnmappedComponents(): void
    {
        $this->assertNull(ComponentPartIdUtility::getOverrideFieldIdKey('unknown-component', 'control'));
    }

    #[Test]
    public function getOverrideFieldIdKeyResolvesTheComponentSpecificPartName(): void
    {
        $this->assertSame('hiddenInput', ComponentPartIdUtility::getOverrideFieldIdKey('switch', 'control'));
    }

    #[Test]
    public function shouldSkipFieldIdsInheritanceWhenNestedInReturnsExclusionsForCheckbox(): void
    {
        $this->assertSame(['checkbox-group'], ComponentPartIdUtility::shouldSkipFieldIdsInheritanceWhenNestedIn('checkbox'));
        $this->assertSame([], ComponentPartIdUtility::shouldSkipFieldIdsInheritanceWhenNestedIn('switch'));
    }
}
