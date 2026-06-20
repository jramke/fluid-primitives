<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class NavigationMenuRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersRootAsNavElementWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:navigationMenu.root>
                <primitives:navigationMenu.list>
                    <primitives:navigationMenu.item value="item-1">
                        <primitives:navigationMenu.trigger>Products</primitives:navigationMenu.trigger>
                        <primitives:navigationMenu.content>Content 1</primitives:navigationMenu.content>
                    </primitives:navigationMenu.item>
                </primitives:navigationMenu.list>
            </primitives:navigationMenu.root>
        ');

        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('data-scope="navigation-menu"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersListAsUlElement(): void
    {
        $html = $this->renderTemplate('
            <primitives:navigationMenu.root>
                <primitives:navigationMenu.list>
                    <primitives:navigationMenu.item value="item-1">
                        <primitives:navigationMenu.trigger>Products</primitives:navigationMenu.trigger>
                        <primitives:navigationMenu.content>Content 1</primitives:navigationMenu.content>
                    </primitives:navigationMenu.item>
                </primitives:navigationMenu.list>
            </primitives:navigationMenu.root>
        ');

        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('data-part="list"', $html);
    }

    #[Test]
    public function rendersItemsAsLiElementsWithValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:navigationMenu.root>
                <primitives:navigationMenu.list>
                    <primitives:navigationMenu.item value="products">
                        <primitives:navigationMenu.trigger>Products</primitives:navigationMenu.trigger>
                        <primitives:navigationMenu.content>Products Content</primitives:navigationMenu.content>
                    </primitives:navigationMenu.item>
                </primitives:navigationMenu.list>
            </primitives:navigationMenu.root>
        ');

        $this->assertStringContainsString('<li', $html);
        $this->assertStringContainsString('data-part="item"', $html);
        $this->assertStringContainsString('data-value="products"', $html);
    }

    #[Test]
    public function rendersTriggerAsButtonWithAriaAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:navigationMenu.root>
                <primitives:navigationMenu.list>
                    <primitives:navigationMenu.item value="item-1">
                        <primitives:navigationMenu.trigger>Click me</primitives:navigationMenu.trigger>
                        <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                    </primitives:navigationMenu.item>
                </primitives:navigationMenu.list>
            </primitives:navigationMenu.root>
        ');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertStringContainsString('data-part="trigger"', $html);
    }

    #[Test]
    public function rendersClosedStateByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:navigationMenu.root>
                <primitives:navigationMenu.list>
                    <primitives:navigationMenu.item value="item-1">
                        <primitives:navigationMenu.trigger>Products</primitives:navigationMenu.trigger>
                        <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                    </primitives:navigationMenu.item>
                </primitives:navigationMenu.list>
            </primitives:navigationMenu.root>
        ');

        $this->assertStringContainsString('aria-expanded="false"', $html);
    }
}
