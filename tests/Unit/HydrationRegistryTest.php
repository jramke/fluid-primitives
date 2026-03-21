<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use TYPO3\CMS\Core\Page\AssetCollector;

describe('HydrationRegistry', function () {
    beforeEach(function () {
        // Create a mock AssetCollector that captures the JS output using PHPUnit's mocking
        $this->capturedJs = null;
        $this->capturedAttributes = null;

        $this->assetCollector = $this->createMock(AssetCollector::class);
        $this->assetCollector
            ->method('addInlineJavaScript')
            ->willReturnCallback(function ($id, $js, $attributes, $options) {
                $this->capturedJs = $js;
                $this->capturedAttributes = $attributes;
                return $this->assetCollector; // Return self for fluent interface
            });

        $this->registry = new HydrationRegistry($this->assetCollector);
    });

    describe('basic registry operations', function () {
        it('adds component data to the registry', function () {
            $this->registry->add('accordion', '«f1»', [
                'controlled' => false,
                'props' => ['multiple' => true],
            ]);

            $data = $this->registry->get('accordion', '«f1»');

            expect($data)->toBe([
                'controlled' => false,
                'props' => ['multiple' => true],
            ]);
        });

        it('returns null for non-existent entries', function () {
            $data = $this->registry->get('accordion', 'non-existent');

            expect($data)->toBeNull();
        });

        it('stores multiple components of the same type', function () {
            $this->registry->add('accordion', '«f1»', ['props' => ['id' => '«f1»']]);
            $this->registry->add('accordion', '«f2»', ['props' => ['id' => '«f2»']]);

            expect($this->registry->get('accordion', '«f1»'))->toBe(['props' => ['id' => '«f1»']]);
            expect($this->registry->get('accordion', '«f2»'))->toBe(['props' => ['id' => '«f2»']]);
        });

        it('stores multiple component types', function () {
            $this->registry->add('accordion', '«f1»', ['type' => 'accordion']);
            $this->registry->add('dialog', '«f2»', ['type' => 'dialog']);

            $all = $this->registry->getAll();

            expect($all)->toHaveKey('accordion');
            expect($all)->toHaveKey('dialog');
            expect($all['accordion']['«f1»'])->toBe(['type' => 'accordion']);
            expect($all['dialog']['«f2»'])->toBe(['type' => 'dialog']);
        });

        it('clears the registry', function () {
            $this->registry->add('accordion', '«f1»', ['props' => []]);
            $this->registry->clear();

            expect($this->registry->getAll())->toBe([]);
        });
    });

    describe('AssetCollector integration', function () {
        it('adds inline JavaScript when data is added', function () {
            $this->registry->add('accordion', '«f1»', [
                'controlled' => false,
                'props' => ['multiple' => true],
            ]);

            expect($this->capturedJs)->not->toBeNull();
            expect($this->capturedJs)->toContain('window.FluidPrimitives');
            expect($this->capturedJs)->toContain('hydrationData');
        });

        it('includes component data in the JavaScript output', function () {
            $this->registry->add('accordion', '«f1»', [
                'controlled' => false,
                'props' => ['multiple' => true],
            ]);

            expect($this->capturedJs)->toContain('"accordion"');
            expect($this->capturedJs)->toContain('"«f1»"');
            expect($this->capturedJs)->toContain('"multiple":true');
        });

        it('updates JavaScript when multiple components are added', function () {
            $this->registry->add('accordion', '«f1»', ['props' => ['id' => '«f1»']]);
            $this->registry->add('dialog', '«f2»', ['props' => ['id' => '«f2»']]);

            expect($this->capturedJs)->toContain('"accordion"');
            expect($this->capturedJs)->toContain('"dialog"');
        });

        it('omits script id attribute in non-development context', function () {
            // In Testing context, isDevelopment() returns false, so id is removed
            $this->registry->add('accordion', '«f1»', ['props' => []]);

            expect($this->capturedAttributes)->not->toHaveKey('id');
        });
    });
});
