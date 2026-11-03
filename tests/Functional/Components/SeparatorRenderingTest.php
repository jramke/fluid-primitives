<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Exception\HydrationException;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SeparatorRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersTheOrientationForAssistiveTechAndStyling(): void
    {
        $horizontal = $this->elementWith($this->renderTemplate('<primitives:separator />'), 'data-separator-root');
        $this->assertSame('separator', $horizontal->get('role'));
        $this->assertSame('horizontal', $horizontal->get('aria-orientation'));
        $this->assertSame('horizontal', $horizontal->get('data-orientation'));

        $vertical = $this->elementWith(
            $this->renderTemplate(
                '<primitives:separator orientation="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\Orientation::Vertical\')}" />',
            ),
            'data-separator-root',
        );
        $this->assertSame('vertical', $vertical->get('aria-orientation'));
        $this->assertSame('vertical', $vertical->get('data-orientation'));
    }

    #[Test]
    public function passesItsAttributesToTheChildWithAsChild(): void
    {
        $item = $this->elementWith(
            $this->renderTemplate('<ul><primitives:separator asChild="{true}"><li></li></primitives:separator></ul>'),
            'data-separator-root',
        );
        $this->assertSame('li', $item->tag);
        $this->assertSame('separator', $item->get('role'));
    }

    #[Test]
    public function rejectsTwoServerOnlyRootsWithOneRootId(): void
    {
        // positive control: one of them renders
        $this->elementWith($this->renderTemplate('<primitives:separator rootId="rule" />'), 'data-separator-root');

        $this->assertRenderThrows(
            HydrationException::class,
            'The rootId "rule" is already used by another <separator> component.',
            fn() => $this->renderTemplate('
                <primitives:separator rootId="rule" />
                <primitives:separator rootId="rule" />
            '),
        );
    }
}
