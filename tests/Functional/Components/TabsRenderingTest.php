<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('Tabs Component Rendering', function () {
    describe('basic structure', function () {
        it('renders tabs root with data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            expect($html)->toContain('data-scope="tabs"');
            expect($html)->toContain('data-part="root"');
        });

        it('renders list with tablist role', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            expect($html)->toContain('role="tablist"');
            expect($html)->toContain('data-part="list"');
        });
    });

    describe('trigger', function () {
        it('renders as button with tab role and ARIA attributes', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            expect($html)->toContain('<button');
            expect($html)->toContain('role="tab"');
            expect($html)->toContain('type="button"');
            expect($html)->toContain('data-part="trigger"');
        });

        it('renders selected state correctly based on defaultValue', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-2">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                        <primitives:tabs.trigger value="tab-2">Tab 2</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                    <primitives:tabs.content value="tab-2">Content 2</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            // tab-2 should be selected (aria-selected comes before data-value in the HTML)
            expect($html)->toMatch('/aria-selected="true"[^>]*data-value="tab-2"/');
            // tab-1 should not be selected
            expect($html)->toMatch('/aria-selected="false"[^>]*data-value="tab-1"/');
        });

        it('renders disabled trigger correctly', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                        <primitives:tabs.trigger value="tab-2" disabled="{true}">Tab 2 (Disabled)</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                    <primitives:tabs.content value="tab-2">Content 2</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            expect($html)->toContain('aria-disabled="true"');
            expect($html)->toContain('data-disabled');
        });
    });

    describe('content', function () {
        it('renders with tabpanel role', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            expect($html)->toContain('role="tabpanel"');
            expect($html)->toContain('data-part="content"');
        });

        it('hides non-selected content panels', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                        <primitives:tabs.trigger value="tab-2">Tab 2</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                    <primitives:tabs.content value="tab-2">Content 2</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            // tab-2 content should be hidden
            expect($html)->toMatch('/data-value="tab-2"[^>]*hidden/');
            // tab-1 content should not have hidden attribute
            expect($html)->not->toMatch('/data-value="tab-1"[^>]*role="tabpanel"[^>]*hidden/');
        });
    });

    describe('orientation', function () {
        it('renders horizontal orientation by default', function () {
            $html = $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            expect($html)->toContain('data-orientation="horizontal"');
            expect($html)->toContain('aria-orientation="horizontal"');
        });

        it('renders vertical orientation when specified', function () {
            $html = $this->renderTemplate(<<<'FLUID'
                <primitives:tabs.root defaultValue="tab-1" orientation="{f:constant(name: 'Jramke\FluidPrimitives\Enum\Orientation::Vertical')}">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                </primitives:tabs.root>
            FLUID);

            expect($html)->toContain('data-orientation="vertical"');
            expect($html)->toContain('aria-orientation="vertical"');
        });
    });

    describe('hydration data', function () {
        it('registers component in hydration registry with props', function () {
            $this->renderTemplate('
                <primitives:tabs.root defaultValue="tab-1" loopFocus="{true}">
                    <primitives:tabs.list>
                        <primitives:tabs.trigger value="tab-1">Tab 1</primitives:tabs.trigger>
                    </primitives:tabs.list>
                    <primitives:tabs.content value="tab-1">Content 1</primitives:tabs.content>
                </primitives:tabs.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('tabs');
            $tabsData = array_values($hydrationData['tabs'])[0];
            expect($tabsData['props'])->toHaveKey('defaultValue');
            expect($tabsData['props']['defaultValue'])->toBe('tab-1');
            expect($tabsData['props'])->toHaveKey('loopFocus');
            expect($tabsData['props']['loopFocus'])->toBe(true);
        });
    });
});
