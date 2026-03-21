<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Contexts\AccordionContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;

/**
 * Tests for AccordionContext.getItemState() logic.
 *
 * We use a TestableAccordionContext that exposes setContextVariables()
 * to avoid needing full component rendering setup.
 */
class TestableAccordionContext extends AccordionContext
{
    private array $testVars = [];

    public function setContextVariables(array $vars): void
    {
        $this->testVars = $vars;
    }

    public function get(string $key): mixed
    {
        return $this->testVars[$key] ?? null;
    }
}

describe('AccordionContext', function () {
    beforeEach(function () {
        $this->context = new TestableAccordionContext();
    });

    describe('getItemState', function () {
        it('returns expanded false when item value is not in defaultValue', function () {
            $this->context->setContextVariables([
                'defaultValue' => ['item-1'],
                'disabled' => false,
            ]);

            $state = $this->context->getItemState(['value' => 'item-2']);

            expect($state->expanded)->toBeFalse();
            expect($state->disabled)->toBeFalse();
        });

        it('returns expanded true when item value is in defaultValue', function () {
            $this->context->setContextVariables([
                'defaultValue' => ['item-1', 'item-2'],
                'disabled' => false,
            ]);

            $state = $this->context->getItemState(['value' => 'item-1']);

            expect($state->expanded)->toBeTrue();
            expect($state->disabled)->toBeFalse();
        });

        it('returns disabled true when root is disabled', function () {
            $this->context->setContextVariables([
                'defaultValue' => [],
                'disabled' => true,
            ]);

            $state = $this->context->getItemState(['value' => 'item-1']);

            expect($state->expanded)->toBeFalse();
            expect($state->disabled)->toBeTrue();
        });

        it('respects item-level disabled over root disabled', function () {
            $this->context->setContextVariables([
                'defaultValue' => [],
                'disabled' => false,
            ]);

            $state = $this->context->getItemState([
                'value' => 'item-1',
                'disabled' => true,
            ]);

            expect($state->expanded)->toBeFalse();
            expect($state->disabled)->toBeTrue();
        });

        it('uses item disabled false to override root disabled', function () {
            $this->context->setContextVariables([
                'defaultValue' => [],
                'disabled' => true,
            ]);

            $state = $this->context->getItemState([
                'value' => 'item-1',
                'disabled' => false,
            ]);

            expect($state->expanded)->toBeFalse();
            expect($state->disabled)->toBeFalse();
        });

        it('handles multiple items in defaultValue', function () {
            $this->context->setContextVariables([
                'defaultValue' => ['item-1', 'item-2', 'item-3'],
                'disabled' => false,
            ]);

            expect($this->context->getItemState(['value' => 'item-1'])->expanded)->toBeTrue();
            expect($this->context->getItemState(['value' => 'item-2'])->expanded)->toBeTrue();
            expect($this->context->getItemState(['value' => 'item-3'])->expanded)->toBeTrue();
            expect($this->context->getItemState(['value' => 'item-4'])->expanded)->toBeFalse();
        });

        it('handles empty defaultValue', function () {
            $this->context->setContextVariables([
                'defaultValue' => [],
                'disabled' => false,
            ]);

            $state = $this->context->getItemState(['value' => 'item-1']);

            expect($state->expanded)->toBeFalse();
        });

        it('handles null defaultValue', function () {
            $this->context->setContextVariables([
                'defaultValue' => null,
                'disabled' => false,
            ]);

            $state = $this->context->getItemState(['value' => 'item-1']);

            expect($state->expanded)->toBeFalse();
        });
    });
});
