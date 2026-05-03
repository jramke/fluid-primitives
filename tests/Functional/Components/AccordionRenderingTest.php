<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('Accordion Component Rendering', function () {
    describe('basic structure', function () {
        it('renders accordion root with data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger 1</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('data-scope="accordion"');
            expect($html)->toContain('data-part="root"');
        });

        it('generates unique rootId for hydration', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toMatch('/data-hydrate-accordion="[^"]+"/');
        });

        it('renders accordion items with value attribute', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="my-unique-value">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('data-part="item"');
            expect($html)->toContain('data-value="my-unique-value"');
        });
    });

    describe('item trigger', function () {
        it('renders as button element', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Click me</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('<button');
            expect($html)->toContain('type="button"');
            expect($html)->toContain('data-part="item-trigger"');
        });

        it('has aria-disabled attribute', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('aria-disabled="false"');
        });

        it('renders disabled state correctly', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1" disabled="{true}">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('aria-disabled="true"');
            expect($html)->toContain('disabled');
            expect($html)->toContain('data-disabled');
        });
    });

    describe('item content', function () {
        it('renders content container with data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>My Content Here</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('data-part="item-content"');
            expect($html)->toContain('My Content Here');
        });
    });

    describe('multiple items', function () {
        it('renders multiple accordion items', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="first">
                        <primitives:accordion.itemTrigger>First Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>First Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                    <primitives:accordion.item value="second">
                        <primitives:accordion.itemTrigger>Second Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Second Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('data-value="first"');
            expect($html)->toContain('data-value="second"');
            expect($html)->toContain('First Trigger');
            expect($html)->toContain('Second Trigger');
        });
    });

    describe('props', function () {
        it('applies custom class to root', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root class="my-custom-class">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('class="my-custom-class"');
        });

        it('passes orientation prop through data attribute', function () {
            $html = $this->renderTemplate(<<<'FLUID'
                <primitives:accordion.root orientation="{f:constant(name: 'Jramke\FluidPrimitives\Enum\Orientation::Horizontal')}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            FLUID);

            expect($html)->toContain('data-orientation="horizontal"');
        });
    });

    describe('hydration data', function () {
        it('registers component in hydration registry', function () {
            $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('accordion');
            expect($hydrationData['accordion'])->toBeArray();
            expect(count($hydrationData['accordion']))->toBe(1);
        });

        it('includes client props in hydration data', function () {
            $this->renderTemplate('
                <primitives:accordion.root multiple="{true}" collapsible="{true}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $accordionData = array_values($hydrationData['accordion'])[0];

            expect($accordionData['props'])->toHaveKey('multiple');
            expect($accordionData['props']['multiple'])->toBe(true);
            expect($accordionData['props'])->toHaveKey('collapsible');
            expect($accordionData['props']['collapsible'])->toBe(true);
        });

        it('includes defaultValue in hydration data', function () {
            $this->renderTemplate('
                <primitives:accordion.root defaultValue="{0: \'item-1\'}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $accordionData = array_values($hydrationData['accordion'])[0];

            expect($accordionData['props'])->toHaveKey('defaultValue');
            expect($accordionData['props']['defaultValue'])->toBe(['item-1']);
        });
    });

    describe('state attributes', function () {
        it('renders closed state by default', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            expect($html)->toContain('data-state="closed"');
        });

        it('renders open state when item is in defaultValue array', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root defaultValue="{0: \'item-1\', 1: \'item-2\'}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger 1</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                    <primitives:accordion.item value="item-2">
                        <primitives:accordion.itemTrigger>Trigger 2</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 2</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                    <primitives:accordion.item value="item-3">
                        <primitives:accordion.itemTrigger>Trigger 3</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 3</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            // Items in defaultValue should be open, others closed
            // Each item has: item wrapper, trigger, content (3 parts with data-state each)
            expect(preg_match_all('/data-state="open"/', $html))->toBe(6); // 2 items x 3 parts
            expect(preg_match_all('/data-state="closed"/', $html))->toBe(3); // 1 item x 3 parts
        });

        it('inherits disabled state from root to all items', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root disabled="{true}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger 1</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                    <primitives:accordion.item value="item-2">
                        <primitives:accordion.itemTrigger>Trigger 2</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 2</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            // All triggers should be disabled
            expect(preg_match_all('/aria-disabled="true"/', $html))->toBe(2);
        });

        it('item-level disabled overrides root disabled', function () {
            $html = $this->renderTemplate('
                <primitives:accordion.root disabled="{true}">
                    <primitives:accordion.item value="item-1" disabled="{false}">
                        <primitives:accordion.itemTrigger>Enabled Item</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                    <primitives:accordion.item value="item-2">
                        <primitives:accordion.itemTrigger>Disabled Item</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 2</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            // First item explicitly enabled, second inherits disabled from root
            expect($html)->toContain('aria-disabled="false"');
            expect($html)->toContain('aria-disabled="true"');
        });
    });
});
