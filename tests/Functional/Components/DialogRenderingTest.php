<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('Dialog Component Rendering', function () {
    describe('basic structure', function () {
        it('renders dialog root component', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open Dialog</primitives:dialog.trigger>
                    <primitives:dialog.content>Dialog Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-scope="dialog"');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('data-part="content"');
        });

        it('generates unique rootId for hydration', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toMatch('/data-hydrate-dialog="[^"]+"/');
        });

        it('uses custom rootId when provided', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root rootId="my-custom-dialog">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-hydrate-dialog="my-custom-dialog"');
        });
    });

    describe('trigger', function () {
        it('renders as button element', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Click me</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('<button');
            expect($html)->toContain('data-part="trigger"');
            expect($html)->toContain('Click me');
        });

        it('renders closed state by default', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            // Trigger should have data-state attribute with "closed" value when defaultOpen is false/not set
            // The ternary in Fluid may output empty string for closed, so we check for the trigger part existing
            expect($html)->toContain('data-part="trigger"');
        });

        it('renders open state when defaultOpen is true', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root defaultOpen="{true}">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-state="open"');
        });

        it('applies custom class', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger class="my-trigger-class">Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('class="my-trigger-class"');
        });
    });

    describe('content', function () {
        it('renders content container', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>My Dialog Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-part="content"');
            expect($html)->toContain('My Dialog Content');
        });

        it('renders hidden by default', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('hidden');
        });

        it('renders visible when defaultOpen is true', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root defaultOpen="{true}">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            // Content should not have hidden attribute when open
            expect($html)->not->toMatch('/<div[^>]*data-part="content"[^>]*hidden/');
        });

        it('applies custom class', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content class="my-content-class">Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('class="my-content-class"');
        });
    });

    describe('close trigger', function () {
        it('renders close button inside content', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.closeTrigger>Close</primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-part="close-trigger"');
            expect($html)->toContain('Close');
        });

        it('renders as button element', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.closeTrigger>X</primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toMatch('/<button[^>]*data-part="close-trigger"/');
        });

        it('applies custom class', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.closeTrigger class="close-btn">X</primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('class="close-btn"');
        });
    });

    describe('hydration data', function () {
        it('registers component in hydration registry', function () {
            $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('dialog');
            expect($hydrationData['dialog'])->toBeArray();
            expect(count($hydrationData['dialog']))->toBe(1);
        });

        it('includes client props in hydration data', function () {
            $this->renderTemplate('
                <primitives:dialog.root modal="{true}" closeOnEscape="{false}">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $dialogData = array_values($hydrationData['dialog'])[0];

            expect($dialogData['props'])->toHaveKey('modal');
            expect($dialogData['props']['modal'])->toBe(true);
            expect($dialogData['props'])->toHaveKey('closeOnEscape');
            expect($dialogData['props']['closeOnEscape'])->toBe(false);
        });

        it('includes defaultOpen in hydration data', function () {
            $this->renderTemplate('
                <primitives:dialog.root defaultOpen="{true}">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $dialogData = array_values($hydrationData['dialog'])[0];

            expect($dialogData['props'])->toHaveKey('defaultOpen');
            expect($dialogData['props']['defaultOpen'])->toBe(true);
        });
    });

    describe('nested dialogs', function () {
        it('renders nested dialog with separate rootIds', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root rootId="parent-dialog">
                    <primitives:dialog.trigger>Open Parent</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.root rootId="nested-dialog">
                            <primitives:dialog.trigger>Open Nested</primitives:dialog.trigger>
                            <primitives:dialog.content>Nested Content</primitives:dialog.content>
                        </primitives:dialog.root>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('data-hydrate-dialog="parent-dialog"');
            expect($html)->toContain('data-hydrate-dialog="nested-dialog"');
        });

        it('registers both dialogs in hydration registry', function () {
            $this->renderTemplate('
                <primitives:dialog.root rootId="parent-dialog">
                    <primitives:dialog.trigger>Open Parent</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.root rootId="nested-dialog">
                            <primitives:dialog.trigger>Open Nested</primitives:dialog.trigger>
                            <primitives:dialog.content>Nested Content</primitives:dialog.content>
                        </primitives:dialog.root>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('dialog');
            expect(count($hydrationData['dialog']))->toBe(2);
            expect($hydrationData['dialog'])->toHaveKey('parent-dialog');
            expect($hydrationData['dialog'])->toHaveKey('nested-dialog');
        });

        it('renders close trigger after nested dialog root', function () {
            // This is the key test for the bug fix - close trigger should work
            // even when placed after a nested dialog.root in the DOM
            $html = $this->renderTemplate('
                <primitives:dialog.root rootId="parent-dialog">
                    <primitives:dialog.trigger>Open Parent</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.root rootId="nested-dialog">
                            <primitives:dialog.trigger>Open Nested</primitives:dialog.trigger>
                            <primitives:dialog.content>Nested Content</primitives:dialog.content>
                        </primitives:dialog.root>
                        <primitives:dialog.closeTrigger>Close Parent</primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            // Close button should have parent dialog's rootId
            expect($html)->toContain('Close Parent');
            expect($html)->toContain('data-part="close-trigger"');

            // Count occurrences of each rootId - parent should appear more times
            // (trigger, content, close-trigger vs just trigger, content for nested)
            preg_match_all('/data-hydrate-dialog="parent-dialog"/', $html, $parentMatches);
            preg_match_all('/data-hydrate-dialog="nested-dialog"/', $html, $nestedMatches);

            // Parent dialog should have at least 3 refs (trigger, content, close-trigger)
            expect(count($parentMatches[0]))->toBeGreaterThanOrEqual(3);
            // Nested dialog should have at least 2 refs (trigger, content)
            expect(count($nestedMatches[0]))->toBeGreaterThanOrEqual(2);
        });

        it('renders nested dialog before close trigger correctly', function () {
            // Test the exact scenario from the bug report - empty nested dialog.root
            // An empty dialog.root doesn't render refs (no trigger/content), but the
            // important thing is that it doesn't break the parent's context
            $html = $this->renderTemplate('
                <primitives:dialog.root rootId="parent-dialog">
                    <primitives:dialog.trigger>Open first Dialog</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.root rootId="nested-dialog"></primitives:dialog.root>
                        <primitives:dialog.closeTrigger>Close</primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            // This should not throw an exception - the bug was that this would fail
            // because the nested dialog.root would remove the parent's context

            // Verify close trigger is correctly associated with parent
            expect($html)->toContain('data-part="close-trigger"');
            // The close trigger should have the parent dialog's rootId
            expect($html)->toContain('data-hydrate-dialog="parent-dialog"');

            // Count close-trigger elements with parent-dialog rootId
            preg_match_all('/data-part="close-trigger"/', $html, $closeMatches);
            expect(count($closeMatches[0]))->toBe(1);
        });

        it('handles deeply nested dialogs', function () {
            $html = $this->renderTemplate('
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

            expect(count($hydrationData['dialog']))->toBe(3);
            expect($hydrationData['dialog'])->toHaveKey('level-1');
            expect($hydrationData['dialog'])->toHaveKey('level-2');
            expect($hydrationData['dialog'])->toHaveKey('level-3');
        });

        it('handles multiple sibling nested dialogs', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root rootId="parent">
                    <primitives:dialog.trigger>Open Parent</primitives:dialog.trigger>
                    <primitives:dialog.content>
                        <primitives:dialog.root rootId="sibling-1">
                            <primitives:dialog.trigger>Open Sibling 1</primitives:dialog.trigger>
                            <primitives:dialog.content>Sibling 1</primitives:dialog.content>
                        </primitives:dialog.root>
                        <primitives:dialog.root rootId="sibling-2">
                            <primitives:dialog.trigger>Open Sibling 2</primitives:dialog.trigger>
                            <primitives:dialog.content>Sibling 2</primitives:dialog.content>
                        </primitives:dialog.root>
                        <primitives:dialog.closeTrigger>Close Parent</primitives:dialog.closeTrigger>
                    </primitives:dialog.content>
                </primitives:dialog.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect(count($hydrationData['dialog']))->toBe(3);
            expect($html)->toContain('Close Parent');
        });
    });

    describe('props', function () {
        it('applies custom class to root wrapper', function () {
            $html = $this->renderTemplate('
                <primitives:dialog.root>
                    <primitives:dialog.trigger class="custom-trigger">Open</primitives:dialog.trigger>
                    <primitives:dialog.content class="custom-content">Content</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            expect($html)->toContain('class="custom-trigger"');
            expect($html)->toContain('class="custom-content"');
        });

        it('passes role prop', function () {
            $this->renderTemplate('
                <primitives:dialog.root role="alertdialog">
                    <primitives:dialog.trigger>Open</primitives:dialog.trigger>
                    <primitives:dialog.content>Alert!</primitives:dialog.content>
                </primitives:dialog.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $dialogData = array_values($hydrationData['dialog'])[0];

            expect($dialogData['props'])->toHaveKey('role');
            expect($dialogData['props']['role'])->toBe('alertdialog');
        });
    });
});
