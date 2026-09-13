<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Create a ListCollection from an array of items.
 *
 * This is needed for components like [Select](/docs/components/select) that expect a Zag.js [ListCollection](https://zagjs.com/guides/collection) instance.
 * This ViewHelper is a first approach to get this DX working. Currently you should format your items like `{value: string, label: string, disabled: boolean}`.
 *
 * The `itemToString`, `itemToValue`, `isItemDisabled` and `groupBy` props are currently supported as key props like `groupByKey`.
 *
 * ## Example
 * ```html
 * <ui:listCollection
 *     items="{
 *         0: {value: 'apple', label: 'Apple'},
 *         1: {value: 'banana', label: 'Banana', disabled: true}
 *     }"
 *     as="collection"
 * />
 * ```
 *
 * Grouped example:
 *
 * ```html
 * <ui:listCollection
 *     items="{
 *         0: {value: 'apple', label: 'Apple', type: 'Fruits'},
 *         1: {value: 'banana', label: 'Banana', disabled: true, type: 'Fruits'},
 *         2: {value: 'carrot', label: 'Carrot', type: 'Vegetables'},
 *         3: {value: 'broccoli', label: 'Broccoli', type: 'Vegetables'}
 *     }"
 *     as="collection"
 *     groupByKey="type"
 * />
 * ```
 */
class ListCollectionViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('as', 'string', 'Variable name to assign the result to', false, '');
        $this->registerArgument(
            'items',
            'array',
            'The items of the collection. `{value: string, label: string, disabled: boolean}`.',
            false,
            [],
        );
        $this->registerArgument('itemToValueKey', 'string', 'The key to use for the item value.', false);
        $this->registerArgument('itemToStringKey', 'string', 'The key to use for the item label.', false);
        $this->registerArgument('isItemDisabledKey', 'string', 'The key to use for the item disabled state.', false);
        $this->registerArgument('groupByKey', 'string', 'The key to use for grouping items.', false);
        $this->registerArgument('groupSort', 'array|string', 'Sorting for groups.', false);
    }

    public function render(): mixed
    {
        $items = $this->arguments['items'] ?? null;
        if (!is_array($items) && !$items instanceof \Traversable) {
            throw new \InvalidArgumentException('The "items" argument must be an array or Traversable.', 1_759_769_689);
        }

        $groupSort = $this->arguments['groupSort'];

        $normalizedItems = array_map(
            static function (mixed $item): array|object {
                if (!is_array($item) && !is_object($item)) {
                    throw new \InvalidArgumentException(
                        'Each item in the "items" argument must be an array or object.',
                        1_788_200_001,
                    );
                }
                return $item;
            },
            is_array($items) ? $items : iterator_to_array($items),
        );

        $collection = new ListCollection(
            $normalizedItems,
            Typed::stringOrNull($this->arguments['itemToValueKey']),
            Typed::stringOrNull($this->arguments['itemToStringKey']),
            Typed::stringOrNull($this->arguments['isItemDisabledKey']),
            Typed::stringOrNull($this->arguments['groupByKey']),
            is_array($groupSort) ? array_map(Typed::string(...), $groupSort) : Typed::stringOrNull($groupSort),
        );

        $as = Typed::string($this->arguments['as']);
        if ($as !== '') {
            $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
                'ListCollection ViewHelper is missing its rendering context.',
                1_788_100_013,
            );
            $renderingContext->getVariableProvider()->add($as, $collection);
            return '';
        }

        return $collection;
    }
}
