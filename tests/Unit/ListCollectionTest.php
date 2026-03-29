<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Domain\Model\ListCollectionItem;

describe('ListCollection', function () {
    describe('item normalization', function () {
        it('normalizes items with default value/label keys', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'Option A'],
                ['value' => 'b', 'label' => 'Option B'],
            ]);

            $items = $collection->getItems();

            expect($items)->toHaveCount(2);
            expect($items[0])->toBeInstanceOf(ListCollectionItem::class);
            expect($items[0]->value)->toBe('a');
            expect($items[0]->label)->toBe('Option A');
        });

        it('normalizes items with custom key mappings', function () {
            $collection = new ListCollection(
                items: [
                    ['id' => 'usr-1', 'name' => 'John', 'isInactive' => true],
                    ['id' => 'usr-2', 'name' => 'Jane', 'isInactive' => false],
                ],
                itemToValueKey: 'id',
                itemToStringKey: 'name',
                isItemDisabledKey: 'isInactive',
            );

            $items = $collection->getItems();

            expect($items[0]->value)->toBe('usr-1');
            expect($items[0]->label)->toBe('John');
            expect($items[0]->disabled)->toBeTrue();
            expect($items[1]->disabled)->toBeFalse();
        });

        it('supports dot notation for nested key access', function () {
            $collection = new ListCollection(
                items: [
                    ['meta' => ['id' => 'nested-1'], 'display' => ['title' => 'Nested Title']],
                ],
                itemToValueKey: 'meta.id',
                itemToStringKey: 'display.title',
            );

            $item = $collection->at(0);

            expect($item->value)->toBe('nested-1');
            expect($item->label)->toBe('Nested Title');
        });

        it('caches normalized items for performance', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'A'],
            ]);

            $items1 = $collection->getItems();
            $items2 = $collection->getItems();

            expect($items1)->toBe($items2); // Same array reference
        });

        it('preserves original data in normalized items', function () {
            $original = ['value' => 'x', 'label' => 'X', 'customField' => 'custom'];
            $collection = new ListCollection([$original]);

            $item = $collection->at(0);

            expect($item->original)->toBe($original);
            expect($item->original['customField'])->toBe('custom');
        });
    });

    describe('find operations', function () {
        it('finds item by value and returns normalized ListCollectionItem', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'Option A'],
                ['value' => 'b', 'label' => 'Option B'],
            ]);

            $found = $collection->find('b');

            expect($found)->toBeInstanceOf(ListCollectionItem::class);
            expect($found->value)->toBe('b');
            expect($found->label)->toBe('Option B');
        });

        it('returns null when value not found', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'A'],
            ]);

            expect($collection->find('nonexistent'))->toBeNull();
            expect($collection->find(null))->toBeNull();
        });

        it('finds multiple items by values', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b', 'label' => 'B'],
                ['value' => 'c', 'label' => 'C'],
            ]);

            $found = $collection->findMany(['a', 'c', 'nonexistent']);

            expect($found)->toHaveCount(2);
            expect($found[0]->value)->toBe('a');
            expect($found[1]->value)->toBe('c');
        });
    });

    describe('first/last value with disabled items', function () {
        it('skips disabled items when finding first value', function () {
            $collection = new ListCollection([
                ['value' => 'disabled-first', 'label' => 'D1', 'disabled' => true],
                ['value' => 'disabled-second', 'label' => 'D2', 'disabled' => true],
                ['value' => 'enabled', 'label' => 'E'],
            ]);

            expect($collection->getFirstValue())->toBe('enabled');
        });

        it('skips disabled items when finding last value', function () {
            $collection = new ListCollection([
                ['value' => 'enabled', 'label' => 'E'],
                ['value' => 'disabled-last', 'label' => 'D', 'disabled' => true],
            ]);

            expect($collection->getLastValue())->toBe('enabled');
        });

        it('returns null when all items are disabled', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'A', 'disabled' => true],
                ['value' => 'b', 'label' => 'B', 'disabled' => true],
            ]);

            expect($collection->getFirstValue())->toBeNull();
            expect($collection->getLastValue())->toBeNull();
        });
    });

    describe('grouping', function () {
        it('groups items by specified key', function () {
            $collection = new ListCollection(items: [
                ['value' => 'a1', 'label' => 'A1', 'category' => 'A'],
                ['value' => 'b1', 'label' => 'B1', 'category' => 'B'],
                ['value' => 'a2', 'label' => 'A2', 'category' => 'A'],
            ], groupByKey: 'category');

            $groups = $collection->group();

            expect(array_keys($groups))->toBe(['A', 'B']);
            expect($groups['A'])->toHaveCount(2);
            expect($groups['B'])->toHaveCount(1);
            expect($groups['A'][0])->toBeInstanceOf(ListCollectionItem::class);
        });

        it('sorts groups by custom order array', function () {
            $collection = new ListCollection(
                items: [
                    ['value' => 'a', 'label' => 'A', 'cat' => 'first'],
                    ['value' => 'b', 'label' => 'B', 'cat' => 'second'],
                    ['value' => 'c', 'label' => 'C', 'cat' => 'third'],
                ],
                groupByKey: 'cat',
                groupSort: ['third', 'first', 'second'],
            );

            $groups = $collection->group();

            expect(array_keys($groups))->toBe(['third', 'first', 'second']);
        });

        it('sorts groups alphabetically when groupSort is asc/desc', function () {
            $collection = new ListCollection(
                items: [
                    ['value' => 'c', 'label' => 'C', 'cat' => 'Zebra'],
                    ['value' => 'a', 'label' => 'A', 'cat' => 'Apple'],
                    ['value' => 'b', 'label' => 'B', 'cat' => 'Mango'],
                ],
                groupByKey: 'cat',
                groupSort: 'asc',
            );

            expect(array_keys($collection->group()))->toBe(['Apple', 'Mango', 'Zebra']);
        });
    });

    describe('stringifyItems', function () {
        it('joins labels of normalized ListCollectionItems', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'Apple'],
                ['value' => 'b', 'label' => 'Banana'],
            ]);

            $items = $collection->findMany(['a', 'b']);
            $result = $collection->stringifyItems($items);

            expect($result)->toBe('Apple, Banana');
        });

        it('uses custom separator', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b', 'label' => 'B'],
            ]);

            $result = $collection->stringifyItems($collection->getItems(), ' | ');

            expect($result)->toBe('A | B');
        });
    });

    describe('iteration', function () {
        it('iterates over normalized items via foreach', function () {
            $collection = new ListCollection([
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b', 'label' => 'B'],
            ]);

            $values = [];
            foreach ($collection as $item) {
                expect($item)->toBeInstanceOf(ListCollectionItem::class);
                $values[] = $item->value;
            }

            expect($values)->toBe(['a', 'b']);
        });
    });
});
