<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Utility\ComponentUtility;

describe('ComponentUtility', function () {
    describe('id', function () {
        it('generates unique IDs with prefix', function () {
            // Reset static counter by generating a few IDs
            $id1 = ComponentUtility::id();
            $id2 = ComponentUtility::id();
            $id3 = ComponentUtility::id('custom');

            expect($id1)->toStartWith('«f');
            expect($id1)->toEndWith('»');
            expect($id2)->toStartWith('«f');
            expect($id3)->toStartWith('«custom');

            // IDs should be unique
            expect($id1)->not->toBe($id2);
        });
    });

    describe('getComponentFullNameFromViewHelperName', function () {
        it('converts camelCase to lowercase-dashed', function () {
            $result = ComponentUtility::getComponentFullNameFromViewHelperName('Accordion');
            expect($result)->toBe('accordion');
        });

        it('handles multi-part names', function () {
            $result = ComponentUtility::getComponentFullNameFromViewHelperName('Accordion.Root');
            expect($result)->toBe('accordion.root');
        });

        it('handles complex names with multiple capitals', function () {
            $result = ComponentUtility::getComponentFullNameFromViewHelperName('ScrollArea.Root');
            expect($result)->toBe('scroll-area.root');
        });
    });

    describe('getComponentBaseNameFromViewHelperName', function () {
        it('extracts base name from simple component', function () {
            $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Accordion');
            expect($result)->toBe('accordion');
        });

        it('extracts base name from compound component', function () {
            $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Accordion.Root');
            expect($result)->toBe('accordion');
        });

        it('extracts base name from item component', function () {
            $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Accordion.Item');
            expect($result)->toBe('accordion');
        });

        it('skips primitives namespace', function () {
            $result = ComponentUtility::getComponentBaseNameFromViewHelperName('Primitives.Dialog.Root');
            expect($result)->toBe('dialog');
        });
    });

    describe('getSubcomponentNameFromViewHelperName', function () {
        it('returns empty string for single part names', function () {
            $result = ComponentUtility::getSubcomponentNameFromViewHelperName('Accordion');
            expect($result)->toBe('');
        });

        it('returns subcomponent name for compound names', function () {
            $result = ComponentUtility::getSubcomponentNameFromViewHelperName('Accordion.Root');
            expect($result)->toBe('root');
        });

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

        it('returns false for trigger components', function () {
            expect(ComponentUtility::isRootComponent('Accordion.ItemTrigger'))->toBeFalse();
        });

        it('handles primitives namespace - second part is dialog not root', function () {
            // Primitives.Dialog.Root has parts: ['primitives', 'dialog', 'root']
            // The check looks at $componentParts[1] which is 'dialog', not 'root'
            expect(ComponentUtility::isRootComponent('Primitives.Dialog.Root'))->toBeFalse();
        });

        it('returns false for empty string', function () {
            expect(ComponentUtility::isRootComponent(''))->toBeFalse();
        });
    });

    describe('isComposableComponent', function () {
        it('returns false for single part names', function () {
            expect(ComponentUtility::isComposableComponent('Accordion'))->toBeFalse();
        });

        it('returns true for compound names', function () {
            expect(ComponentUtility::isComposableComponent('Accordion.Root'))->toBeTrue();
            expect(ComponentUtility::isComposableComponent('Accordion.Item'))->toBeTrue();
        });

        it('returns false for empty string', function () {
            expect(ComponentUtility::isComposableComponent(''))->toBeFalse();
        });
    });

    describe('camelCaseToLowerCaseDashed', function () {
        it('converts simple camelCase', function () {
            $result = ComponentUtility::camelCaseToLowerCaseDashed('ScrollArea');
            expect($result)->toBe('scroll-area');
        });

        it('handles single word', function () {
            $result = ComponentUtility::camelCaseToLowerCaseDashed('Accordion');
            expect($result)->toBe('accordion');
        });

        it('handles multiple capitals', function () {
            $result = ComponentUtility::camelCaseToLowerCaseDashed('HTTPResponseCode');
            expect($result)->toBe('h-t-t-p-response-code');
        });
    });
});
