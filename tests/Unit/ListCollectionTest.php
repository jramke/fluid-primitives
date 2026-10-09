<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Domain\Dto\ListCollectionItem;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function preservesOriginalDataInNormalizedItems(): void
    {
        $original = ['value' => 'x', 'label' => 'X', 'customField' => 'custom'];
        $collection = new ListCollection([$original]);

        $item = $collection->at(0);

        $this->assertSame($original, $item->original);
        $this->assertSame('custom', $item->original['customField']);
    }

    #[Test]
    public function findsItemsByValue(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
            ['value' => 'c', 'label' => 'C'],
        ]);

        $found = $collection->find('b');
        $this->assertInstanceOf(ListCollectionItem::class, $found);
        $this->assertSame('b', $found->value);
        $this->assertNull($collection->find('nonexistent'));
        $this->assertNull($collection->find(null));

        $many = $collection->findMany(['a', 'c', 'nonexistent']);
        $this->assertCount(2, $many);
        $this->assertSame('a', $many[0]->value);
        $this->assertSame('c', $many[1]->value);
    }

    #[Test]
    public function firstAndLastValueSkipDisabledItems(): void
    {
        $collection = new ListCollection([
            ['value' => 'disabled-first', 'label' => 'D1', 'disabled' => true],
            ['value' => 'first-enabled', 'label' => 'E1'],
            ['value' => 'last-enabled', 'label' => 'E2'],
            ['value' => 'disabled-last', 'label' => 'D2', 'disabled' => true],
        ]);
        $this->assertSame('first-enabled', $collection->getFirstValue());
        $this->assertSame('last-enabled', $collection->getLastValue());

        $allDisabled = new ListCollection([
            ['value' => 'a', 'label' => 'A', 'disabled' => true],
            ['value' => 'b', 'label' => 'B', 'disabled' => true],
        ]);
        $this->assertNull($allDisabled->getFirstValue());
        $this->assertNull($allDisabled->getLastValue());
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
    public function sortsGroupsByACustomOrderOrAlphabetically(): void
    {
        $items = [
            ['value' => 'c', 'label' => 'C', 'cat' => 'Zebra'],
            ['value' => 'a', 'label' => 'A', 'cat' => 'Apple'],
            ['value' => 'b', 'label' => 'B', 'cat' => 'Mango'],
        ];

        $custom = new ListCollection(items: $items, groupByKey: 'cat', groupSort: ['Mango', 'Zebra', 'Apple']);
        $this->assertSame(['Mango', 'Zebra', 'Apple'], array_keys($custom->group()));

        $alphabetical = new ListCollection(items: $items, groupByKey: 'cat', groupSort: 'asc');
        $this->assertSame(['Apple', 'Mango', 'Zebra'], array_keys($alphabetical->group()));
    }

    #[Test]
    public function stringifiesItemsByTheirLabels(): void
    {
        $collection = new ListCollection([
            ['value' => 'a', 'label' => 'Apple'],
            ['value' => 'b', 'label' => 'Banana'],
        ]);

        $this->assertSame('Apple, Banana', $collection->stringifyItems($collection->findMany(['a', 'b'])));
        $this->assertSame('Apple | Banana', $collection->stringifyItems($collection->getItems(), ' | '));
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

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    public static function clientWireCases(): iterable
    {
        $cases = json_decode(
            (string)file_get_contents(__DIR__ . '/../Fixtures/list-collection.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($cases as $case) {
            yield $case['name'] => [$case['wire'], $case['expected']];
        }
    }

    /**
     * `tests/Client/list-collection.test.ts` reads the same file: what the server renders and what the client works
     * with have to be the same items.
     *
     * @param array<string, mixed> $wire
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('clientWireCases')]
    public function agreesWithTheClientOnWhatTheWireShapeMeans(array $wire, array $expected): void
    {
        $collection = new ListCollection(
            $wire['items'],
            $wire['itemToValueKey'],
            $wire['itemToStringKey'],
            $wire['isItemDisabledKey'],
            $wire['groupByKey'],
            $wire['groupSort'],
        );

        $this->assertSame($wire, json_decode(
            json_encode($collection, JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));

        $items = $collection->getItems();
        $this->assertSame($expected['values'], array_map(
            static fn(ListCollectionItem $item): string => $item->value,
            $items,
        ));
        $this->assertSame($expected['labels'], array_map(
            static fn(ListCollectionItem $item): string => $item->label,
            $items,
        ));
        $this->assertSame($expected['disabled'], array_map(
            static fn(ListCollectionItem $item): bool => $item->disabled,
            $items,
        ));

        $groups = [];
        foreach ($collection->group() as $key => $group) {
            $groups[] = [(string)$key, array_map(static fn(ListCollectionItem $item): string => $item->value, $group)];
        }
        $this->assertSame($expected['groups'], $groups);
    }
}
