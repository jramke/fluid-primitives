<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DialogRenderingTest extends FunctionalTestCase
{
    private const array PARTS = [
        'trigger',
        'backdrop',
        'positioner',
        'content',
        'title',
        'description',
        'close-trigger',
    ];

    /**
     * A root component is registered for hydration once a `ui:ref` was rendered for it (recorded in
     * `ReferencedRootRegistry`, not found by scanning the output) - which must also hold when
     * `ui:portal` buffered every ref'd part (content/title/...) away and there is no `trigger` (the one
     * part that's never portaled). This is exactly the shape of a dialog meant to be opened only
     * programmatically (e.g. a delete confirmation triggered from another component's click handler,
     * see the FileUpload "Confirm File Deletion" docs example).
     */
    #[Test]
    public function registersForHydrationWhenEveryRefIsPortaledAndThereIsNoTrigger(): void
    {
        $this->renderTemplate('
            <primitives:dialog.root rootId="portaled-only-dialog">
                <ui:portal>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </ui:portal>
            </primitives:dialog.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $this->assertArrayHasKey('portaled-only-dialog', $hydrationData['dialog'] ?? []);
    }

    #[Test]
    public function rejectsTheSameExplicitRootIdOnTwoRootsOfOneComponent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The rootId "same-id" is already used by another <dialog> component.');

        $this->renderTemplate('
            <primitives:dialog.root rootId="same-id">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
            </primitives:dialog.root>
            <primitives:dialog.root rootId="same-id">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
            </primitives:dialog.root>
        ');
    }

    #[Test]
    public function allowsTheSameExplicitRootIdOnRootsOfDifferentComponents(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="same-id">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
            </primitives:dialog.root>
            <primitives:popover.root rootId="same-id">
                <primitives:popover.trigger>Open</primitives:popover.trigger>
            </primitives:popover.root>
        ');

        $this->assertSame('same-id', $this->elementWith($html, 'data-dialog-trigger')->get('data-dialog-trigger'));
        $this->assertSame('same-id', $this->elementWith($html, 'data-popover-trigger')->get('data-popover-trigger'));
    }

    #[Test]
    public function marksEveryPartWithTheGeneratedRootIdOfItsDialog(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.backdrop />
                <primitives:dialog.positioner>
                    <primitives:dialog.content>
                        <primitives:dialog.title>Title</primitives:dialog.title>
                        <primitives:dialog.description>Description</primitives:dialog.description>
                        <primitives:dialog.closeTrigger>Close</primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.positioner>
            </primitives:dialog.root>
        ');

        $rootIds = [];
        foreach (self::PARTS as $part) {
            $rootIds[$part] = $this->elementWith($html, 'data-dialog-' . $part)->get('data-dialog-' . $part);
        }

        $this->assertNotEmpty($rootIds['trigger']);
        $this->assertCount(1, array_unique($rootIds), 'Every part carries the same root id.');
        $this->assertSame('button', $this->elementWith($html, 'data-dialog-trigger')->tag);
        $this->assertSame('button', $this->elementWith($html, 'data-dialog-close-trigger')->tag);
    }

    #[Test]
    public function rendersAClosedDialogHiddenAndAnOpenOneVisible(): void
    {
        $dialog = static fn(string $arguments): string => (
            '
            <primitives:dialog.root ' . $arguments . '>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.backdrop />
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        '
        );

        $closed = $this->renderTemplate($dialog(''));
        $this->assertSame('closed', $this->elementWith($closed, 'data-dialog-trigger')->get('data-state'));
        foreach (['data-dialog-backdrop', 'data-dialog-content'] as $part) {
            $element = $this->elementWith($closed, $part);
            $this->assertTrue($element->has('hidden'), $part);
            $this->assertSame('closed', $element->get('data-state'), $part);
        }

        $open = $this->renderTemplate($dialog('defaultOpen="{true}"'));
        $this->assertSame('open', $this->elementWith($open, 'data-dialog-trigger')->get('data-state'));
        foreach (['data-dialog-backdrop', 'data-dialog-content'] as $part) {
            $element = $this->elementWith($open, $part);
            $this->assertFalse($element->has('hidden'), $part);
            $this->assertSame('open', $element->get('data-state'), $part);
        }
    }

    #[Test]
    public function appliesAClassToTheTriggerAndTheContent(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger class="custom-trigger">Open</primitives:dialog.trigger>
                <primitives:dialog.content class="custom-content">Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertSame('custom-trigger', $this->elementWith($html, 'data-dialog-trigger')->get('class'));
        $this->assertSame('custom-content', $this->elementWith($html, 'data-dialog-content')->get('class'));
    }

    #[Test]
    public function nestedDialogsKeepTheirOwnRootIdsAndRegisterSeparately(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="parent-dialog">
                <primitives:dialog.trigger>Open Parent</primitives:dialog.trigger>
                <primitives:dialog.content>
                    <primitives:dialog.root rootId="child-dialog">
                        <primitives:dialog.trigger>Open Child</primitives:dialog.trigger>
                        <primitives:dialog.content>
                            <primitives:dialog.root rootId="grandchild-dialog">
                                <primitives:dialog.trigger>Open Grandchild</primitives:dialog.trigger>
                                <primitives:dialog.content>Grandchild</primitives:dialog.content>
                            </primitives:dialog.root>
                            <primitives:dialog.closeTrigger>Close Child</primitives:dialog.closeTrigger>
                        </primitives:dialog.content>
                    </primitives:dialog.root>
                    <primitives:dialog.closeTrigger>Close Parent</primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>
        ');

        // document order: a nested dialog is rendered inside the content of its parent
        $this->assertSame(
            ['parent-dialog', 'child-dialog', 'grandchild-dialog'],
            $this->valuesOf($html, 'data-dialog-trigger'),
        );
        $this->assertEqualsCanonicalizing(
            ['parent-dialog', 'child-dialog', 'grandchild-dialog'],
            $this->valuesOf($html, 'data-dialog-content'),
        );
        // the close trigger of each dialog belongs to it, not to the dialog rendered last
        $this->assertEqualsCanonicalizing(
            ['parent-dialog', 'child-dialog'],
            $this->valuesOf($html, 'data-dialog-close-trigger'),
        );

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives']['dialog'];
        $this->assertEqualsCanonicalizing(
            ['parent-dialog', 'child-dialog', 'grandchild-dialog'],
            array_keys($hydrationData),
        );
    }

    #[Test]
    public function passesRoleProp(): void
    {
        $this->renderTemplate('
            <primitives:dialog.root role="{f:constant(name: \'Jramke\FluidPrimitives\Enum\DialogRole::AlertDialog\')}">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>Alert!</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $dialogData = array_values($hydrationData['dialog'])[0];

        $this->assertArrayHasKey('role', $dialogData['props']);
        $this->assertSame('alertdialog', $dialogData['props']['role']);
    }

    /**
     * The rootId is the value of every part attribute and lands unescaped inside zag's attribute selectors.
     */
    #[Test]
    public function rejectsARootIdThatWouldBreakThePartSelectors(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain quotes or backslashes');

        $this->renderTemplate(
            '<primitives:dialog.root rootId=\'my"dialog\'><primitives:dialog.trigger>Open</primitives:dialog.trigger></primitives:dialog.root>',
        );
    }
}
