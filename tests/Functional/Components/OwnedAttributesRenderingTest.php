<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Fixtures\PlainComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * A caller's attribute never replaces one the same tag writes, and `{ui:attributes()}` needs no `skip` for it.
 * `FunctionalTestCase::renderTemplate()` fails on a tag that repeats an attribute, which is how a collision shows.
 */
final class OwnedAttributesRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function keepsTheAttributesAPrimitiveSetsAndPassesTheOthers(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root>
                <primitives:checkbox.hiddenInput style="display: block" data-extra="1" />
            </primitives:checkbox.root>
            <primitives:dialog.root>
                <primitives:dialog.content hidden="hidden" data-extra="2">Closed</primitives:dialog.content>
            </primitives:dialog.root>
            <primitives:dialog.root defaultOpen="{true}">
                <primitives:dialog.content hidden="hidden" data-extra="3">Open</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $input = $this->elementWith($html, 'data-checkbox-hidden-input');
        $this->assertNotSame('display: block', $input->get('style'));
        $this->assertSame('1', $input->get('data-extra'));

        $contents = $this->elementsWith($html, 'data-dialog-content');
        $this->assertTrue($contents[0]->has('hidden'));
        $this->assertSame('2', $contents[0]->get('data-extra'));
        // the dialog is open here, so the primitive renders no `hidden`, and the caller's does not take its place
        $this->assertFalse($contents[1]->has('hidden'));
        $this->assertSame('3', $contents[1]->get('data-extra'));
    }

    #[Test]
    public function keepsTheAttributesAUserlandTemplateWritesLiterallyOrUnderACondition(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());

        $html = $this->renderTemplate('
            <plain:addToBasket type="submit" hidden="hidden" data-product-id="42">Add</plain:addToBasket>
            <plain:addToBasket type="submit" closed="{true}" data-product-id="43">Add</plain:addToBasket>
        ');

        [$open, $closed] = $this->elementsWith($html, 'data-product-id');
        $this->assertSame('button', $open->get('type'));
        $this->assertFalse($open->has('hidden'));
        $this->assertSame('button', $closed->get('type'));
        $this->assertTrue($closed->has('hidden'));
    }
}
