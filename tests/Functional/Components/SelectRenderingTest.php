<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('Select Component Rendering', function () {
    describe('basic structure', function () {
        it('renders select root with data attributes', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
                ['value' => 'opt-2', 'label' => 'Option 2'],
            ]);

            $html = $this->renderTemplate('
                <primitives:select.root collection="{collection}">
                    <primitives:select.control>
                        <primitives:select.trigger>Select an option</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            expect($html)->toContain('data-scope="select"');
            expect($html)->toContain('data-part="root"');
        });

        it('renders trigger as combobox button', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
            ]);

            $html = $this->renderTemplate('
                <primitives:select.root collection="{collection}">
                    <primitives:select.control>
                        <primitives:select.trigger>Select</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            expect($html)->toContain('<button');
            expect($html)->toContain('type="button"');
            expect($html)->toContain('role="combobox"');
            expect($html)->toContain('aria-haspopup="listbox"');
        });
    });

    describe('defaultValue normalization', function () {
        it('normalizes string defaultValue to array in hydration data', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
                ['value' => 'opt-2', 'label' => 'Option 2'],
            ]);

            $this->renderTemplate('
                <primitives:select.root collection="{collection}" defaultValue="opt-1">
                    <primitives:select.control>
                        <primitives:select.trigger>Select</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $selectData = array_values($hydrationData['select'])[0];

            // String value should be wrapped in array for Zag.js
            expect($selectData['props']['defaultValue'])->toBe(['opt-1']);
        });

        it('passes array defaultValue as-is in hydration data', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
                ['value' => 'opt-2', 'label' => 'Option 2'],
            ]);

            $this->renderTemplate('
                <primitives:select.root collection="{collection}" defaultValue="{0: \'opt-1\', 1: \'opt-2\'}" multiple="{true}">
                    <primitives:select.control>
                        <primitives:select.trigger>Select</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $selectData = array_values($hydrationData['select'])[0];

            expect($selectData['props']['defaultValue'])->toBe(['opt-1', 'opt-2']);
        });

        it('excludes defaultValue from hydration data when empty', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
            ]);

            $this->renderTemplate('
                <primitives:select.root collection="{collection}">
                    <primitives:select.control>
                        <primitives:select.trigger>Select</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $selectData = array_values($hydrationData['select'])[0];

            // defaultValue should not be present (null excluded)
            expect($selectData['props'])->not->toHaveKey('defaultValue');
        });
    });

    describe('state attributes', function () {
        it('renders state attribute on trigger and control', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
            ]);

            $html = $this->renderTemplate('
                <primitives:select.root collection="{collection}">
                    <primitives:select.control>
                        <primitives:select.trigger>Select</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            // Both control and trigger should have state attribute
            expect($html)->toMatch('/data-part="control"[^>]*data-state=/');
            expect($html)->toMatch('/data-part="trigger"[^>]*data-state=/');
        });

        it('renders open state when defaultOpen is true', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
            ]);

            $html = $this->renderTemplate('
                <primitives:select.root collection="{collection}" defaultOpen="{true}">
                    <primitives:select.control>
                        <primitives:select.trigger>Select</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            expect($html)->toContain('data-state="open"');
        });
    });

    describe('hydration data', function () {
        it('registers component with all client props', function () {
            $collection = new ListCollection([
                ['value' => 'opt-1', 'label' => 'Option 1'],
            ]);

            $this->renderTemplate('
                <primitives:select.root collection="{collection}" disabled="{true}" multiple="{true}" closeOnSelect="{false}">
                    <primitives:select.control>
                        <primitives:select.trigger>Select</primitives:select.trigger>
                    </primitives:select.control>
                </primitives:select.root>
            ', ['collection' => $collection]);

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('select');
            $selectData = array_values($hydrationData['select'])[0];
            expect($selectData['props']['disabled'])->toBe(true);
            expect($selectData['props']['multiple'])->toBe(true);
            expect($selectData['props']['closeOnSelect'])->toBe(false);
        });
    });
});
