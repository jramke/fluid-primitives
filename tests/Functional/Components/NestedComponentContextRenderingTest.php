<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Fixtures\PlainComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class NestedComponentContextRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function givesAPartInTheTemplateOfARootItsOwnInstanceInsideAnotherOfTheSameComponent(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());

        // a bundle card that lists a product card, both render their badge in their own template
        $html = $this->renderTemplate('
            <plain:productCard.root rootId="bundle">
                <plain:productCard.root rootId="kettle" />
            </plain:productCard.root>
        ');

        $this->assertSame(['bundle', 'kettle'], $this->valuesOf($html, 'data-badge-of'));
    }
}
