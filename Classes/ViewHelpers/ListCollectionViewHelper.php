<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
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
        $this->registerArgument('as', 'string', 'Variable name to assign the result to', false);
        $this->registerArgument('items', 'ListCollectionItem', 'The items of the collection. `{value: string, label: string, disabled: boolean}`', true);
        $this->registerArgument('itemToValueKey', 'string', 'The key to use for the item value.', false);
        $this->registerArgument('itemToStringKey', 'string', 'The key to use for the item label.', false);
        $this->registerArgument('isItemDisabledKey', 'string', 'The key to use for the item disabled state.', false);
        $this->registerArgument('groupByKey', 'string', 'The key to use for grouping items.', false);
        $this->registerArgument('groupSort', 'array|string', 'Sorting for groups.', false);
    }

    public function render(): mixed
    {
        $items = $this->arguments['items'] ?? null;
        if (!is_array($items) && !($items instanceof \Traversable)) {
            throw new \InvalidArgumentException('The "items" argument must be an array or Traversable.', 1759769689);
        }

        $collection = new ListCollection(
            $items,
            $this->arguments['itemToValueKey'] ?? null,
            $this->arguments['itemToStringKey'] ?? null,
            $this->arguments['isItemDisabledKey'] ?? null,
            $this->arguments['groupByKey'] ?? null,
            $this->arguments['groupSort'] ?? null,
        );

        $as = $this->arguments['as'] ?? '';

        if (!empty($as)) {
            $this->renderingContext->getVariableProvider()->add($as, $collection);
            return '';
        } else {
            return $collection;
        }
    }
}
