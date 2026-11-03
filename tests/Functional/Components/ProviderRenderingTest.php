<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Exception\DuplicateProviderException;
use Jramke\FluidPrimitives\Exception\ProvidedRootArgumentException;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProviderRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function aFolderNamedRootTakesItsPropsFromTheProviderAndKeepsItsElementArguments(): void
    {
        $html = $this->renderTemplate('
            <primitives:button.provider id="save" loading="{true}" />
            <primitives:button provider="save" class="btn" data-test="save">Save</primitives:button>
        ');

        $button = $this->elementWith($html, 'data-button-root');
        $this->assertSame('save', $button->get('data-button-root'));
        $this->assertSame('btn', $button->get('class'));
        $this->assertSame('save', $button->get('data-test'));
        $this->assertTrue($button->has('data-loading'));
    }

    #[Test]
    public function rejectsARootIdNextToAProvider(): void
    {
        $this->assertRenderThrows(
            ProvidedRootArgumentException::class,
            'takes its rootId from <dialog.provider id="contact">',
            fn() => $this->renderTemplate('
                <primitives:dialog.provider id="contact" />
                <primitives:dialog.root provider="contact" rootId="other">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                </primitives:dialog.root>
            '),
        );
    }

    #[Test]
    public function aProvidedRootStillTakesTheStateOfTheFieldAroundIt(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.provider id="terms" value="yes" />
            <primitives:field.root name="terms" invalid="{true}">
                <primitives:checkbox.root provider="terms">
                    <primitives:checkbox.hiddenInput />
                </primitives:checkbox.root>
            </primitives:field.root>
        ');

        $input = $this->elementWith($html, 'data-checkbox-hidden-input');
        $this->assertSame('terms', $input->get('name'));
        $this->assertSame('yes', $input->get('value'));
        $this->assertSame('true', $input->get('aria-invalid'));
    }

    #[Test]
    public function rejectsASecondProviderForOneRootId(): void
    {
        $this->assertRenderThrows(
            DuplicateProviderException::class,
            'There already is a <dialog.provider id="contact">',
            fn() => $this->renderTemplate('
                <primitives:dialog.provider id="contact" />
                <primitives:dialog.provider id="contact" />
            '),
        );
    }

    #[Test]
    public function rejectsAPropOnAProvidedRoot(): void
    {
        $this->assertRenderThrows(
            ProvidedRootArgumentException::class,
            'with provider "contact" takes its props from its <dialog.provider>. Move "modal" there.',
            fn() => $this->renderTemplate('
                <primitives:dialog.provider id="contact" />
                <primitives:dialog.root provider="contact" modal="{false}">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                </primitives:dialog.root>
            '),
        );
    }
}
