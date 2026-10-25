<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Turns a list of items into the collection that [Select](/docs/components/select) and [Combobox](/docs/components/combobox) take as `collection`. The client rebuilds it as a Zag.js [ListCollection](https://zagjs.com/guides/collection).
 *
 * ```html
 * <ui:listCollection
 *     items="{
 *         0: {value: 'de', label: 'Germany'},
 *         1: {value: 'fr', label: 'France'},
 *         2: {value: 'ch', label: 'Switzerland', disabled: true}
 *     }"
 *     as="countries"
 * />
 *
 * <ui:select.root collection="{countries}">...</ui:select.root>
 * ```
 *
 * `collection` takes a plain array of items with a `value` and a `label` as well. Use the ViewHelper when your items use other keys or when you want groups.
 *
 * ## Group the items
 *
 * Name the key that holds the group with `groupByKey`:
 *
 * ```html
 * <ui:listCollection
 *     items="{
 *         0: {value: 'de', label: 'Germany', region: 'Europe'},
 *         1: {value: 'fr', label: 'France', region: 'Europe'},
 *         2: {value: 'us', label: 'United States', region: 'North America'},
 *         3: {value: 'ca', label: 'Canada', region: 'North America'}
 *     }"
 *     as="countries"
 *     groupByKey="region"
 * />
 * ```
 *
 * `groupSort` takes `asc`, `desc` or a list of group keys in the order you want.
 *
 * ## Use other item keys
 *
 * An item is an array or an object with a `value`, a `label` and optionally `disabled`. When your items use other keys, name them with `itemToValueKey`, `itemToStringKey` and `isItemDisabledKey`. A key can be a dot path such as `country.isoCode`.
 *
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

    public function render(): string|ListCollection
    {
        // Narrowed immediately below via is_array()/instanceof Traversable - no single Typed:: call
        // covers that union.
        // @mago-expect analysis:mixed-assignment
        $items = $this->arguments['items'] ?? null;
        if (!is_array($items) && !$items instanceof \Traversable) {
            throw new \InvalidArgumentException('The "items" argument must be an array or Traversable.', 1_759_769_689);
        }

        // Fluid's own registered type is 'array|string' - stays mixed here since it's consumed via
        // an is_array()/Typed::stringOrNull() branch below, not narrowed to one or the other upfront.
        // @mago-expect analysis:mixed-assignment
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
