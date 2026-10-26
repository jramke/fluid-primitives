<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

use IteratorAggregate;
use Jramke\FluidPrimitives\Contracts\ConvertibleArgumentInterface;
use Jramke\FluidPrimitives\Utility\Typed;
use JsonSerializable;
use Traversable;

// TODO: can we refactor this into smaller parts?
// @mago-expect lint:kan-defect,too-many-methods,cyclomatic-complexity
/**
 * @implements IteratorAggregate<array-key, ListCollectionItem>
 */
final class ListCollection implements JsonSerializable, IteratorAggregate, ConvertibleArgumentInterface
{
    /** @var ListCollectionItem[]|null Cached normalized items */
    private ?array $normalizedItems = null;

    /**
     * @param array<array-key, array<array-key, mixed>|object> $items
     * @param array<string>|string|null $groupSort Explicit group-key order; alternatively 'asc'/'desc'
     *   to sort group keys, or null for insertion order.
     */
    public function __construct(
        protected array $items = [],
        protected ?string $itemToValueKey = null,
        protected ?string $itemToStringKey = null,
        protected ?string $isItemDisabledKey = null,
        protected ?string $groupByKey = null,
        protected array|string|null $groupSort = null,
    ) {
        if (is_string($groupSort) && $groupSort !== 'asc' && $groupSort !== 'desc') {
            throw new \InvalidArgumentException(
                'The "groupSort" argument must be either "asc", "desc", or an array of strings.',
                1_788_200_002,
            );
        }
    }

    /**
     * A collection of the given items with the default `value`, `label` and `disabled` keys and no
     * groups: what a prop declared `array|ListCollection` makes of an array. Other keys and groups
     * need the {@see \Jramke\FluidPrimitives\ViewHelpers\ListCollectionViewHelper}.
     */
    public static function fromArgument(mixed $value): static
    {
        if ($value instanceof Traversable) {
            $value = iterator_to_array($value);
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                'The items of a collection must be an array or Traversable.',
                1_788_400_006,
            );
        }

        return new self(array_map(static function (mixed $item): array|object {
            if (!is_array($item) && !is_object($item)) {
                throw new \InvalidArgumentException(
                    'Each item of a collection must be an array or object.',
                    1_788_400_007,
                );
            }

            return $item;
        }, $value));
    }

    /**
     * @param array<array-key, array<array-key, mixed>|object>|null $items
     */
    public function copy(?array $items = null): static
    {
        return new self(
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
        $this->normalizedItems ??= array_map($this->normalizeItem(...), $this->items);

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

    protected function getFromKey(array|object $item, ?string $key): string|int|float|bool|null
    {
        if (!$key) {
            return null;
        }
        $segments = explode('.', $key);
        $current = $item;

        foreach ($segments as $segment) {
            // Resolving an arbitrary dot-notation path means each intermediate value is genuinely
            // mixed - narrower typing would defeat the point of a generic nested-path lookup.
            if (is_array($current) && array_key_exists($segment, $current)) {
                // @mago-expect analysis:mixed-assignment
                $current = $current[$segment];
                continue;
            }

            // $segment is a dot-notation path fragment - dynamic property access is inherent to
            // supporting arbitrary nested object paths here, not something a rewrite would resolve.
            // @mago-expect analysis:string-member-selector
            if (is_object($current) && ($current->{$segment} ?? null) !== null) {
                // @mago-expect analysis:mixed-assignment
                $current = $current->{$segment};
                continue;
            }

            return null;
        }

        if (is_array($current) || is_object($current)) {
            return null;
        }

        /** @var string|int|float|bool|null $current */
        return $current;
    }

    public function getItemValue(array|object $item): ?string
    {
        return Typed::stringOrNull($this->getFromKey($item, $this->itemToValueKey ?? 'value'));
    }

    public function stringifyItem(array|object $item): ?string
    {
        $label = Typed::stringOrNull($this->getFromKey($item, $this->itemToStringKey ?? 'label'));
        if ($label !== null) {
            return $label;
        }
        // A plain {value} item needs no separate label; a configured itemToStringKey never falls back.
        return $this->itemToStringKey === null ? Typed::stringOrNull($this->getFromKey($item, 'value')) : '';
    }

    /**
     * @param array<ListCollectionItem|array<array-key, mixed>|object> $items
     */
    public function stringifyItems(array $items, string $separator = ', '): string
    {
        $strings = [];

        foreach ($items as $item) {
            // Handle both ListCollectionItem objects and raw items
            $str = $item instanceof ListCollectionItem ? $item->label : $this->stringifyItem($item);
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
        // Plain (bool) casts rather than Typed::bool() are deliberate here: Typed::bool() only
        // recognizes explicit boolean-keyword strings, whereas a raw item's "disabled" value should
        // be read with PHP's normal truthiness (matching how the ListCollectionItem branch above
        // reads its own already-real bool $item->disabled).
        return (bool)$this->getFromKey($item, $this->isItemDisabledKey ?? 'disabled');
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
     * @param array<string>|string $values
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
            if ($item instanceof ListCollectionItem) {
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

        // A manual counter rather than the foreach key, since getItems() preserves whatever keys the
        // raw $items array happened to use (not necessarily a sequential list) - "index" here means
        // position in iteration order, matching how at() uses it.
        $index = 0;
        foreach ($this->getItems() as $item) {
            if ($item->value === $value) {
                return $index;
            }
            $index++;
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
                if (($groups[$key] ?? null) === null) {
                    continue;
                }

                $ordered[$key] = $groups[$key];
                unset($groups[$key]);
            }
            $groups = array_merge($ordered, $groups);
        }

        if ($this->groupSort === 'asc') {
            ksort($groups);
        }

        if ($this->groupSort === 'desc') {
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

    /**
     * The actual wire shape sent to the client - the client rebuilds the collection from the items
     * and the keys, and computes everything else itself. This docblock is the authoritative source a
     * TypeScript-generation tool should read for this class's client-facing shape.
     *
     * @return array{
     *     items: array<array-key, array<array-key, mixed>|object>,
     *     itemToValueKey: string|null,
     *     itemToStringKey: string|null,
     *     isItemDisabledKey: string|null,
     *     groupByKey: string|null,
     *     groupSort: array<string>|string|null,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => array_map($this->itemForClient(...), $this->items),
            'itemToValueKey' => $this->itemToValueKey,
            'itemToStringKey' => $this->itemToStringKey,
            'isItemDisabledKey' => $this->isItemDisabledKey,
            'groupByKey' => $this->groupByKey,
            'groupSort' => $this->groupSort,
        ];
    }

    /**
     * Only what the client reads of an item: its value, label, disabled flag and group, at the paths the keys name.
     * The rest of a row, like the other columns of a database record, stays on the server instead of in the page source.
     *
     * @return array<string, mixed>
     */
    private function itemForClient(array|object $item): array
    {
        $paths = array_filter(array_unique([
            $this->itemToValueKey ?? 'value',
            $this->itemToStringKey ?? 'label',
            $this->isItemDisabledKey ?? 'disabled',
            $this->groupByKey,
        ]));

        $projected = [];
        foreach ($paths as $path) {
            $value = $this->getFromKey($item, $path);
            if ($value === null) {
                continue;
            }

            $projected = array_replace_recursive($projected, $this->nest(explode('.', $path), $value));
        }

        return $projected;
    }

    /**
     * `['meta', 'off']` and `true` become `['meta' => ['off' => true]]`.
     *
     * @param list<string> $segments
     * @return array<string, mixed>
     */
    private function nest(array $segments, string|int|float|bool $value): array
    {
        $segment = (string)array_shift($segments);

        return [$segment => $segments === [] ? $value : $this->nest($segments, $value)];
    }
}
