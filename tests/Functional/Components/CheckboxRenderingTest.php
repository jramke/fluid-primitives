<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('Checkbox Component Rendering', function () {
    describe('basic structure', function () {
        it('renders checkbox root as label with data attributes', function () {
            $html = $this->renderTemplate('
                <primitives:checkbox.root>
                    <primitives:checkbox.control>
                        <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                    </primitives:checkbox.control>
                    <primitives:checkbox.label>Accept terms</primitives:checkbox.label>
                </primitives:checkbox.root>
            ');

            expect($html)->toContain('<label');
            expect($html)->toContain('data-scope="checkbox"');
            expect($html)->toContain('data-part="root"');
        });
    });

    describe('three-state checkbox behavior', function () {
        it('renders unchecked state by default', function () {
            $html = $this->renderTemplate('
                <primitives:checkbox.root>
                    <primitives:checkbox.control>
                        <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                    </primitives:checkbox.control>
                </primitives:checkbox.root>
            ');

            expect($html)->toContain('data-state="unchecked"');
            // Indicator should be hidden when unchecked (hidden attribute may appear before or after data-part)
            expect($html)->toMatch('/data-part="indicator".*hidden|hidden.*data-part="indicator"/s');
        });

        it('renders checked state when defaultChecked is true', function () {
            $html = $this->renderTemplate('
                <primitives:checkbox.root defaultChecked="{true}">
                    <primitives:checkbox.control>
                        <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                    </primitives:checkbox.control>
                </primitives:checkbox.root>
            ');

            expect($html)->toContain('data-state="checked"');
            // Indicator should be visible when checked
            expect($html)->not->toMatch('/data-part="indicator"[^>]*hidden/');
        });

        it('renders indeterminate state when defaultChecked is "indeterminate"', function () {
            $html = $this->renderTemplate('
                <primitives:checkbox.root defaultChecked="indeterminate">
                    <primitives:checkbox.control>
                        <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                    </primitives:checkbox.control>
                </primitives:checkbox.root>
            ');

            expect($html)->toContain('data-state="indeterminate"');
            // Indicator should be visible for indeterminate
            expect($html)->not->toMatch('/data-part="indicator"[^>]*hidden/');
        });
    });

    describe('data attributes from context', function () {
        it('includes disabled, readonly, invalid, required attributes', function () {
            $html = $this->renderTemplate('
                <primitives:checkbox.root defaultChecked="{true}" disabled="{true}" readOnly="{true}" invalid="{true}" required="{true}">
                    <primitives:checkbox.control>
                        <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                    </primitives:checkbox.control>
                </primitives:checkbox.root>
            ');

            expect($html)->toContain('data-disabled');
            expect($html)->toContain('data-readonly');
            expect($html)->toContain('data-invalid');
            expect($html)->toContain('data-required');
        });
    });

    describe('hydration data', function () {
        it('registers component with props in hydration registry', function () {
            $this->renderTemplate('
                <primitives:checkbox.root defaultChecked="{true}" name="accept" value="yes">
                    <primitives:checkbox.control>
                        <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                    </primitives:checkbox.control>
                </primitives:checkbox.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('checkbox');
            $checkboxData = array_values($hydrationData['checkbox'])[0];
            expect($checkboxData['props'])->toHaveKey('defaultChecked');
            expect($checkboxData['props']['defaultChecked'])->toBe(true);
            expect($checkboxData['props'])->toHaveKey('name');
            expect($checkboxData['props']['name'])->toBe('accept');
        });
    });
});
