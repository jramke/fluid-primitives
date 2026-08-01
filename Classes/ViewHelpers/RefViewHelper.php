<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\EnumUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Generates a reference to a part of a component.
 *
 * This is used to mark parts of a component for JavaScript interaction or styling.
 * It generates the element `id` (using the same deterministic formula as `ui:partId()`)
 * along with `data-scope` and `data-part` attributes.
 *
 * ## Example
 * ```html
 * <div {ui:ref(name: 'button')}">Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div id="my-component:«uniqueRootId»:button" data-scope="my-component" data-part="button">Click me</div>
 * ```
 *
 * For multi-instance parts (e.g. accordion items, tab panels) pass a `value:` discriminator:
 * ```html
 * <div {ui:ref(name: 'item', value: value)}">...</div>
 * ```
 * This will generate: `id="accordion:«f1»:item:my-value" data-scope="accordion" data-part="item"`
 *
 * You can also pass additional data attributes:
 * ```html
 * <div {ui:ref(name: 'button', data: { action: 'submit' })}">Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div id="..." data-scope="my-component" data-part="button" data-action="submit">Click me</div>
 * ```
 *
 * Use `withId: false` to suppress the `id` attribute (e.g. for parts that have no unique
 * discriminator and would produce duplicate IDs):
 * ```html
 * <div {ui:ref(name: 'item-group-label', withId: false)}>...</div>
 * ```
 */
class RefViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Name of the ref', true);
        $this->registerArgument(
            'asArray',
            'boolean',
            'If true, the ref will be rendered as an array instead of a string of data-attributes',
            false,
            false,
        );
        $this->registerArgument(
            'data',
            'array',
            'Additional data attributes to include in the ref. Associative array with key-value pairs. Each key is prefixed with "data-".',
            false,
            [],
        );
        $this->registerArgument(
            'value',
            'string|BackedEnum|UnitEnum|null|array',
            'Optional discriminator for multi-instance parts (e.g. accordion items, tab triggers). Mirrors the value argument of ui:partId().',
            false,
            null,
        );
        $this->registerArgument(
            'withId',
            'boolean',
            'Whether to emit the id attribute. Set to false for parts that have no unique discriminator and would produce duplicate IDs.',
            false,
            true,
        );
    }

    public function render(): mixed
    {
        if (!ComponentUtility::isComponent($this->renderingContext)) {
            throw new \RuntimeException('The ref ViewHelper can only be used inside a component context.', 1698255600);
        }

        $componentName = ComponentUtility::getComponentBaseNameFromContext($this->renderingContext);
        $rootId = ComponentUtility::getRootIdFromContext($this->renderingContext);
        if ($rootId === '' || $rootId === '0') {
            throw new \RuntimeException(
                'No rootId found for component ' .
                ComponentUtility::getComponentFullNameFromContext($this->renderingContext) .
                '.',
                1756025267,
            );
        }

        $part = $this->arguments['name'];
        $value = EnumUtility::normalize($this->arguments['value']);

        $additionalData = $this->arguments['data'];
        if ($additionalData !== []) {
            $additionalData = array_combine(
                array_map(static fn($key) => "data-{$key}", array_keys($this->arguments['data'])),
                array_values($this->arguments['data']),
            );
        }

        $baseAttributes = [
            'data-scope' => $componentName,
            'data-part' => $part,
        ];

        if ($this->arguments['withId']) {
            $ids = $this->renderingContext->getVariableProvider()->getByPath('context.ids') ?? [];
            $idsArray = is_array($ids) ? $ids : [];
            $id = ComponentUtility::generatePartId($componentName, $rootId, $part, $value, $idsArray);
            $baseAttributes = array_merge(['id' => $id], $baseAttributes);
        }

        $attributes = new TagAttributes(array_merge($baseAttributes, $additionalData));

        if ($this->arguments['asArray']) {
            return $attributes->renderAsArray();
        }

        return (string)$attributes;
    }
}

