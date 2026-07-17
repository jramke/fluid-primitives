<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\ViewHelpers\PartIdViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

describe('PartIdViewHelper', function () {
    beforeEach(function () {
        $this->renderingContext = new RenderingContext();
        $this->variableProvider = new StandardVariableProvider();
        $this->renderingContext->setVariableProvider($this->variableProvider);

        $this->viewHelper = new PartIdViewHelper();
        $this->viewHelper->setRenderingContext($this->renderingContext);
    });

    describe('within root component context (rootId available directly)', function () {
        beforeEach(function () {
            $this->variableProvider->add('component', [
                'fullName' => 'Collapsible.Root',
            ]);
            $this->variableProvider->add('rootId', '«f1»');
            $this->variableProvider->add('context', ['ids' => []]);
        });

        it('generates deterministic ID for a part', function () {
            $this->viewHelper->setArguments([
                'part' => 'root',
                'value' => null,
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toBe('collapsible:«f1»:root');
        });

        it('generates ID with value for multi-instance parts', function () {
            $this->viewHelper->setArguments([
                'part' => 'trigger',
                'value' => 'tab-1',
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toBe('collapsible:«f1»:trigger:tab-1');
        });

        it('uses explicit ID from ids prop when provided', function () {
            $this->variableProvider->remove('context');
            $this->variableProvider->add('context', ['ids' => ['root' => 'my-custom-root-id']]);

            $this->viewHelper->setArguments([
                'part' => 'root',
                'value' => null,
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toBe('my-custom-root-id');
        });

        it('falls back to generated ID when explicit ID is empty string', function () {
            $this->variableProvider->remove('context');
            $this->variableProvider->add('context', ['ids' => ['root' => '']]);

            $this->viewHelper->setArguments([
                'part' => 'root',
                'value' => null,
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toBe('collapsible:«f1»:root');
        });

        it('uses dashed part names correctly', function () {
            $this->viewHelper->setArguments([
                'part' => 'item-trigger',
                'value' => 'item-1',
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toBe('collapsible:«f1»:item-trigger:item-1');
        });
    });

    describe('within non-root component context (rootId via context.rootId)', function () {
        beforeEach(function () {
            $this->variableProvider->add('component', [
                'fullName' => 'Accordion.Item',
            ]);
            $this->variableProvider->add('context', ['rootId' => '«f2»', 'ids' => []]);
        });

        it('generates ID using rootId from context', function () {
            $this->viewHelper->setArguments([
                'part' => 'item',
                'value' => 'my-item',
            ]);

            $result = $this->viewHelper->render();

            expect($result)->toBe('accordion:«f2»:item:my-item');
        });
    });

    describe('outside component context', function () {
        it('throws exception when used outside component', function () {
            $this->viewHelper->setArguments([
                'part' => 'root',
                'value' => null,
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
            $this->variableProvider->add('context', ['ids' => []]);
            // rootId not set

            $this->viewHelper->setArguments([
                'part' => 'root',
                'value' => null,
            ]);

            expect(fn() => $this->viewHelper->render())->toThrow(RuntimeException::class, 'No rootId found');
        });
    });
});
