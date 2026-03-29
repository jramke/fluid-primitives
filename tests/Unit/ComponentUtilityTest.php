<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Utility\ComponentUtility;

describe('ComponentUtility', function () {
    describe('id', function () {
        it('generates unique IDs with prefix', function () {
            $id1 = ComponentUtility::id();
            $id2 = ComponentUtility::id();
            $id3 = ComponentUtility::id('custom');

            expect($id1)->toStartWith('«f');
            expect($id1)->toEndWith('»');
            expect($id3)->toStartWith('«custom');
            expect($id1)->not->toBe($id2);
        });
    });

    describe('getComponentFullNameFromViewHelperName', function () {
        it('handles complex names with multiple capitals', function () {
            $result = ComponentUtility::getComponentFullNameFromViewHelperName('ScrollArea.Root');
            expect($result)->toBe('scroll-area.root');
        });
    });

    describe('getComponentBaseNameFromViewHelperName', function () {
        it('skips primitives namespace', function () {
            $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Primitives.Dialog.Root');
            expect($result)->toBe('dialog');
        });

        it('extracts base name from compound component', function () {
            $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Accordion.Item');
            expect($result)->toBe('accordion');
        });
    });

    describe('getSubcomponentNameFromViewHelperName', function () {
        it('returns full subcomponent path for deep names', function () {
            $result = ComponentUtility::getSubcomponentNameFromViewHelperName('Accordion.Item.Trigger');
            expect($result)->toBe('item.trigger');
        });
    });

    describe('isRootComponent', function () {
        it('returns true for single part component name', function () {
            expect(ComponentUtility::isRootComponent('Collapsible'))->toBeTrue();
        });

        it('returns true when second part is Root', function () {
            expect(ComponentUtility::isRootComponent('Accordion.Root'))->toBeTrue();
        });

        it('returns false for item components', function () {
            expect(ComponentUtility::isRootComponent('Accordion.Item'))->toBeFalse();
        });

        it('handles primitives namespace - second part is dialog not root', function () {
            // Primitives.Dialog.Root has parts: ['primitives', 'dialog', 'root']
            // The check looks at $componentParts[1] which is 'dialog', not 'root'
            expect(ComponentUtility::isRootComponent('Primitives.Dialog.Root'))->toBeFalse();
        });
    });
});
