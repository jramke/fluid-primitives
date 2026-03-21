<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\ViewHelpers\RefViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

describe('RefViewHelper', function () {
    beforeEach(function () {
        $this->renderingContext = new RenderingContext();
        $this->variableProvider = new StandardVariableProvider();
        $this->renderingContext->setVariableProvider($this->variableProvider);

        $this->viewHelper = new RefViewHelper();
        $this->viewHelper->setRenderingContext($this->renderingContext);
    });

    describe('within component context', function () {
        beforeEach(function () {
            // Set up component context
            $this->variableProvider->add('component', [
                'fullName' => 'Collapsible.Root',
            ]);
            $this->variableProvider->add('rootId', '«f1»');
        });

        it('renders data attributes for a ref', function () {
            $this->viewHelper->setArguments([
                'name' => 'trigger',
                'asArray' => false,
                'data' => [],
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toContain('data-scope="collapsible"');
            expect($result)->toContain('data-part="trigger"');
            expect($result)->toContain('data-hydrate-collapsible="«f1»"');
        });

        it('renders root ref correctly', function () {
            $this->viewHelper->setArguments([
                'name' => 'root',
                'asArray' => false,
                'data' => [],
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toContain('data-part="root"');
            expect($result)->toContain('data-hydrate-collapsible="«f1»"');
        });

        it('includes additional data attributes', function () {
            $this->viewHelper->setArguments([
                'name' => 'trigger',
                'asArray' => false,
                'data' => [
                    'action' => 'toggle',
                    'state' => 'collapsed',
                ],
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toContain('data-action="toggle"');
            expect($result)->toContain('data-state="collapsed"');
        });

        it('returns array when asArray is true', function () {
            $this->viewHelper->setArguments([
                'name' => 'trigger',
                'asArray' => true,
                'data' => [],
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toBeArray();
            expect($result)->toHaveKey('data-scope');
            expect($result)->toHaveKey('data-part');
            expect($result)->toHaveKey('data-hydrate-collapsible');
            expect($result['data-scope'])->toBe('collapsible');
            expect($result['data-part'])->toBe('trigger');
            expect($result['data-hydrate-collapsible'])->toBe('«f1»');
        });

        it('handles accordion component name correctly', function () {
            // Override the component context for this test
            // Accordion.Item is NOT a root component, so needs context.rootId
            $this->variableProvider->remove('component');
            $this->variableProvider->remove('rootId');
            $this->variableProvider->add('component', [
                'fullName' => 'Accordion.Item',
            ]);
            $this->variableProvider->add('context', [
                'rootId' => '«f1»',
            ]);

            $this->viewHelper->setArguments([
                'name' => 'item',
                'asArray' => false,
                'data' => [],
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toContain('data-scope="accordion"');
        });

        it('handles primitives namespace correctly', function () {
            // Override the component context for this test
            // Primitives.Dialog.Root - the second part is 'dialog' not 'root', so NOT a root component
            // Therefore it needs context.rootId
            $this->variableProvider->remove('component');
            $this->variableProvider->remove('rootId');
            $this->variableProvider->add('component', [
                'fullName' => 'Primitives.Dialog.Root',
            ]);
            $this->variableProvider->add('context', [
                'rootId' => '«f1»',
            ]);

            $this->viewHelper->setArguments([
                'name' => 'root',
                'asArray' => false,
                'data' => [],
            ]);

            $result = $this->viewHelper->render();

            // Should extract 'dialog' as base name, skipping 'primitives'
            expect($result)->toContain('data-scope="dialog"');
        });
    });

    describe('outside component context', function () {
        it('throws exception when used outside component', function () {
            // No component context set
            $this->viewHelper->setArguments([
                'name' => 'trigger',
                'asArray' => false,
                'data' => [],
            ]);

            expect(fn() => $this->viewHelper->render())
                ->toThrow(RuntimeException::class, 'can only be used inside a component context');
        });
    });

    describe('missing rootId', function () {
        it('throws exception when rootId is missing', function () {
            $this->variableProvider->add('component', [
                'fullName' => 'Collapsible.Root',
            ]);
            // rootId not set

            $this->viewHelper->setArguments([
                'name' => 'trigger',
                'asArray' => false,
                'data' => [],
            ]);

            expect(fn() => $this->viewHelper->render())
                ->toThrow(RuntimeException::class, 'No rootId found');
        });
    });
});
