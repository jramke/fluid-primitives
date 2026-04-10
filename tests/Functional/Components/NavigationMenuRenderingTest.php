<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('NavigationMenu Component Rendering', function () {
    describe('basic structure', function () {
        it('renders root as nav element with data attributes', function () {
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

            expect($html)->toContain('<nav');
            expect($html)->toContain('data-scope="navigation-menu"');
            expect($html)->toContain('data-part="root"');
        });

        it('renders list as ul element with data attributes', function () {
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

            expect($html)->toContain('<ul');
            expect($html)->toContain('data-part="list"');
        });

        it('renders items as li elements with value attribute', function () {
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

            expect($html)->toContain('<li');
            expect($html)->toContain('data-part="item"');
            expect($html)->toContain('data-value="products"');
        });
    });

    describe('trigger', function () {
        it('renders as button with aria attributes', function () {
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

            expect($html)->toContain('<button');
            expect($html)->toContain('type="button"');
            expect($html)->toContain('aria-haspopup="menu"');
            expect($html)->toContain('data-part="trigger"');
        });

        it('renders closed state by default', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root>
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('aria-expanded="false"');
            expect($html)->toContain('data-state="closed"');
        });

        it('renders open state when item matches defaultValue', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root defaultValue="item-1">
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger 1</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content 1</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                        <primitives:navigationMenu.item value="item-2">
                            <primitives:navigationMenu.trigger>Trigger 2</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content 2</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('aria-expanded="true"');
            expect($html)->toContain('aria-expanded="false"');
        });

        it('renders disabled trigger with disabled attribute', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root>
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1" disabled="{true}">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('disabled');
            expect($html)->toContain('data-disabled');
        });
    });

    describe('content', function () {
        it('hides content for closed items', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root>
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>My Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('hidden');
            expect($html)->toContain('data-part="content"');
            expect($html)->toContain('My Content');
        });

        it('shows content for item matching defaultValue', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root defaultValue="item-1">
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Open Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                        <primitives:navigationMenu.item value="item-2">
                            <primitives:navigationMenu.trigger>Trigger 2</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Closed Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            // item-1 content is open, so no hidden; item-2 content is closed, so hidden
            expect(preg_match_all('/hidden/', $html))->toBe(1);
        });
    });

    describe('link', function () {
        it('renders as anchor element with data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root>
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.link href="/products">Products</primitives:navigationMenu.link>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('<a');
            expect($html)->toContain('data-part="link"');
            expect($html)->toContain('href="/products"');
        });

        it('sets aria-current and data-current for current links', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root>
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.link href="/products" current="{true}">Products</primitives:navigationMenu.link>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('aria-current="page"');
            expect($html)->toContain('data-current');
        });
    });

    describe('orientation', function () {
        it('defaults to horizontal orientation', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root>
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('data-orientation="horizontal"');
        });

        it('propagates orientation to items and content', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root orientation="vertical">
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect(preg_match_all('/data-orientation="vertical"/', $html))->toBeGreaterThanOrEqual(2);
        });
    });

    describe('multiple items', function () {
        it('renders multiple items with independent state', function () {
            $html = $this->renderTemplate('
                <primitives:navigationMenu.root defaultValue="products">
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="products">
                            <primitives:navigationMenu.trigger>Products</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Products Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                        <primitives:navigationMenu.item value="company">
                            <primitives:navigationMenu.trigger>Company</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Company Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                        <primitives:navigationMenu.item value="blog">
                            <primitives:navigationMenu.trigger>Blog</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Blog Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            expect($html)->toContain('data-value="products"');
            expect($html)->toContain('data-value="company"');
            expect($html)->toContain('data-value="blog"');
            expect($html)->toContain('Products Content');
            expect($html)->toContain('Company Content');
            expect($html)->toContain('Blog Content');
        });
    });

    describe('hydration data', function () {
        it('registers component in hydration registry', function () {
            $this->renderTemplate('
                <primitives:navigationMenu.root>
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('navigation-menu');
            expect($hydrationData['navigation-menu'])->toBeArray();
            expect(count($hydrationData['navigation-menu']))->toBe(1);
        });

        it('includes client props in hydration data', function () {
            $this->renderTemplate('
                <primitives:navigationMenu.root defaultValue="item-1" orientation="vertical">
                    <primitives:navigationMenu.list>
                        <primitives:navigationMenu.item value="item-1">
                            <primitives:navigationMenu.trigger>Trigger</primitives:navigationMenu.trigger>
                            <primitives:navigationMenu.content>Content</primitives:navigationMenu.content>
                        </primitives:navigationMenu.item>
                    </primitives:navigationMenu.list>
                </primitives:navigationMenu.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $menuData = array_values($hydrationData['navigation-menu'])[0];

            expect($menuData['props'])->toHaveKey('defaultValue');
            expect($menuData['props']['defaultValue'])->toBe('item-1');
            expect($menuData['props'])->toHaveKey('orientation');
            expect($menuData['props']['orientation'])->toBe('vertical');
        });
    });
});
