<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Domain\Model\ListCollectionItem;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

// @mago-expect lint:too-many-methods
final class ListCollectionTest extends TestCase
{
    #[Test]
    public function normalizesItemsWithDefaultValueLabelKeys(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'Option A'],
            ['value' => 'b', 'label' => 'Option B'],
        ]);

        $items = $collection->getItems();

        $this->assertCount(2, $items);
        $this->assertInstanceOf(ListCollectionItem::class, $items[0]);
        $this->assertSame('a', $items[0]->value);
        $this->assertSame('Option A', $items[0]->label);
    }

    #[Test]
    public function normalizesItemsWithCustomKeyMappings(): void
    {
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

        $this->assertSame('usr-1', $items[0]->value);
        $this->assertSame('John', $items[0]->label);
        $this->assertTrue($items[0]->disabled);
        $this->assertFalse($items[1]->disabled);
    }

    #[Test]
    public function supportsDotNotationForNestedKeyAccess(): void
    {
        $collection = new ListCollection(
            items: [
                ['meta' => ['id' => 'nested-1'], 'display' => ['title' => 'Nested Title']],
            ],
            itemToValueKey: 'meta.id',
            itemToStringKey: 'display.title',
        );

        $item = $collection->at(0);

        $this->assertSame('nested-1', $item->value);
        $this->assertSame('Nested Title', $item->label);
    }

    #[Test]
    public function cachesNormalizedItemsForPerformance(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'A'],
        ]);

        $items1 = $collection->getItems();
        $items2 = $collection->getItems();

        $this->assertSame($items1, $items2);
    }

    #[Test]
    public function preservesOriginalDataInNormalizedItems(): void
    {
        $original = ['value' => 'x', 'label' => 'X', 'customField' => 'custom'];
        $collection = new ListCollection([$original]);

        $item = $collection->at(0);

        $this->assertSame($original, $item->original);
        $this->assertSame('custom', $item->original['customField']);
    }

    #[Test]
    public function findsItemByValue(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'Option A'],
            ['value' => 'b', 'label' => 'Option B'],
        ]);

        $found = $collection->find('b');

        $this->assertInstanceOf(ListCollectionItem::class, $found);
        $this->assertSame('b', $found->value);
    }

    #[Test]
    public function returnsNullWhenValueNotFound(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'A'],
        ]);

        $this->assertNull($collection->find('nonexistent'));
        $this->assertNull($collection->find(null));
    }

    #[Test]
    public function findsMultipleItemsByValues(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
            ['value' => 'c', 'label' => 'C'],
        ]);

        $found = $collection->findMany(['a', 'c', 'nonexistent']);

        $this->assertCount(2, $found);
        $this->assertSame('a', $found[0]->value);
        $this->assertSame('c', $found[1]->value);
    }

    #[Test]
    public function skipsDisabledItemsWhenFindingFirstValue(): void
    {
        $collection = new ListCollection([
            ['value' => 'disabled-first', 'label' => 'D1', 'disabled' => true],
            ['value' => 'disabled-second', 'label' => 'D2', 'disabled' => true],
            ['value' => 'enabled', 'label' => 'E'],
        ]);

        $this->assertSame('enabled', $collection->getFirstValue());
    }

    #[Test]
    public function skipsDisabledItemsWhenFindingLastValue(): void
    {
        $collection = new ListCollection([
            ['value' => 'enabled', 'label' => 'E'],
            ['value' => 'disabled-last', 'label' => 'D', 'disabled' => true],
        ]);

        $this->assertSame('enabled', $collection->getLastValue());
    }

    #[Test]
    public function returnsNullWhenAllItemsAreDisabled(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'A', 'disabled' => true],
            ['value' => 'b', 'label' => 'B', 'disabled' => true],
        ]);

        $this->assertNull($collection->getFirstValue());
        $this->assertNull($collection->getLastValue());
    }

    #[Test]
    public function groupsItemsBySpecifiedKey(): void
    {
        $collection = new ListCollection(items: [
            ['value' => 'a1', 'label' => 'A1', 'category' => 'A'],
            ['value' => 'b1', 'label' => 'B1', 'category' => 'B'],
            ['value' => 'a2', 'label' => 'A2', 'category' => 'A'],
        ], groupByKey: 'category');

        $groups = $collection->group();

        $this->assertSame(['A', 'B'], array_keys($groups));
        $this->assertCount(2, $groups['A']);
        $this->assertCount(1, $groups['B']);
        $this->assertInstanceOf(ListCollectionItem::class, $groups['A'][0]);
    }

    #[Test]
    public function sortsGroupsByCustomOrderArray(): void
    {
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

        $this->assertSame(['third', 'first', 'second'], array_keys($groups));
    }

    #[Test]
    public function sortsGroupsAlphabeticallyWhenGroupSortIsAsc(): void
    {
        $collection = new ListCollection(
            items: [
                ['value' => 'c', 'label' => 'C', 'cat' => 'Zebra'],
                ['value' => 'a', 'label' => 'A', 'cat' => 'Apple'],
                ['value' => 'b', 'label' => 'B', 'cat' => 'Mango'],
            ],
            groupByKey: 'cat',
            groupSort: 'asc',
        );

        $this->assertSame(['Apple', 'Mango', 'Zebra'], array_keys($collection->group()));
    }

    #[Test]
    public function joinsLabelsOfNormalizedItems(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'Apple'],
            ['value' => 'b', 'label' => 'Banana'],
        ]);

        $items = $collection->findMany(['a', 'b']);
        $result = $collection->stringifyItems($items);

        $this->assertSame('Apple, Banana', $result);
    }

    #[Test]
    public function stringifyItemsUsesCustomSeparator(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
        ]);

        $result = $collection->stringifyItems($collection->getItems(), ' | ');

        $this->assertSame('A | B', $result);
    }

    #[Test]
    public function iteratesOverNormalizedItemsViaForeach(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
        ]);

        $values = [];
        foreach ($collection as $item) {
            $this->assertInstanceOf(ListCollectionItem::class, $item);
            $values[] = $item->value;
        }

        $this->assertSame(['a', 'b'], $values);
    }
}
