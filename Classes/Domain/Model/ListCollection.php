<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Model;

use JsonSerializable;
use IteratorAggregate;
use Traversable;

class ListCollection implements JsonSerializable, IteratorAggregate
{
    protected array $items = [];

    protected ?string $itemToValueKey = null;
    protected ?string $itemToStringKey = null;
    protected ?string $isItemDisabledKey = null;
    protected ?string $groupByKey = null;
    protected array|string|null $groupSort = null;

    public function __construct(
        array $items = [],
        ?string $itemToValueKey = null,
        ?string $itemToStringKey = null,
        ?string $isItemDisabledKey = null,
        ?string $groupByKey = null,
        array|string|null $groupSort = null
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
            $this->groupSort
        );
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getSize(): int
    {
        return count($this->items);
    }

    protected function getFromKey(array|object $item, ?string $key): mixed
    {
        if (!$key) return null;
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
            $str = $this->stringifyItem($item);
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
        if ($this->isItemDisabledKey) {
            return (bool)$this->getFromKey($item, $this->isItemDisabledKey);
        }
        return (bool)($item['disabled'] ?? false);
    }

    public function find(?string $value)
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->items as $item) {
            if ($this->getItemValue($item) === $value) {
                return $item;
            }
        }
        return null;
    }

    public function findMany(array $values): array
    {
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

        foreach ($this->items as $index => $item) {
            if ($this->getItemValue($item) === $value) {
                return $index;
            }
        }
        return -1;
    }

    public function at(int $index)
    {
        return $this->items[$index] ?? null;
    }

    public function has(?string $value): bool
    {
        return $this->indexOf($value) !== -1;
    }

    public function hasItem(?array $item): bool
    {
        if ($item === null) {
            return false;
        }
        return $this->has($this->getItemValue($item));
    }

    public function getFirstValue(): ?string
    {
        foreach ($this->items as $item) {
            if (!$this->getItemDisabled($item)) {
                return $this->getItemValue($item);
            }
        }
        return null;
    }

    public function getLastValue(): ?string
    {
        for ($i = count($this->items) - 1; $i >= 0; $i--) {
            if (!$this->getItemDisabled($this->items[$i])) {
                return $this->getItemValue($this->items[$i]);
            }
        }
        return null;
    }

    /**
     * Group items by the defined key (e.g. "category").
     *
     * @return array<string, mixed>
     */
    public function group(): array
    {
        if ($this->groupByKey === null) {
            return ['' => $this->items];
        }

        $groups = [];
        foreach ($this->items as $index => $item) {
            $key = (string)($this->getFromKey($item, $this->groupByKey) ?? '');
            // krexx($key);
            $groups[$key][] = $item;
        }

        // sort groups if groupSort is defined
        if (is_array($this->groupSort)) {
            $ordered = [];
            foreach ($this->groupSort as $key) {
                if (isset($groups[$key])) {
                    $ordered[$key] = $groups[$key];
                    unset($groups[$key]);
                }
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
