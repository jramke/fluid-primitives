<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('Collapsible Component Rendering', function () {
    describe('basic structure', function () {
        it('renders collapsible root with data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root>
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Hidden content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('data-scope="collapsible"');
            expect($html)->toContain('data-part="root"');
        });
    });

    describe('trigger', function () {
        it('renders as button with aria-expanded', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root>
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('<button');
            expect($html)->toContain('type="button"');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('aria-expanded="false"');
        });

        it('renders expanded state when defaultOpen is true', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root defaultOpen="{true}">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('aria-expanded="true"');
            expect($html)->toContain('data-state="open"');
        });
    });

    describe('content', function () {
        it('renders hidden by default when closed', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root>
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Hidden content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('data-part="content"');
            expect($html)->toMatch('/data-part="content"[^>]*hidden/');
        });

        it('renders visible when defaultOpen is true', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root defaultOpen="{true}">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Visible content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('Visible content');
            expect($html)->not->toMatch('/data-part="content"[^>]*hidden/');
        });
    });

    describe('collapsed size styles', function () {
        it('applies height styles when collapsedHeight is set and closed', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root collapsedHeight="50px">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content with collapsed height</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('--collapsed-height: 50px');
            expect($html)->toContain('min-height: 50px');
            expect($html)->toContain('max-height: 50px');
            expect($html)->toContain('overflow: hidden');
        });

        it('does not apply collapsed styles when defaultOpen is true', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root defaultOpen="{true}" collapsedHeight="50px">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Open content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->not->toContain('--collapsed-height');
            expect($html)->not->toContain('min-height: 50px');
        });

        it('renders content without hidden attribute when collapsedHeight provides peek preview', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root collapsedHeight="100px">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Peek preview content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('--collapsed-height: 100px');
            expect($html)->toContain('Peek preview content');
        });

        it('applies width styles when collapsedWidth is set and closed', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root collapsedWidth="200px">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content with collapsed width</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('--collapsed-width: 200px');
            expect($html)->toContain('min-width: 200px');
            expect($html)->toContain('max-width: 200px');
        });

        it('applies both height and width styles when both are set', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root collapsedHeight="100px" collapsedWidth="200px">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content with both constraints</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('--collapsed-height: 100px');
            expect($html)->toContain('--collapsed-width: 200px');
            expect($html)->toContain('min-height: 100px');
            expect($html)->toContain('min-width: 200px');
        });
    });

    describe('indicator', function () {
        it('renders the closed indicator by default', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root>
                    <primitives:collapsible.trigger>
                        <primitives:collapsible.indicator state="closed">Show more</primitives:collapsible.indicator>
                        <primitives:collapsible.indicator state="open">Show less</primitives:collapsible.indicator>
                    </primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('Show more');
            expect($html)->toContain('data-part="indicator-closed"');
            expect($html)->toContain('data-part="indicator-open"');
            expect($html)->toMatch('/data-part="indicator-open"[^>]*hidden|hidden[^>]*data-part="indicator-open"/');
        });

        it('renders the open indicator when defaultOpen is true', function () {
            $html = $this->renderTemplate('
                <primitives:collapsible.root defaultOpen="{true}">
                    <primitives:collapsible.trigger>
                        <primitives:collapsible.indicator state="closed">Show more</primitives:collapsible.indicator>
                        <primitives:collapsible.indicator state="open">Show less</primitives:collapsible.indicator>
                    </primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            expect($html)->toContain('Show less');
            expect($html)->toMatch('/data-part="indicator-closed"[^>]*hidden|hidden[^>]*data-part="indicator-closed"/');
        });
    });

    describe('hydration data', function () {
        it('registers component with props in hydration registry', function () {
            $this->renderTemplate('
                <primitives:collapsible.root defaultOpen="{true}" disabled="{true}">
                    <primitives:collapsible.trigger>Toggle</primitives:collapsible.trigger>
                    <primitives:collapsible.content>Content</primitives:collapsible.content>
                </primitives:collapsible.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('collapsible');
            $collapsibleData = array_values($hydrationData['collapsible'])[0];
            expect($collapsibleData['props'])->toHaveKey('defaultOpen');
            expect($collapsibleData['props']['defaultOpen'])->toBe(true);
            expect($collapsibleData['props'])->toHaveKey('disabled');
            expect($collapsibleData['props']['disabled'])->toBe(true);
        });
    });
});
