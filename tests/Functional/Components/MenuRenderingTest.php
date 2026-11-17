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

        $menus = HydrationRegistry::getInstance()->getAll()['primitives']['menu'];

        $this->assertSame('file-menu', $menus['share-menu']['props']['parentId']);
        $this->assertArrayNotHasKey('parentId', $menus['file-menu']['props']);

        // Zag composes a submenu's trigger item from the *child's* trigger props, so it's marked with the
        // child's own root id - that's what the submenu finds its anchor by.
        $this->assertSame(['share-menu'], $this->valuesOf($html, 'data-menu-trigger-item'));
    }

    #[Test]
    public function rendersTheTriggerAndTheContentClosedAndOpen(): void
    {
        $menu = static fn(string $arguments): string => '
            <primitives:menu.root ' . $arguments . '>
                <primitives:menu.trigger>Open</primitives:menu.trigger>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.item value="a">A</primitives:menu.item>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ';

        $closed = $this->renderTemplate($menu(''));
        $trigger = $this->elementWith($closed, 'data-menu-trigger');
        $this->assertSame('button', $trigger->tag);
        $this->assertSame('button', $trigger->get('type'));
        $this->assertSame('menu', $trigger->get('aria-haspopup'));
        $this->assertSame('false', $trigger->get('aria-expanded'));
        $this->assertTrue($this->elementWith($closed, 'data-menu-content')->has('hidden'));

        $open = $this->renderTemplate($menu('defaultOpen="{true}"'));
        $this->assertSame('true', $this->elementWith($open, 'data-menu-trigger')->get('aria-expanded'));
        $content = $this->elementWith($open, 'data-menu-content');
        $this->assertFalse($content->has('hidden'));
        $this->assertSame('open', $content->get('data-state'));
    }

    #[Test]
    public function rendersTheTriggerAndTheContentForThePopupTypeAndShipsItToTheClient(): void
    {
        $menu = static fn(string $arguments): string => (
            '
            <primitives:menu.root rootId="my-menu" ' . $arguments . '>
                <primitives:menu.trigger>Open</primitives:menu.trigger>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.input placeholder="Filter" />
                        <primitives:menu.list>
                            <primitives:menu.item value="a">A</primitives:menu.item>
                        </primitives:menu.list>
                        <primitives:menu.empty data-message="">No results</primitives:menu.empty>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        '
        );

        $menuPopup = $this->renderTemplate($menu(''));
        $this->assertSame('menu', $this->elementWith($menuPopup, 'data-menu-trigger')->get('aria-haspopup'));
        $content = $this->elementWith($menuPopup, 'data-menu-content');
        $this->assertSame('menu', $content->get('role'));
        $this->assertSame('0', $content->get('tabindex'));
        $this->assertSame('presentation', $this->elementWith($menuPopup, 'data-menu-list')->get('role'));
        $this->assertSame('menu', $this->firstHydrationProps()['popupType']);

        $dialogPopup = $this->renderTemplate($menu(
            'popupType="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\MenuPopupType::Dialog\')}"',
        ));
        $this->assertSame('dialog', $this->elementWith($dialogPopup, 'data-menu-trigger')->get('aria-haspopup'));
        $content = $this->elementWith($dialogPopup, 'data-menu-content');
        $this->assertSame('dialog', $content->get('role'));
        $this->assertSame('-1', $content->get('tabindex'));
        $this->assertSame('menu', $this->elementWith($dialogPopup, 'data-menu-list')->get('role'));
        $this->assertSame('dialog', $this->firstHydrationProps()['popupType']);

        $input = $this->elementWith($dialogPopup, 'data-menu-input');
        $this->assertSame('input', $input->tag);
        $this->assertSame('searchbox', $input->get('role'));
        $this->assertSame('Filter', $input->get('placeholder'));
        // the part stays on the page as a live region, its message is hidden until a filter matches nothing
        $empty = $this->elementWith($dialogPopup, 'data-menu-empty');
        $this->assertFalse($empty->has('hidden'));
        $this->assertSame('status', $empty->get('role'));
        $this->assertTrue($this->elementWith($dialogPopup, 'data-message')->has('hidden'));
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

        $items = $this->elementsByValue($html, 'data-menu-item');

        $this->assertSame('true', $items['a']->get('aria-disabled'));
        $this->assertTrue($items['a']->has('data-disabled'));
        $this->assertFalse($items['b']->has('aria-disabled'));
        $this->assertFalse($items['b']->has('data-disabled'));
    }

    #[Test]
    public function rendersCheckboxAndRadioItemsWithTheTypeTheClientBranchesOn(): void
    {
        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <primitives:menu.checkboxItem value="bold" checked="{true}">Bold</primitives:menu.checkboxItem>
                        <primitives:menu.radioItem value="left" name="align">Left</primitives:menu.radioItem>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $items = $this->elementsByValue($html, 'data-menu-item');

        $bold = $items['bold'];
        $this->assertSame('menuitemcheckbox', $bold->get('role'));
        $this->assertSame('true', $bold->get('aria-checked'));
        $this->assertSame('checked', $bold->get('data-state'));
        $this->assertSame('checkbox', $bold->get('data-type'));

        $left = $items['left'];
        $this->assertSame('menuitemradio', $left->get('role'));
        $this->assertSame('false', $left->get('aria-checked'));
        $this->assertSame('unchecked', $left->get('data-state'));
        $this->assertSame('radio', $left->get('data-type'));
        $this->assertSame('align', $left->get('data-name'));
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

        $this->assertSame(['fruits'], array_keys($this->elementsByValue($html, 'data-menu-item-group')));
        $this->assertSame(['fruits'], array_keys($this->elementsByValue($html, 'data-menu-item-group-label')));
    }

    #[Test]
    public function expandsOnlyTheTriggerOfTheDefaultTriggerValue(): void
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

        $triggers = $this->elementsByValue($html, 'data-menu-trigger');

        $this->assertSame('false', $triggers['a']->get('aria-expanded'));
        $this->assertSame('true', $triggers['b']->get('aria-expanded'));
    }

    /**
     * @return array<string, mixed>
     */
    private function firstHydrationProps(): array
    {
        return array_values(HydrationRegistry::getInstance()->getAll()['primitives']['menu'])[0]['props'];
    }
}
