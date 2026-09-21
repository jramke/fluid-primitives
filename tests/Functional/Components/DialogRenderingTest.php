<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DialogRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersDialogRootComponent(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open Dialog</primitives:dialog.trigger>
                <primitives:dialog.content>Dialog Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-scope="dialog"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('data-part="content"', $html);
    }

    /**
     * `ComponentRenderer` registers a root component for hydration when its own rendered output
     * contains a `data-scope="{component}"` ref (`ui:ref` always emits one, that's the detection
     * signal) - but `ui:portal` renders empty at its own position and buffers the real markup
     * elsewhere for `ui:portalContainer` to flush, so a dialog whose every ref'd part
     * (content/title/...) is portaled, and which has no `trigger` (the one part that's never
     * portaled), has nothing inline to detect. This is exactly the shape of a dialog meant to be
     * opened only programmatically (e.g. a delete confirmation triggered from another component's
     * click handler, see the FileUpload "Confirm File Deletion" docs example) - so
     * `ComponentRenderer` also checks whatever this render pass portaled away, not just its own
     * directly-rendered output.
     */
    #[Test]
    public function registersForHydrationWhenEveryRefIsPortaledAndThereIsNoTrigger(): void
    {
        HydrationRegistry::getInstance()->clear();

        $this->renderTemplate('
            <primitives:dialog.root rootId="portaled-only-dialog">
                <ui:portal>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </ui:portal>
            </primitives:dialog.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $this->assertArrayHasKey('portaled-only-dialog', $hydrationData['dialog'] ?? []);
    }

    #[Test]
    public function generatesUniqueRootIdForHydration(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertMatchesRegularExpression('/id="dialog:[^"]+"/', $html);
    }

    #[Test]
    public function usesCustomRootIdWhenProvided(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="my-custom-dialog">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('id="dialog:my-custom-dialog:trigger"', $html);
        $this->assertStringContainsString('id="dialog:my-custom-dialog:content"', $html);
    }

    #[Test]
    public function rendersAsButtonElement(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Click me</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('Click me', $html);
    }

    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function rendersOpenStateWhenDefaultOpenIsTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root defaultOpen="{true}">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-state="open"', $html);
    }

    #[Test]
    public function appliesCustomClass(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger class="my-trigger-class">Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('class="my-trigger-class"', $html);
    }

    #[Test]
    public function rendersContentContainer(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>My Dialog Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-part="content"', $html);
        $this->assertStringContainsString('My Dialog Content', $html);
    }

    #[Test]
    public function rendersCloseTrigger(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>
                    Content
                    <primitives:dialog.closeTrigger>Close</primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-part="close-trigger"', $html);
    }

    #[Test]
    public function registersInHydrationRegistry(): void
    {
        $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertArrayHasKey('dialog', $hydrationData);
        $this->assertNotEmpty($hydrationData['dialog']);
    }

    #[Test]
    public function nestedDialogsMaintainSeparateContexts(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="parent-dialog">
                <primitives:dialog.trigger>Open Parent</primitives:dialog.trigger>
                <primitives:dialog.content>
                    Parent
                    <primitives:dialog.root rootId="child-dialog">
                        <primitives:dialog.trigger>Open Child</primitives:dialog.trigger>
                        <primitives:dialog.content>
                            Child
                            <primitives:dialog.closeTrigger>Close Child</primitives:dialog.closeTrigger>
                        </primitives:dialog.content>
                    </primitives:dialog.root>
                    <primitives:dialog.closeTrigger>Close Parent</primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('data-part="close-trigger"', $html);

        $this->assertStringContainsString('id="dialog:parent-dialog:trigger"', $html);
        $this->assertStringContainsString('id="dialog:parent-dialog:content"', $html);

        $this->assertStringContainsString('id="dialog:child-dialog:trigger"', $html);
        $this->assertStringContainsString('id="dialog:child-dialog:content"', $html);

        preg_match_all('/data-part="close-trigger"/', $html, $closeMatches);
        $this->assertCount(2, $closeMatches[0]);
    }

    #[Test]
    public function handlesDeeplyNestedDialogs(): void
    {
        $this->renderTemplate('
            <primitives:dialog.root rootId="level-1">
                <primitives:dialog.trigger>Open Level 1</primitives:dialog.trigger>
                <primitives:dialog.content>
                    <primitives:dialog.root rootId="level-2">
                        <primitives:dialog.trigger>Open Level 2</primitives:dialog.trigger>
                        <primitives:dialog.content>
                            <primitives:dialog.root rootId="level-3">
                                <primitives:dialog.trigger>Open Level 3</primitives:dialog.trigger>
                                <primitives:dialog.content>Level 3 Content</primitives:dialog.content>
                            </primitives:dialog.root>
                            <primitives:dialog.closeTrigger>Close Level 2</primitives:dialog.closeTrigger>
                        </primitives:dialog.content>
                    </primitives:dialog.root>
                    <primitives:dialog.closeTrigger>Close Level 1</primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertCount(3, $hydrationData['dialog']);
        $this->assertArrayHasKey('level-1', $hydrationData['dialog']);
        $this->assertArrayHasKey('level-2', $hydrationData['dialog']);
        $this->assertArrayHasKey('level-3', $hydrationData['dialog']);
    }

    #[Test]
    public function appliesCustomClassToRootWrapper(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger class="custom-trigger">Open</primitives:dialog.trigger>
                <primitives:dialog.content class="custom-content">Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('class="custom-trigger"', $html);
        $this->assertStringContainsString('class="custom-content"', $html);
    }

    #[Test]
    public function passesRoleProp(): void
    {
        $this->renderTemplate('
            <primitives:dialog.root role="alertdialog">
                <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                <primitives:dialog.content>Alert!</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $dialogData = array_values($hydrationData['dialog'])[0];

        $this->assertArrayHasKey('role', $dialogData['props']);
        $this->assertSame('alertdialog', $dialogData['props']['role']);
    }
}
