<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\Fixtures\PlainComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ContextViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function readsTheContextOfTheEnclosingComponentInTheTemplateOfANestedOne(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());

        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="cookie-settings">
                <plain:dialogCloseButton />
            </primitives:dialog.root>
        ');

        $this->assertSame('cookie-settings', $this->elementWith($html, 'data-closes')->get('data-closes'));
    }
}
