<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CollapsibleRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersAClosedCollapsibleCollapsedWithItsContentHiddenAndAnOpenOneExpanded(): void
    {
        $collapsible = static fn(string $arguments): string => (
            '
            <primitives:collapsible.root rootId="my-collapsible" ' . $arguments . '>
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Content</primitives:collapsible.content>
            </primitives:collapsible.root>
        '
        );

        $closed = $this->renderTemplate($collapsible(''));
        foreach (['root', 'trigger', 'content'] as $part) {
            $this->assertSame(['my-collapsible'], $this->valuesOf($closed, 'data-collapsible-' . $part), $part);
        }
        $trigger = $this->elementWith($closed, 'data-collapsible-trigger');
        $this->assertSame('button', $trigger->tag);
        $this->assertSame('button', $trigger->get('type'));
        $this->assertSame('false', $trigger->get('aria-expanded'));
        $this->assertSame('closed', $trigger->get('data-state'));
        $this->assertTrue($this->elementWith($closed, 'data-collapsible-content')->has('hidden'));

        $open = $this->renderTemplate($collapsible('defaultOpen="{true}"'));
        $trigger = $this->elementWith($open, 'data-collapsible-trigger');
        $this->assertSame('true', $trigger->get('aria-expanded'));
        $this->assertSame('open', $trigger->get('data-state'));
        $content = $this->elementWith($open, 'data-collapsible-content');
        $this->assertFalse($content->has('hidden'));
        $this->assertSame('open', $content->get('data-state'));
    }

    #[Test]
    public function keepsTheCollapsedSizeOfAClosedCollapsibleInsteadOfHidingIt(): void
    {
        $collapsible = static fn(string $arguments): string => (
            '
            <primitives:collapsible.root ' . $arguments . '>
                <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                <primitives:collapsible.content>Content</primitives:collapsible.content>
            </primitives:collapsible.root>
        '
        );

        $closed = $this->elementWith(
            $this->renderTemplate($collapsible('collapsedHeight="40px" collapsedWidth="10rem"')),
            'data-collapsible-content',
        );
        $this->assertFalse($closed->has('hidden'));
        foreach ([
            '--collapsed-height: 40px;',
            'max-height: 40px;',
            '--collapsed-width: 10rem;',
            'max-width: 10rem;',
        ] as $style) {
            $this->assertStringContainsString($style, $closed->get('style') ?? '');
        }

        $open = $this->elementWith(
            $this->renderTemplate($collapsible('collapsedHeight="40px" defaultOpen="{true}"')),
            'data-collapsible-content',
        );
        $this->assertSame('', trim($open->get('style') ?? ''));
    }
}
