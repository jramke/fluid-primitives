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
}
