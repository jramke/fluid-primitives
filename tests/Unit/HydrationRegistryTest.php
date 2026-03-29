<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use TYPO3\CMS\Core\Page\AssetCollector;

describe('HydrationRegistry', function () {
    beforeEach(function () {
        $this->capturedJs = null;
        $this->capturedAttributes = null;

        $this->assetCollector = $this->createMock(AssetCollector::class);
        $this->assetCollector
            ->method('addInlineJavaScript')
            ->willReturnCallback(function ($id, $js, $attributes, $options) {
                $this->capturedJs = $js;
                $this->capturedAttributes = $attributes;
                return $this->assetCollector;
            });

        $this->registry = new HydrationRegistry($this->assetCollector);
    });

    describe('registry operations', function () {
        it('stores and retrieves multiple component types', function () {
            $this->registry->add('accordion', '«f1»', ['type' => 'accordion']);
            $this->registry->add('dialog', '«f2»', ['type' => 'dialog']);

            $all = $this->registry->getAll();

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
        it('adds inline JavaScript with component data', function () {
            $this->registry->add('accordion', '«f1»', [
                'controlled' => false,
                'props' => ['multiple' => true],
            ]);

            expect($this->capturedJs)->toContain('window.FluidPrimitives');
            expect($this->capturedJs)->toContain('"accordion"');
            expect($this->capturedJs)->toContain('"«f1»"');
            expect($this->capturedJs)->toContain('"multiple":true');
        });
    });
});
