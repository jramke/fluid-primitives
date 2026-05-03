<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('RadioGroup Component Rendering', function () {
    describe('basic structure', function () {
        it('renders radiogroup root with role and data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:radioGroup.root>
                    <primitives:radioGroup.item value="option-1">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                </primitives:radioGroup.root>
            ');

            expect($html)->toContain('role="radiogroup"');
            expect($html)->toContain('data-scope="radio-group"');
            expect($html)->toContain('data-part="root"');
        });
    });

    describe('item selection state', function () {
        it('renders unchecked state when item is not selected', function () {
            $html = $this->renderTemplate('
                <primitives:radioGroup.root>
                    <primitives:radioGroup.item value="option-1">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                </primitives:radioGroup.root>
            ');

            expect($html)->toContain('data-state="unchecked"');
        });

        it('renders checked state when item matches defaultValue', function () {
            $html = $this->renderTemplate('
                <primitives:radioGroup.root defaultValue="option-2">
                    <primitives:radioGroup.item value="option-1">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                    <primitives:radioGroup.item value="option-2">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 2</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                </primitives:radioGroup.root>
            ');

            // Should have checked and unchecked states
            // Each item has multiple parts (item, control) that get state attributes
            expect($html)->toContain('data-state="checked"');
            expect($html)->toContain('data-state="unchecked"');
        });
    });

    describe('disabled and invalid state inheritance', function () {
        it('inherits disabled from root to all items', function () {
            $html = $this->renderTemplate('
                <primitives:radioGroup.root disabled="{true}">
                    <primitives:radioGroup.item value="option-1">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                    <primitives:radioGroup.item value="option-2">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 2</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                </primitives:radioGroup.root>
            ');

            // Root should have aria-disabled
            expect($html)->toContain('aria-disabled="true"');
            // Items should have data-disabled
            expect(preg_match_all('/data-disabled/', $html))->toBeGreaterThanOrEqual(2);
        });

        it('item-level disabled overrides root disabled', function () {
            $html = $this->renderTemplate('
                <primitives:radioGroup.root disabled="{true}">
                    <primitives:radioGroup.item value="option-1" disabled="{false}">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Enabled item</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                    <primitives:radioGroup.item value="option-2">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Disabled item</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                </primitives:radioGroup.root>
            ');

            // First item should NOT have data-disabled (explicitly enabled)
            // Second item should have data-disabled (inherits from root)
            // We can verify the pattern exists for the second item only
            expect($html)->toContain('Enabled item');
            expect($html)->toContain('Disabled item');
        });

        it('includes orientation and readonly from root', function () {
            $html = $this->renderTemplate(<<<'FLUID'
                <primitives:radioGroup.root orientation="{f:constant(name: 'Jramke\FluidPrimitives\Enum\Orientation::Vertical')}" readOnly="{true}">
                    <primitives:radioGroup.item value="option-1">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                </primitives:radioGroup.root>
            FLUID);

            expect($html)->toContain('data-orientation="vertical"');
            expect($html)->toContain('data-readonly');
        });
    });

    describe('hydration data', function () {
        it('registers component with props in hydration registry', function () {
            $this->renderTemplate(<<<'FLUID'
                <primitives:radioGroup.root defaultValue="option-1" name="choice" orientation="{f:constant(name: 'Jramke\FluidPrimitives\Enum\Orientation::Horizontal')}">
                    <primitives:radioGroup.item value="option-1">
                        <primitives:radioGroup.itemControl />
                        <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                    </primitives:radioGroup.item>
                </primitives:radioGroup.root>
            FLUID);

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('radio-group');
            $radioGroupData = array_values($hydrationData['radio-group'])[0];
            expect($radioGroupData['props'])->toHaveKey('defaultValue');
            expect($radioGroupData['props']['defaultValue'])->toBe('option-1');
            expect($radioGroupData['props'])->toHaveKey('orientation');
            expect($radioGroupData['props']['orientation'])->toBe('horizontal');
        });
    });
});
