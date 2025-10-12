<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Generates a reference to a part of a component.
 *
 * This is used to mark parts of a component for JavaScript interaction or styling.
 * It generates data attributes that can be used to identify the part within the component's scope.
 *
 * ## Example
 * ```html
 * <div {ui:ref(name: 'button')}">Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div data-scope="my-component" data-part="button" data-hydrate-my-component="«uniqueRootId»">Click me</div>
 * ```
 *
 * You can also pass additional data attributes:
 * ```html
 * <div {ui:ref(name: 'button', data: { action: 'submit', id: '123' })}">Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div data-scope="my-component" data-part="button" data-hydrate-my-component="«uniqueRootId»" data-action="submit" data-id="123">Click me</div>
 * ```
 *
 */
class RefViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Name of the ref', true);
        $this->registerArgument('asArray', 'boolean', 'If true, the ref will be rendered as an array instead of a string of data-attributes', false, false);
        $this->registerArgument('data', 'array', 'Additional data attributes to include in the ref. Associative array with key-value pairs. Each key is prefixed with "data-".', false, []);
    }

    public function render(): mixed
    {
        if (!ComponentUtility::isComponent($this->renderingContext)) {
            throw new \RuntimeException('The ref ViewHelper can only be used inside a component context.', 1698255600);
        }

        $componentName = ComponentUtility::getComponentBaseNameFromContext($this->renderingContext);
        $rootId = ComponentUtility::getRootIdFromContext($this->renderingContext);
        if (!$rootId) {
            throw new \RuntimeException('No rootId found for component ' . ComponentUtility::getComponentFullNameFromContext($this->renderingContext) . '.', 1756025267);
        }

        $additionalData = $this->arguments['data'] ?? [];
        if (!empty($additionalData)) {
            $additionalData = array_combine(
                array_map(function ($key) {
                    return "data-{$key}";
                }, array_keys($this->arguments['data'])),
                array_values($this->arguments['data'])
            );
        }

        $attributes = new TagAttributes(
            array_merge(
                [
                    'data-scope' => $componentName,
                    'data-part' => $this->arguments['name'],
                    "data-hydrate-{$componentName}" => $rootId,
                ],
                $additionalData
            )
        );

        if ($this->arguments['asArray']) {
            return $attributes->renderAsArray();
        }

        return (string)$attributes;
    }
}
