<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Exception\DuplicateProviderException;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\MissingProviderException;
use Jramke\FluidPrimitives\Exception\ProvidedRootArgumentException;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Fixtures\UsePropsForwardingComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProviderRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function aTriggerBeforeItsRootTakesTheProvidedStateAndTheRootHydratesWithTheProvidedProps(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.provider id="contact" defaultOpen="{true}" closeOnEscape="{false}" />
            <header>
                <primitives:dialog.trigger provider="contact">Contact</primitives:dialog.trigger>
            </header>
            <main>
                <primitives:dialog.root provider="contact">
                    <primitives:dialog.content>Form</primitives:dialog.content>
                </primitives:dialog.root>
            </main>
        ');

        $trigger = $this->elementWith($html, 'data-dialog-trigger');
        $this->assertSame('contact', $trigger->get('data-dialog-trigger'));
        $this->assertSame('open', $trigger->get('data-state'));
        $this->assertFalse($this->elementWith($html, 'data-dialog-content')->has('hidden'));

        $props = HydrationRegistry::getInstance()->getAll()['primitives']['dialog']['contact']['props'] ?? null;
        $this->assertIsArray($props);
        $this->assertTrue($props['defaultOpen']);
        $this->assertFalse($props['closeOnEscape']);
    }

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
    public function rejectsAProviderOnARootThatForwardsItsProps(): void
    {
        $this->assertRenderThrows(
            ProvidedRootArgumentException::class,
            'forwards its props with spreadProps, so the component around it is its root',
            fn() => $this->renderTemplate('
                <primitives:dialog.provider id="contact" />
                <primitives:dialog.root provider="contact" spreadProps="{0: \'modal\'}">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                </primitives:dialog.root>
            '),
        );
    }

    #[Test]
    public function aDetachedMenuTriggerComputesItsStateFromTheProvidedContext(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.provider id="actions" defaultOpen="{true}" defaultTriggerValue="toolbar" />
            <primitives:menu.trigger provider="actions" value="toolbar">Actions</primitives:menu.trigger>
            <primitives:menu.trigger provider="actions" value="sidebar">Actions</primitives:menu.trigger>
            <primitives:menu.root provider="actions">
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="edit">Edit</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $triggers = $this->elementsByValue($html, 'data-menu-trigger');
        $this->assertSame('true', $triggers['toolbar']->get('aria-expanded'));
        $this->assertSame('false', $triggers['sidebar']->get('aria-expanded'));
        $this->assertSame('open', $triggers['sidebar']->get('data-state'));
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
    public function uiContextReadsAProvidedContextOutsideAnyComponent(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.provider id="contact" defaultOpen="{true}" />
            <ui:context name="primitives:dialog" provider="contact" as="contactDialog" />
            <p data-open="{f:if(condition: contactDialog.defaultOpen, then: \'yes\', else: \'no\')}"></p>
        ');

        $this->assertSame('yes', $this->elementWith($html, 'data-open')->get('data-open'));
    }

    #[Test]
    public function rejectsAPartWhoseProviderIsNotRenderedBeforeIt(): void
    {
        $this->assertRenderThrows(
            MissingProviderException::class,
            'references the provider "contact", but no <dialog.provider id="contact">',
            fn() => $this->renderTemplate('
                <primitives:dialog.trigger provider="contact">Contact</primitives:dialog.trigger>
                <primitives:dialog.provider id="contact" />
            '),
        );
    }

    #[Test]
    public function rejectsAProviderOnAPartThatIsNotDetachable(): void
    {
        $this->assertRenderThrows(
            InvalidPropValueException::class,
            '<dialog.content> takes no provider.',
            fn() => $this->renderTemplate('
                <primitives:dialog.provider id="contact" />
                <primitives:dialog.content provider="contact">Form</primitives:dialog.content>
            '),
        );
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

    #[Test]
    public function aStyledWrapperProvidesItsOwnRootAndTheWrappedPrimitiveFollows(): void
    {
        $this->useWrapperCollection();

        $html = $this->renderTemplate('
            <wrapper:menu.provider id="actions" defaultOpen="{true}" />
            <wrapper:menu.trigger provider="actions">Actions</wrapper:menu.trigger>
            <wrapper:menu.root provider="actions">
                <primitives:menu.positioner>
                    <primitives:menu.content>Edit</primitives:menu.content>
                </primitives:menu.positioner>
            </wrapper:menu.root>
        ');

        $trigger = $this->elementWith($html, 'data-menu-trigger');
        $this->assertSame('actions', $trigger->get('data-menu-trigger'));
        $this->assertSame('true', $trigger->get('aria-expanded'));
        $this->assertTrue(
            HydrationRegistry::getInstance()->getAll()['wrapper']['menu']['actions']['props']['defaultOpen'],
        );
    }

    #[Test]
    public function aProviderServesOnlyThePartsOfItsOwnCollection(): void
    {
        $this->useWrapperCollection();

        $this->assertRenderThrows(
            MissingProviderException::class,
            '<menu.trigger> references the provider "actions"',
            fn() => $this->renderTemplate('
                <wrapper:menu.provider id="actions" />
                <primitives:menu.trigger provider="actions">Actions</primitives:menu.trigger>
            '),
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['wrapper']);
        parent::tearDown();
    }

    private function useWrapperCollection(): void
    {
        // Hydration looks the namespace up globally, the view needs it to resolve the tags
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['wrapper'] = [
            UsePropsForwardingComponentCollection::class,
        ];
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('wrapper', new UsePropsForwardingComponentCollection());
    }
}
