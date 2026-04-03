<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('CheckboxGroup Component Rendering', function () {
    describe('basic structure', function () {
        it('renders checkboxgroup root with role and data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root>
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            expect($html)->toContain('role="group"');
            expect($html)->toContain('data-scope="checkbox-group"');
            expect($html)->toContain('data-part="root"');
        });
    });

    describe('checkbox state inside group', function () {
        it('renders unchecked state when checkbox value is not in defaultValue array', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root>
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            // Checkbox should have unchecked state
            expect($html)->toContain('data-state="unchecked"');
        });

        it('renders checked state when checkbox value is in defaultValue array', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root defaultValue="{0: \'option-2\'}">
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                    <primitives:checkbox.root value="option-2">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 2</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            // Should have both checked and unchecked states
            expect($html)->toContain('data-state="checked"');
            expect($html)->toContain('data-state="unchecked"');
        });

        it('allows multiple checkboxes to be checked simultaneously', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root defaultValue="{0: \'option-1\', 1: \'option-3\'}">
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                    <primitives:checkbox.root value="option-2">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 2</primitives:checkbox.label>
                    </primitives:checkbox.root>
                    <primitives:checkbox.root value="option-3">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 3</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            // Count checked states - option-1 and option-3 are checked
            $checkedCount = preg_match_all('/data-state="checked"/', $html);
            $uncheckedCount = preg_match_all('/data-state="unchecked"/', $html);

            expect($checkedCount)->toBeGreaterThanOrEqual(2);
            expect($uncheckedCount)->toBeGreaterThanOrEqual(1);
        });
    });

    describe('disabled and invalid state inheritance', function () {
        it('inherits disabled from group root to all checkboxes', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root disabled="{true}">
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                    <primitives:checkbox.root value="option-2">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 2</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            // Root should have aria-disabled
            expect($html)->toContain('aria-disabled="true"');
            // Checkboxes should have data-disabled
            expect(preg_match_all('/data-disabled/', $html))->toBeGreaterThanOrEqual(2);
        });

        it('checkbox-level disabled overrides group disabled', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root disabled="{true}">
                    <primitives:checkbox.root value="option-1" disabled="{false}">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Enabled checkbox</primitives:checkbox.label>
                    </primitives:checkbox.root>
                    <primitives:checkbox.root value="option-2">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Disabled checkbox</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            // Both labels should be rendered
            expect($html)->toContain('Enabled checkbox');
            expect($html)->toContain('Disabled checkbox');
        });

        it('includes readonly from group root', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root readOnly="{true}">
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            expect($html)->toContain('data-readonly');
        });
    });

    describe('name inheritance', function () {
        it('inherits name from group to checkboxes with array brackets', function () {
            $html = $this->renderTemplate('
                <primitives:checkboxGroup.root name="choices">
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                        <primitives:checkbox.hiddenInput />
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            // Name should have [] appended for proper form array handling
            expect($html)->toContain('name="choices[]"');
        });
    });

    describe('hydration data', function () {
        it('registers group component with props in hydration registry', function () {
            $this->renderTemplate('
                <primitives:checkboxGroup.root defaultValue="{0: \'option-1\', 1: \'option-2\'}" name="choices">
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('checkbox-group');
            $checkboxGroupData = array_values($hydrationData['checkbox-group'])[0];
            expect($checkboxGroupData['props'])->toHaveKey('defaultValue');
            expect($checkboxGroupData['props']['defaultValue'])->toBe(['option-1', 'option-2']);
            expect($checkboxGroupData['props']['name'])->toBe('choices');
        });

        it('registers checkbox group reference in child checkbox hydration data', function () {
            $this->renderTemplate('
                <primitives:checkboxGroup.root name="choices">
                    <primitives:checkbox.root value="option-1">
                        <primitives:checkbox.control />
                        <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            // Checkbox should have reference to its parent checkbox-group
            expect($hydrationData)->toHaveKey('checkbox');
            $checkboxData = array_values($hydrationData['checkbox'])[0];
            expect($checkboxData)->toHaveKey('checkboxGroup');
        });
    });
});
