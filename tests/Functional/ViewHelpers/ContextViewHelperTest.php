<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
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

    #[Test]
    public function readsTheClosestRootAndThePropsItsPartsExposeInAPlainTemplate(): void
    {
        // The inner accordion opens one item at a time, the outer one several: the closest root wins, and after the
        // inner root the outer one is the closest again
        $html = $this->renderTemplate('
            <primitives:accordion.root multiple="{true}">
                <primitives:accordion.item value="shipping">
                    <primitives:accordion.itemTrigger asChild="{true}">
                        <ui:context name="primitives:accordion" as="outer" />
                        <button type="button" data-multiple="{outer.multiple}" data-item="{outer.item.value}">Shipping</button>
                    </primitives:accordion.itemTrigger>
                    <primitives:accordion.itemContent>
                        <primitives:accordion.root>
                            <primitives:accordion.item value="express">
                                <primitives:accordion.itemTrigger asChild="{true}">
                                    <ui:context name="primitives:accordion" as="inner" />
                                    <button type="button" data-multiple="[{inner.multiple}]" data-item="{inner.item.value}">Express</button>
                                </primitives:accordion.itemTrigger>
                            </primitives:accordion.item>
                        </primitives:accordion.root>
                    </primitives:accordion.itemContent>
                </primitives:accordion.item>
                <primitives:accordion.item value="returns">
                    <primitives:accordion.itemTrigger asChild="{true}">
                        <ui:context name="primitives:accordion" as="after" />
                        <button type="button" data-multiple="{after.multiple}" data-item="{after.item.value}">Returns</button>
                    </primitives:accordion.itemTrigger>
                </primitives:accordion.item>
            </primitives:accordion.root>
            <ui:context name="primitives:accordion" as="outside" />
            <span data-outside="{f:if(condition: outside, then: \'found\', else: \'none\')}"></span>
        ');

        $triggers = $this->elementsByValue($html, 'data-accordion-item-trigger');
        $this->assertSame(['1', 'shipping'], [
            $triggers['shipping']->get('data-multiple'),
            $triggers['shipping']->get('data-item'),
        ]);
        $this->assertSame(['[]', 'express'], [
            $triggers['express']->get('data-multiple'),
            $triggers['express']->get('data-item'),
        ]);
        $this->assertSame(['1', 'returns'], [
            $triggers['returns']->get('data-multiple'),
            $triggers['returns']->get('data-item'),
        ]);
        $this->assertSame('none', $this->elementWith($html, 'data-outside')->get('data-outside'));
    }

    #[Test]
    public function rejectsTheContextOfAComponentFromAnotherCollection(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());

        $this->assertRenderThrows(
            InvalidPropValueException::class,
            'The closest enclosing dialog is not a plain:dialog.',
            fn() => $this->renderTemplate('
                <primitives:dialog.root rootId="cookie-settings">
                    <plain:foreignDialogReader />
                </primitives:dialog.root>
            '),
        );
    }

    #[Test]
    public function rejectsANamespaceThatOnlyThePageTemplateDeclares(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());

        $this->assertRenderThrows(
            InvalidPropValueException::class,
            'The namespace "local" of "local:dialog" is not known where ui:context renders.',
            fn() => $this->renderTemplate('
                {namespace local=Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection}
                <local:dialog.root rootId="cookie-settings">
                    <plain:localDialogReader />
                </local:dialog.root>
            '),
        );
    }

    #[Test]
    public function rejectsANameWithoutItsNamespace(): void
    {
        $this->assertRenderThrows(
            InvalidPropValueException::class,
            '"dialog" has none.',
            fn() => $this->renderTemplate('<ui:context name="dialog" as="dialogContext" />'),
        );
    }
}
