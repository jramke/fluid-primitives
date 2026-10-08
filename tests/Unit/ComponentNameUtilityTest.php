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
        $result = ComponentNameUtility::getComponentBaseNameFromViewHelperName(
            'Primitives.Dialog.Root',
            isDeclaredRoot: true,
        );
        $this->assertSame('dialog', $result);
    }

    #[Test]
    public function extractsBaseNameFromCompoundComponent(): void
    {
        $result = ComponentNameUtility::getComponentBaseNameFromViewHelperName('Accordion.Item', isDeclaredRoot: false);
        $this->assertSame('accordion', $result);
    }

    #[Test]
    public function tieredComponentsKeepTheirTierAsPartOfTheirOwnIdentity(): void
    {
        // A tier prefix is kept, not collapsed away - "Molecules.CheckboxGroup" and
        // "Molecules.Tooltip" are distinct, nested identities, and neither collides with the
        // other or with a same-named but untiered "checkboxGroup"/"tooltip".
        $this->assertSame('molecules.checkboxGroup', ComponentNameUtility::getComponentBaseNameFromViewHelperName(
            'Molecules.CheckboxGroup.Root',
            isDeclaredRoot: true,
        ));
        $this->assertSame('molecules.tooltip', ComponentNameUtility::getComponentBaseNameFromViewHelperName(
            'Molecules.Tooltip.Root',
            isDeclaredRoot: true,
        ));
        // A genuine (non-root) subcomponent of a tiered component still resolves to the same
        // identity as its own root, exactly like the classic Accordion.Root/Accordion.Item case.
        $this->assertSame('molecules.checkboxGroup', ComponentNameUtility::getComponentBaseNameFromViewHelperName(
            'Molecules.CheckboxGroup.Label',
            isDeclaredRoot: false,
        ));
    }

    #[Test]
    public function independentlyRootLeafKeepsItsFullPathAsItsOwnIdentity(): void
    {
        // 'CheckboxGroupExamples.SelectAll' is root only via the folder-shape default (see
        // ComponentRootUtilityTest), a sibling of - not nested inside - the real CheckboxGroup's
        // own folder. It has no ".Root"/subcomponent marker to drop, so its own last segment is
        // kept - the full path is its identity, distinguishing it from any sibling example (e.g.
        // "checkboxGroupExamples.simple") sharing only its first segment.
        $this->assertSame('checkboxGroupExamples.selectAll', ComponentNameUtility::getComponentBaseNameFromViewHelperName(
            'CheckboxGroupExamples.SelectAll',
            isDeclaredRoot: true,
        ));
    }

    #[Test]
    public function returnsFullSubcomponentPathForDeepNames(): void
    {
        $result = ComponentNameUtility::getSubcomponentNameFromViewHelperName('Accordion.Item.Trigger');
        $this->assertSame('item.trigger', $result);
    }
}
