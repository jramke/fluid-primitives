<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ToggleGroupRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function marksTheRootAndEveryItemWithTheRootIdAndTheValueOfTheItem(): void
    {
        $html = $this->renderTemplate($this->toggleGroup('rootId="my-group"', 'left', 'center'));

        $this->assertSame(['my-group'], $this->valuesOf($html, 'data-toggle-group-root'));
        $this->assertSame(['my-group', 'my-group'], $this->valuesOf($html, 'data-toggle-group-item'));
        $items = $this->elementsByValue($html, 'data-toggle-group-item');
        $this->assertSame(['left', 'center'], array_keys($items));
        $this->assertSame('button', $items['left']->tag);
        $this->assertSame('button', $items['left']->get('type'));
        $this->assertSame('group', $this->elementWith($html, 'data-toggle-group-root')->get('role'));
    }

    #[Test]
    public function rendersAnItemPressedOnlyWhenItsValueIsInTheDefaultValue(): void
    {
        $html = $this->renderTemplate($this->toggleGroup(
            'multiple="{true}" defaultValue="{0: \'bold\', 1: \'underline\'}"',
            'bold',
            'italic',
            'underline',
        ));

        $items = $this->elementsByValue($html, 'data-toggle-group-item');
        foreach ($items as $value => $item) {
            $pressed = $value !== 'italic';
            $this->assertSame($pressed ? 'true' : 'false', $item->get('aria-pressed'), $value);
            $this->assertSame($pressed, $item->has('data-pressed'), $value);
        }
    }

    #[Test]
    public function disablesAnItemOnItsOwnOrThroughTheGroupAndNeverLetsItOptOutOfADisabledGroup(): void
    {
        $item = static fn(string $value, string $arguments = ''): string => (
            '<primitives:toggleGroup.item value="' .
            $value .
            '" ' .
            $arguments .
            '>' .
            $value .
            '</primitives:toggleGroup.item>'
        );
        $disabled = static fn($element): array => [$element->has('disabled'), $element->has('data-disabled')];

        $groupDisabled = $this->renderTemplate(
            '<primitives:toggleGroup.root disabled="{true}">' .
            $item('inherits') .
            $item('triesToOptOut', 'disabled="{false}"') .
            '</primitives:toggleGroup.root>',
        );
        $this->assertTrue($this->elementWith($groupDisabled, 'data-toggle-group-root')->has('data-disabled'));
        $items = $this->elementsByValue($groupDisabled, 'data-toggle-group-item');
        $this->assertSame([true, true], $disabled($items['inherits']));
        $this->assertSame([true, true], $disabled($items['triesToOptOut']));

        $itemDisabled = $this->renderTemplate(
            '<primitives:toggleGroup.root>' .
            $item('off', 'disabled="{true}"') .
            $item('on') .
            '</primitives:toggleGroup.root>',
        );
        $this->assertFalse($this->elementWith($itemDisabled, 'data-toggle-group-root')->has('data-disabled'));
        $items = $this->elementsByValue($itemDisabled, 'data-toggle-group-item');
        $this->assertSame([true, true], $disabled($items['off']));
        $this->assertSame([false, false], $disabled($items['on']));
    }

    #[Test]
    public function passesTheOrientationAndTheMultipleStateOn(): void
    {
        $html = $this->renderTemplate($this->toggleGroup(
            'multiple="{true}" orientation="{f:constant(name: \'Jramke\FluidPrimitives\Enum\Orientation::Vertical\')}"',
            'a',
        ));

        $root = $this->elementWith($html, 'data-toggle-group-root');
        $this->assertSame('vertical', $root->get('data-orientation'));
        $this->assertTrue($root->has('data-multiple'));
        $this->assertSame('vertical', $this->elementWith($html, 'data-toggle-group-item')->get('data-orientation'));

        $default = $this->renderTemplate($this->toggleGroup('', 'a'));
        $root = $this->elementWith($default, 'data-toggle-group-root');
        $this->assertSame('horizontal', $root->get('data-orientation'));
        $this->assertFalse($root->has('data-multiple'));
    }

    #[Test]
    public function registersOneRootWithItsClientProps(): void
    {
        $this->renderTemplate($this->toggleGroup(
            'multiple="{true}" deselectable="{false}" rovingFocus="{false}" defaultValue="{0: \'a\'}"',
            'a',
            'b',
        ));

        $groups = HydrationRegistry::getInstance()->getAll()['primitives']['toggle-group'];

        // the items are parts of the root, not roots of their own
        $this->assertCount(1, $groups);
        $props = array_values($groups)[0]['props'];
        $this->assertTrue($props['multiple']);
        $this->assertFalse($props['deselectable']);
        $this->assertFalse($props['rovingFocus']);
        $this->assertSame(['a'], $props['defaultValue']);
    }

    private function toggleGroup(string $rootArguments, string ...$values): string
    {
        $items = '';
        foreach ($values as $value) {
            $items .=
                '<primitives:toggleGroup.item value="' . $value . '">' . $value . '</primitives:toggleGroup.item>';
        }

        return '<primitives:toggleGroup.root ' . $rootArguments . '>' . $items . '</primitives:toggleGroup.root>';
    }
}
