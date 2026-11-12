<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Fixtures\PlainComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class NestedComponentContextRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function givesTheTemplatesOfARootAndItsPartsTheirOwnInstanceInsideAnotherOfTheSameComponent(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());

        // a bundle card that lists a product card: each renders its badge and a wishlist button in its own
        // template, and the badge renders one more, which finds the card through ui:context
        $html = $this->renderTemplate('
            <plain:productCard.root rootId="bundle">
                <plain:productCard.root rootId="kettle" />
            </plain:productCard.root>
        ');

        $this->assertSame(['bundle', 'kettle'], $this->valuesOf($html, 'data-badge-of'));
        $this->assertSame(['bundle', 'bundle', 'kettle', 'kettle'], $this->valuesOf($html, 'data-wishlist-for'));
    }

    #[Test]
    public function keepsTheContextOfARootWhileAnotherRootOfTheSameComponentRendersInsideIt(): void
    {
        // a Popover context comes from the container: when the container shares it, the inner root
        // overwrites the outer root's state and the outer close trigger renders with the inner's
        $html = $this->renderTemplate('
            <primitives:popover.root rootId="outer" translations="{closeTriggerLabel: \'Close outer\'}">
                <primitives:popover.content>
                    <primitives:popover.root rootId="inner" translations="{closeTriggerLabel: \'Close inner\'}">
                        <primitives:popover.closeTrigger>Inner</primitives:popover.closeTrigger>
                    </primitives:popover.root>
                    <primitives:popover.closeTrigger>Outer</primitives:popover.closeTrigger>
                </primitives:popover.content>
            </primitives:popover.root>
        ');

        $this->assertSame(['inner', 'outer'], $this->valuesOf($html, 'data-popover-close-trigger'));
        $this->assertSame(['Close inner', 'Close outer'], $this->valuesOf($html, 'aria-label'));
    }
}
