<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A part that may appear more than once in a component (two close buttons of a dialog, several separators of a
 * menu) is marked with the root id only: no `data-value`, and no `id`, which would collide. `ui:ref` owns that rule
 * (tests/Unit/RefViewHelperTest), this proves that no template of such a part passes a `value`.
 */
final class RepeatedPartsRenderingTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function repeatedParts(): iterable
    {
        yield 'the close triggers of a dialog' => [
            '<primitives:dialog.root rootId="my-root">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>
                    <primitives:dialog.closeTrigger>X</primitives:dialog.closeTrigger>
                    <primitives:dialog.closeTrigger>Cancel</primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>',
            'data-dialog-close-trigger',
        ];
        yield 'the close triggers of a popover' => [
            '<primitives:popover.root rootId="my-root">
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <primitives:popover.positioner>
                    <primitives:popover.content>
                        <primitives:popover.closeTrigger>X</primitives:popover.closeTrigger>
                        <primitives:popover.closeTrigger>Cancel</primitives:popover.closeTrigger>
                    </primitives:popover.content>
                </primitives:popover.positioner>
            </primitives:popover.root>',
            'data-popover-close-trigger',
        ];
        yield 'the triggers of a collapsible' => [
            '<primitives:collapsible.root rootId="my-root">
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Content</primitives:collapsible.content>
                <primitives:collapsible.trigger>Toggle again</primitives:collapsible.trigger>
            </primitives:collapsible.root>',
            'data-collapsible-trigger',
        ];
        yield 'the separators of a menu' => [
            '<primitives:menu.root rootId="my-root">
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                        <primitives:menu.separator />
                        <primitives:menu.item value="b">B</primitives:menu.item>
                        <primitives:menu.separator />
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>',
            'data-menu-separator',
        ];
    }

    #[Test]
    #[DataProvider('repeatedParts')]
    public function marksEveryRepeatedPartWithTheRootIdAndNothingThatCouldCollide(
        string $template,
        string $attribute,
    ): void {
        $elements = $this->elementsWith($this->renderTemplate($template), $attribute);

        $this->assertCount(2, $elements);
        foreach ($elements as $element) {
            $this->assertSame('my-root', $element->get($attribute));
            $this->assertFalse($element->has('id'));
            $this->assertFalse($element->has('data-value'));
        }
    }
}
