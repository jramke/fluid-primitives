<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ComponentNameUtilityTest extends TestCase
{
    /**
     * A tier prefix is kept, not collapsed away: "Molecules.CheckboxGroup" and "Molecules.Tooltip" are distinct,
     * nested identities that collide with neither each other nor a same-named but untiered component. A leaf
     * that is root only via the folder-shape default has no ".Root"/subcomponent marker to drop, so its full path
     * is its identity, which keeps it apart from any sibling example sharing only its first segment.
     *
     * @return iterable<string, array{0: string, 1: bool, 2: string}>
     */
    public static function viewHelperNames(): iterable
    {
        yield 'a root skips the Primitives namespace' => ['Primitives.Dialog.Root', true, 'dialog'];
        yield 'a subcomponent shares the identity of its root' => ['Accordion.Item', false, 'accordion'];
        yield 'a tiered root keeps its tier' => ['Molecules.CheckboxGroup.Root', true, 'molecules.checkboxGroup'];
        yield 'another tiered root has an identity of its own' => ['Molecules.Tooltip.Root', true, 'molecules.tooltip'];
        yield 'a tiered subcomponent shares the identity of its tiered root' => [
            'Molecules.CheckboxGroup.Label',
            false,
            'molecules.checkboxGroup',
        ];
        yield 'an independent root leaf keeps its full path' => [
            'CheckboxGroupExamples.SelectAll',
            true,
            'checkboxGroupExamples.selectAll',
        ];
    }

    #[Test]
    #[DataProvider('viewHelperNames')]
    public function resolvesTheIdentityAComponentIsHydratedAndItsContextStoredUnder(
        string $viewHelperName,
        bool $isDeclaredRoot,
        string $expected,
    ): void {
        $this->assertSame($expected, ComponentNameUtility::getComponentBaseNameFromViewHelperName(
            $viewHelperName,
            $isDeclaredRoot,
        ));
    }

    #[Test]
    public function returnsFullSubcomponentPathForDeepNames(): void
    {
        $result = ComponentNameUtility::getSubcomponentNameFromViewHelperName('Accordion.Item.Trigger');
        $this->assertSame('item.trigger', $result);
    }
}
