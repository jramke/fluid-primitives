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
