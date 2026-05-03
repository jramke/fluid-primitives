<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;

describe('NumberInput Component Rendering', function () {
    beforeEach(function () {
        HydrationRegistry::getInstance()->clear();
    });

    describe('translations', function () {
        it('renders english labels by default', function () {
            $html = $this->renderTemplate('
                <primitives:numberInput.root>
                    <primitives:numberInput.control>
                        <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                        <primitives:numberInput.input />
                        <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                    </primitives:numberInput.control>
                </primitives:numberInput.root>
            ');

            expect($html)->toContain('aria-label="Increment value"');
            expect($html)->toContain('aria-label="Decrement value"');
        });

        it('renders german labels when locale is german', function () {
            $this->setRequestLocale('de_DE');

            $html = $this->renderTemplate('
                <primitives:numberInput.root>
                    <primitives:numberInput.control>
                        <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                        <primitives:numberInput.input />
                        <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                    </primitives:numberInput.control>
                </primitives:numberInput.root>
            ');

            expect($html)->toContain('aria-label="Wert erhöhen"');
            expect($html)->toContain('aria-label="Wert verringern"');
        });

        it('prefers prop overrides over localized defaults', function () {
            $this->setRequestLocale('de_DE');

            $html = $this->renderTemplate('
                <primitives:numberInput.root translations="{incrementLabel: \'Steigern\', decrementLabel: \'Senken\'}">
                    <primitives:numberInput.control>
                        <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                        <primitives:numberInput.input />
                        <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                    </primitives:numberInput.control>
                </primitives:numberInput.root>
            ');

            expect($html)->toContain('aria-label="Steigern"');
            expect($html)->toContain('aria-label="Senken"');
        });

        it('includes merged translations in hydration data', function () {
            $this->setRequestLocale('de_DE');

            $this->renderTemplate('
                <primitives:numberInput.root translations="{incrementLabel: \'Steigern\'}">
                    <primitives:numberInput.control>
                        <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                        <primitives:numberInput.input />
                        <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                    </primitives:numberInput.control>
                </primitives:numberInput.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $numberInputData = array_values($hydrationData['number-input'])[0];

            expect($numberInputData['props']['translations'])->toBe([
                'incrementLabel' => 'Steigern',
                'decrementLabel' => 'Wert verringern',
            ]);
        });
    });
});
