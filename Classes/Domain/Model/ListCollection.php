<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Model;

use IteratorAggregate;
use JsonSerializable;
use Traversable;

// @mago-expect lint:kan-defect
class ListCollection implements JsonSerializable, IteratorAggregate
{
    protected array $items = [];

    protected ?string $itemToValueKey = null;
    protected ?string $itemToStringKey = null;
    protected ?string $isItemDisabledKey = null;
    protected ?string $groupByKey = null;
    protected array|string|null $groupSort = null;

    /** @var ListCollectionItem[]|null Cached normalized items */
    private ?array $normalizedItems = null;

    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        array $items = [],
        ?string $itemToValueKey = null,
        ?string $itemToStringKey = null,
        ?string $isItemDisabledKey = null,
        ?string $groupByKey = null,
        array|string|null $groupSort = null,
    ) {
        $this->items = $items;

        $this->itemToValueKey = $itemToValueKey;
        $this->itemToStringKey = $itemToStringKey;
        $this->isItemDisabledKey = $isItemDisabledKey;
        $this->groupByKey = $groupByKey;
        $this->groupSort = $groupSort;
    }

    public function copy(?array $items = null): static
    {
        return new static(
            $items ?? $this->items,
            $this->itemToValueKey,
            $this->itemToStringKey,
            $this->isItemDisabledKey,
            $this->groupByKey,
            $this->groupSort,
        );
    }

    public function getIterator(): Traversable
    {
        yield from $this->getItems();
    }

    /**
     * Get all items as normalized ListCollectionItem objects.
     * Results are cached for performance.
     *
     * @return ListCollectionItem[]
     */
    public function getItems(): array
    {
        if ($this->normalizedItems === null) {
            $this->normalizedItems = array_map($this->normalizeItem(...), $this->items);
        }

        return $this->normalizedItems;
    }

    /**
     * Get the raw/original items without normalization.
     */
    public function getRawItems(): array
    {
        return $this->items;
    }

    /**
     * Normalize a raw item to a ListCollectionItem object.
     */
    public function normalizeItem(array|object $item): ListCollectionItem
    {
        return new ListCollectionItem(
            value: $this->getItemValue($item) ?? '',
            label: $this->stringifyItem($item) ?? '',
            disabled: $this->getItemDisabled($item),
            original: $item,
        );
    }

    public function getSize(): int
    {
        return count($this->items);
    }

    protected function getFromKey(array|object $item, ?string $key): mixed
    {
        if (!$key)
            return null;
        $segments = explode('.', $key);
        $current = $item;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current) && isset($current->{$segment})) {
                $current = $current->{$segment};
            } else {
                return null;
            }
        }

        if (is_array($current) || is_object($current)) {
            return null;
        }

        return $current;
    }

    public function getItemValue(array|object $item): ?string
    {
        if ($this->itemToValueKey) {
            return (string)($this->getFromKey($item, $this->itemToValueKey) ?? '');
        }
        return $item['value'] ?? null;
    }

    public function stringifyItem(array|object $item): ?string
    {
        if ($item === null) {
            return null;
        }
        if ($this->itemToStringKey) {
            return (string)($this->getFromKey($item, $this->itemToStringKey) ?? '');
        }
        return $item['label'] ?? $item['value'] ?? null;
    }

    public function stringifyItems(array $items, string $separator = ', '): string
    {
        $strings = [];

        foreach ($items as $item) {
            // Handle both ListCollectionItem objects and raw items
            if ($item instanceof ListCollectionItem) {
                $str = $item->label;
            } else {
                $str = $this->stringifyItem($item);
            }
            if ($str !== null && $str !== '') {
                $strings[] = $str;
            }
        }

        return implode($separator, $strings);
    }

    public function getItemDisabled(array|object|null $item): bool
    {
        if ($item === null) {
            return false;
        }
        // Handle ListCollectionItem objects
        if ($item instanceof ListCollectionItem) {
            return $item->disabled;
        }
        if ($this->isItemDisabledKey) {
            return (bool)$this->getFromKey($item, $this->isItemDisabledKey);
        }
        return (bool)($item['disabled'] ?? false);
    }

    /**
     * Find a normalized ListCollectionItem by value.
     */
    public function find(?string $value): ?ListCollectionItem
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->getItems() as $item) {
            if ($item->value === $value) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Find multiple normalized ListCollectionItems by values.
     *
     * @return ListCollectionItem[]
     */
    public function findMany(array|string $values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        $result = [];
        foreach ($values as $value) {
            $item = $this->find($value);
            if ($item) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function indexOf(?string $value): int
    {
        if ($value === null) {
            return -1;
        }

        foreach ($this->getItems() as $index => $item) {
            if ($item->value === $value) {
                return $index;
            }
        }
        return -1;
    }

    /**
     * Get a normalized ListCollectionItem at the given index.
     */
    public function at(int $index): ?ListCollectionItem
    {
        $items = $this->getItems();
        return $items[$index] ?? null;
    }

    public function has(?string $value): bool
    {
        return $this->indexOf($value) !== -1;
    }

    public function hasItem(ListCollectionItem|array|null $item): bool
    {
        if ($item === null) {
            return false;
        }
        $value = $item instanceof ListCollectionItem ? $item->value : $this->getItemValue($item);
        return $this->has($value);
    }

    public function getFirstValue(): ?string
    {
        foreach ($this->getItems() as $item) {
            if (!$item->disabled) {
                return $item->value;
            }
        }
        return null;
    }

    public function getLastValue(): ?string
    {
        $items = $this->getItems();
        for ($i = count($items) - 1; $i >= 0; $i--) {
            if (!$items[$i]->disabled) {
                return $items[$i]->value;
            }
        }
        return null;
    }

    /**
     * Group normalized items by the defined key (e.g. "category").
     * Groups contain normalized ListCollectionItem objects.
     *
     * @return array<string, ListCollectionItem[]>
     */
    public function group(): array
    {
        $normalizedItems = $this->getItems();

        if ($this->groupByKey === null) {
            return ['' => $normalizedItems];
        }

        $groups = [];
        foreach ($normalizedItems as $item) {
            // Access the original data to get the group key
            $key = (string)($this->getFromKey($item->original, $this->groupByKey) ?? '');
            $groups[$key][] = $item;
        }

        // sort groups if groupSort is defined
        if (is_array($this->groupSort)) {
            $ordered = [];
            foreach ($this->groupSort as $key) {
                if (!isset($groups[$key])) {
                    continue;
                }

                $ordered[$key] = $groups[$key];
                unset($groups[$key]);
            }
            $groups = array_merge($ordered, $groups);
        } elseif ($this->groupSort === 'asc') {
            ksort($groups);
        } elseif ($this->groupSort === 'desc') {
            krsort($groups);
        }

        return $groups;
    }

    // just for the `collection.group` access in fluid
    public function getGroup(): array
    {
        return $this->group();
    }

    public function toString(): string
    {
        $parts = [];
        foreach ($this->items as $item) {
            $parts[] = implode(':', array_filter([
                $this->getItemValue($item),
                $this->stringifyItem($item),
                $this->getItemDisabled($item) ? 'disabled' : null,
            ]));
        }
        return implode(',', $parts);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'items' => $this->items,
            'size' => $this->getSize(),
            'first' => $this->getFirstValue(),
            'last' => $this->getLastValue(),
            'itemToValueKey' => $this->itemToValueKey,
            'itemToStringKey' => $this->itemToStringKey,
            'isItemDisabledKey' => $this->isItemDisabledKey,
            'groupByKey' => $this->groupByKey,
            'groupSort' => $this->groupSort,
        ];
    }
}
