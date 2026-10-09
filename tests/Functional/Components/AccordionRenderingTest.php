<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class AccordionRenderingTest extends FunctionalTestCase
{
    private const array PARTS = ['item', 'item-header', 'item-trigger', 'item-content'];

    #[Test]
    public function marksEveryPartWithTheGeneratedRootIdAndTheValueOfItsItem(): void
    {
        $html = $this->renderTemplate($this->accordion('', 'item-1', 'item-2'));

        $rootIds = $this->valuesOf($html, 'data-accordion-root');
        $this->assertCount(1, $rootIds);
        $this->assertNotSame('', $rootIds[0]);

        foreach (self::PARTS as $part) {
            $attribute = 'data-accordion-' . $part;
            $this->assertSame([$rootIds[0], $rootIds[0]], $this->valuesOf($html, $attribute), $part);
            $this->assertSame(['item-1', 'item-2'], array_keys($this->elementsByValue($html, $attribute)), $part);
        }

        $item = $this->elementsByValue($html, 'data-accordion-item-trigger')['item-1'];
        $this->assertSame('button', $item->tag);
        $this->assertSame('button', $item->get('type'));
        $this->assertSame('h3', $this->elementsByValue($html, 'data-accordion-item-header')['item-1']->tag);
        $this->assertSame(
            'region',
            $this->elementsByValue($html, 'data-accordion-item-content')['item-1']->get('role'),
        );
    }

    #[Test]
    public function rendersAnItemOpenOnlyWhenItsValueIsInTheDefaultValue(): void
    {
        $html = $this->renderTemplate($this->accordion(
            'defaultValue="{0: \'item-1\', 1: \'item-2\'}"',
            'item-1',
            'item-2',
            'item-3',
        ));

        foreach (['item', 'item-header', 'item-trigger', 'item-content'] as $part) {
            foreach ($this->elementsByValue($html, 'data-accordion-' . $part) as $value => $element) {
                $this->assertSame(
                    $value === 'item-3' ? 'closed' : 'open',
                    $element->get('data-state'),
                    $part . ' ' . $value,
                );
            }
        }
        foreach ($this->elementsByValue($html, 'data-accordion-item-content') as $value => $content) {
            $this->assertSame($value === 'item-3', $content->has('hidden'), $value);
        }
    }

    #[Test]
    public function disablesAnItemNativelyOnItsOwnOrThroughTheRootAndLetsItOverrideTheRoot(): void
    {
        $item = static fn(string $value, string $arguments = ''): string => (
            '
            <primitives:accordion.item value="' . $value . '" ' . $arguments . '>
                <primitives:accordion.itemHeader>
                    <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                </primitives:accordion.itemHeader>
                <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
            </primitives:accordion.item>
        '
        );
        $disabled = static fn($element): array => [
            $element->has('disabled'),
            $element->get('aria-disabled'),
            $element->has('data-disabled'),
        ];

        $rootDisabled = $this->renderTemplate(
            '<primitives:accordion.root disabled="{true}">' .
            $item('inherits') .
            $item('overrides', 'disabled="{false}"') .
            '</primitives:accordion.root>',
        );
        $triggers = $this->elementsByValue($rootDisabled, 'data-accordion-item-trigger');
        $this->assertSame([true, 'true', true], $disabled($triggers['inherits']));
        $this->assertSame([false, 'false', false], $disabled($triggers['overrides']));

        $itemDisabled = $this->renderTemplate(
            '<primitives:accordion.root>' .
            $item('off', 'disabled="{true}"') .
            $item('on') .
            '</primitives:accordion.root>',
        );
        $triggers = $this->elementsByValue($itemDisabled, 'data-accordion-item-trigger');
        $this->assertSame([true, 'true', true], $disabled($triggers['off']));
        $this->assertSame([false, 'false', false], $disabled($triggers['on']));
        // the header carries the same state and value, the client spreads it by value
        $header = $this->elementsByValue($itemDisabled, 'data-accordion-item-header')['off'];
        $this->assertTrue($header->has('data-disabled'));
        $this->assertSame('off', $header->get('data-value'));
    }

    #[Test]
    public function passesOrientationOnToEveryPart(): void
    {
        $html = $this->renderTemplate($this->accordion(
            'orientation="{f:constant(name: \'Jramke\FluidPrimitives\Enum\Orientation::Horizontal\')}"',
            'item-1',
        ));

        foreach (['root', 'item', 'item-trigger', 'item-content'] as $part) {
            $this->assertSame(
                'horizontal',
                $this->elementWith($html, 'data-accordion-' . $part)->get('data-orientation'),
                $part,
            );
        }
    }

    #[Test]
    public function registersOneRootWithItsClientProps(): void
    {
        $this->renderTemplate($this->accordion(
            'multiple="{true}" collapsible="{false}" defaultValue="{0: \'item-1\'}"',
            'item-1',
            'item-2',
        ));

        $accordions = HydrationRegistry::getInstance()->getAll()['primitives']['accordion'];

        // items, triggers and the like are parts of the root, not roots of their own
        $this->assertCount(1, $accordions);
        $props = array_values($accordions)[0]['props'];
        $this->assertTrue($props['multiple']);
        $this->assertFalse($props['collapsible']);
        $this->assertSame(['item-1'], $props['defaultValue']);
    }

    private function accordion(string $rootArguments, string ...$values): string
    {
        $items = '';
        foreach ($values as $value) {
            $items .= '
                <primitives:accordion.item value="' . $value . '">
                    <primitives:accordion.itemHeader>
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                    </primitives:accordion.itemHeader>
                    <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                </primitives:accordion.item>
            ';
        }

        return '<primitives:accordion.root ' . $rootArguments . '>' . $items . '</primitives:accordion.root>';
    }
}
