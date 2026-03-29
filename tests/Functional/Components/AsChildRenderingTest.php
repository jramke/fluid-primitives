<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('asChild Rendering', function () {
    describe('basic functionality', function () {
        it('renders component template element when asChild is false', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{false}">Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<button');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('Open');
        });

        it('renders child element with component attributes when asChild is true', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <a href="/some-link">Open Dialog</a>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<a');
            expect($html)->toContain('href="/some-link"');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('data-scope="dialog"');
            expect($html)->toContain('Open Dialog');
            expect($html)->not->toMatch('/<button[^>]*data-part="trigger"/');
        });

        it('preserves child element attributes when spreading', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <button type="submit" class="my-custom-class" data-custom="value">Open</button>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('type="submit"');
            expect($html)->toContain('class="my-custom-class"');
            expect($html)->toContain('data-custom="value"');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('data-scope="dialog"');
        });

        it('child attributes take precedence over component attributes', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <button class="child-class">Open</button>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('class="child-class"');
        });
    });

    describe('with different element types', function () {
        it('works with div elements', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <div role="button" tabindex="0">Clickable Div</div>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<div');
            expect($html)->toContain('role="button"');
            expect($html)->toContain('tabindex="0"');
            expect($html)->toContain('data-part="trigger"');
        });

        it('works with span elements', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <span class="trigger-span">Click me</span>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<span');
            expect($html)->toContain('class="trigger-span"');
            expect($html)->toContain('data-part="trigger"');
        });

        it('works with custom data attributes on elements', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <button data-variant="primary" data-size="large">Custom Button</button>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<button');
            expect($html)->toContain('data-variant="primary"');
            expect($html)->toContain('data-size="large"');
            expect($html)->toContain('data-part="trigger"');
        });
    });

    describe('with accordion component', function () {
        it('spreads attributes to custom trigger element', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger asChild="{true}">
                            <div class="custom-accordion-trigger">
                                <span>Toggle Section</span>
                                <svg class="icon"></svg>
                            </div>
                        </primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('<div');
            expect($html)->toContain('class="custom-accordion-trigger"');
            expect($html)->toContain('data-part="item-trigger"');
            expect($html)->toContain('Toggle Section');
        });
    });

    describe('with close trigger', function () {
        it('spreads attributes to custom close element', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.closeTrigger asChild="{true}">
                            <span class="close-icon" aria-label="Close">×</span>
                        </primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<span');
            expect($html)->toContain('class="close-icon"');
            expect($html)->toContain('aria-label="Close"');
            expect($html)->toContain('data-part="close-trigger"');
        });
    });

    describe('hydration data', function () {
        it('correctly registers hydration data when using asChild', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root rootId="as-child-dialog">
                    <primitives:dialog.trigger asChild="{true}">
                        <button class="custom-btn">Open</button>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('dialog');
            expect($hydrationData['dialog'])->toHaveKey('as-child-dialog');
            expect($html)->toContain('data-hydrate-dialog="as-child-dialog"');
        });
    });

    describe('nested asChild usage', function () {
        it('works with multiple asChild components in the same dialog', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root rootId="multi-aschild">
                    <primitives:dialog.trigger asChild="{true}">
                        <a href="#" class="open-link">Open</a>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <p>Dialog content here</p>
                        <primitives:dialog.closeTrigger asChild="{true}">
                            <a href="#" class="close-link">Close</a>
                        </primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('class="open-link"');
            expect($html)->toContain('class="close-link"');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('data-part="close-trigger"');

            preg_match_all('/data-hydrate-dialog="multi-aschild"/', $html, $matches);
            expect(count($matches[0]))->toBeGreaterThanOrEqual(3);
        });
    });

    describe('edge cases', function () {
        it('handles boolean attributes on child element', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <button disabled autofocus>Open</button>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('disabled');
            expect($html)->toContain('autofocus');
            expect($html)->toContain('data-part="trigger"');
        });

        it('handles empty child content gracefully', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <button></button>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-part="trigger"');
        });

        it('handles self-closing child elements', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <input type="button" value="Open" />
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<input');
            expect($html)->toContain('type="button"');
            expect($html)->toContain('value="Open"');
            expect($html)->toContain('data-part="trigger"');
        });

        it('handles child with data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger asChild="{true}">
                        <button data-testid="dialog-trigger" data-analytics="open-dialog">Open</button>
                    </primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-testid="dialog-trigger"');
            expect($html)->toContain('data-analytics="open-dialog"');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('data-scope="dialog"');
        });
    });
});
