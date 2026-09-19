<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class MenuRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function linksParentAndChildViaExplicitParentIdAndChildId(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root rootId="file-menu">
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.triggerItem childId="share-menu">Share</primitives:menu.triggerItem>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
            <primitives:menu.root rootId="share-menu" parentId="file-menu">
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="email">Email</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertArrayHasKey('menu', $hydrationData);
        $this->assertArrayHasKey('share-menu', $hydrationData['menu']);
        $this->assertSame('file-menu', $hydrationData['menu']['share-menu']['props']['parentId'] ?? null);
        $this->assertArrayNotHasKey('parentId', $hydrationData['menu']['file-menu']['props'] ?? []);

        $this->assertMatchesRegularExpression(
            '/data-part="trigger-item"[^>]*data-value="share-menu"|data-value="share-menu"[^>]*data-part="trigger-item"/',
            $html,
        );
    }

    #[Test]
    public function doesNotExposeParentIdWhenNotGiven(): void
    {
        $this->renderTemplate('
            <primitives:menu.root rootId="standalone-menu">
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertArrayNotHasKey('parentId', $hydrationData['menu']['standalone-menu']['props'] ?? []);
    }

    #[Test]
    public function rendersTriggerAsButtonWithAriaExpanded(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.trigger>Open</primitives:menu.trigger>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('data-scope="menu"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
    }

    #[Test]
    public function rendersContentHiddenUnlessDefaultOpen(): void
    {
        $closed = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.trigger>Open</primitives:menu.trigger>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertMatchesRegularExpression('/data-part="content"[^>]*hidden/', $closed);
        $this->assertStringContainsString('aria-expanded="false"', $closed);

        $open = $this->renderTemplate('
            <primitives:menu.root defaultOpen="{true}">
                <primitives:menu.trigger>Open</primitives:menu.trigger>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertDoesNotMatchRegularExpression('/data-part="content"[^>]*hidden/', $open);
        $this->assertStringContainsString('aria-expanded="true"', $open);
        $this->assertStringContainsString('data-state="open"', $open);
    }

    #[Test]
    public function rendersContentRoleBasedOnComposite(): void
    {
        $composite = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');
        $this->assertMatchesRegularExpression('/role="menu"[^>]*data-part="content"/', $composite);

        $nonComposite = $this->renderTemplate('
            <primitives:menu.root composite="{false}">
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');
        $this->assertMatchesRegularExpression('/role="dialog"[^>]*data-part="content"/', $nonComposite);
    }

    #[Test]
    public function rendersItemDisabledAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a" disabled="{true}">A</primitives:menu.item>
                        <primitives:menu.item value="b">B</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertMatchesRegularExpression('/aria-disabled="true" data-disabled=""[^>]*data-value="a"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-disabled[^>]*data-value="b"/', $html);
        $this->assertMatchesRegularExpression('/data-value="b"/', $html);
    }

    #[Test]
    public function rendersCheckboxAndRadioItemRoleAndCheckedState(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.checkboxItem value="bold" checked="{true}">Bold</primitives:menu.checkboxItem>
                        <primitives:menu.radioItem value="left">Left</primitives:menu.radioItem>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertMatchesRegularExpression('/role="menuitemcheckbox"[^>]*aria-checked="true"/', $html);
        $this->assertMatchesRegularExpression('/data-value="bold"[^>]*data-state="checked"/', $html);

        $this->assertMatchesRegularExpression('/role="menuitemradio"[^>]*aria-checked="false"/', $html);
        $this->assertMatchesRegularExpression('/data-value="left"[^>]*data-state="unchecked"/', $html);
    }

    #[Test]
    public function rendersItemGroupLabelLinkedToItsGroup(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.itemGroup value="fruits">
                            <primitives:menu.itemGroupLabel>Fruits</primitives:menu.itemGroupLabel>
                            <primitives:menu.item value="apple">Apple</primitives:menu.item>
                        </primitives:menu.itemGroup>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertMatchesRegularExpression('/id="menu:[^"]*:group:fruits"[^>]*data-part="item-group"/', $html);
        $this->assertMatchesRegularExpression(
            '/id="menu:[^"]*:group-label:fruits"[^>]*data-part="item-group-label"/',
            $html,
        );
    }

    #[Test]
    public function rendersMultipleTriggersWithCurrentBasedOnDefaultTriggerValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root defaultOpen="{true}" defaultTriggerValue="b">
                <primitives:menu.trigger value="a">A</primitives:menu.trigger>
                <primitives:menu.trigger value="b">B</primitives:menu.trigger>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="x">X</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertMatchesRegularExpression('/aria-expanded="false"[^>]*data-value="a"/', $html);
        $this->assertMatchesRegularExpression('/aria-expanded="true"[^>]*data-value="b"/', $html);
    }

    #[Test]
    public function rendersEachSeparatorWithAUniqueId(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                        <primitives:menu.separator />
                        <primitives:menu.item value="b">B</primitives:menu.item>
                        <primitives:menu.separator />
                        <primitives:menu.item value="c">C</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        preg_match_all('/id="([^"]+)"[^>]*data-part="separator"/', $html, $matches);
        $separatorIds = $matches[1];

        $this->assertCount(2, $separatorIds);
        $this->assertCount(2, array_unique($separatorIds));
    }
}
