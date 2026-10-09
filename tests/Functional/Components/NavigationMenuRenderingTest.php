<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class NavigationMenuRenderingTest extends FunctionalTestCase
{
    private const string MENU = '
        <primitives:navigationMenu.root rootId="main-nav" %s>
            <primitives:navigationMenu.list>
                <primitives:navigationMenu.item value="products">
                    <primitives:navigationMenu.trigger>Products</primitives:navigationMenu.trigger>
                    <primitives:navigationMenu.content>Products content</primitives:navigationMenu.content>
                </primitives:navigationMenu.item>
                <primitives:navigationMenu.item value="company">
                    <primitives:navigationMenu.trigger>Company</primitives:navigationMenu.trigger>
                    <primitives:navigationMenu.content>Company content</primitives:navigationMenu.content>
                </primitives:navigationMenu.item>
            </primitives:navigationMenu.list>
        </primitives:navigationMenu.root>
    ';

    #[Test]
    public function marksEveryPartOfAClosedNavigationMenuWithItsRootIdAndItsItemsValue(): void
    {
        $html = $this->renderTemplate(sprintf(self::MENU, ''));

        $this->assertSame('nav', $this->elementWith($html, 'data-navigation-menu-root')->tag);
        $this->assertSame('ul', $this->elementWith($html, 'data-navigation-menu-list')->tag);
        foreach (['item', 'trigger', 'content'] as $part) {
            $elements = $this->elementsByValue($html, 'data-navigation-menu-' . $part);
            $this->assertSame(['products', 'company'], array_keys($elements), $part);
            foreach ($elements as $element) {
                $this->assertSame('main-nav', $element->get('data-navigation-menu-' . $part), $part);
            }
        }

        $items = $this->elementsByValue($html, 'data-navigation-menu-item');
        $this->assertSame('li', $items['products']->tag);

        // the trigger announces a popup menu and is collapsed, its content is hidden, until hydration says otherwise
        foreach ($this->elementsByValue($html, 'data-navigation-menu-trigger') as $trigger) {
            $this->assertSame('button', $trigger->tag);
            $this->assertSame('button', $trigger->get('type'));
            $this->assertSame('menu', $trigger->get('aria-haspopup'));
            $this->assertSame('false', $trigger->get('aria-expanded'));
        }
        foreach ($this->elementsByValue($html, 'data-navigation-menu-content') as $content) {
            $this->assertTrue($content->has('hidden'));
            $this->assertSame('closed', $content->get('data-state'));
        }
    }

    #[Test]
    public function opensOnlyTheItemOfTheDefaultValue(): void
    {
        $html = $this->renderTemplate(sprintf(self::MENU, 'defaultValue="company"'));

        $triggers = $this->elementsByValue($html, 'data-navigation-menu-trigger');
        $this->assertSame('false', $triggers['products']->get('aria-expanded'));
        $this->assertSame('true', $triggers['company']->get('aria-expanded'));
        $this->assertSame('open', $triggers['company']->get('data-state'));

        $contents = $this->elementsByValue($html, 'data-navigation-menu-content');
        $this->assertTrue($contents['products']->has('hidden'));
        $this->assertFalse($contents['company']->has('hidden'));
        $this->assertSame('open', $contents['company']->get('data-state'));
    }
}
