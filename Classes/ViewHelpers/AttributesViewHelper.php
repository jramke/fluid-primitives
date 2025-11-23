<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders arbitrary HTML attributes.
 * 
 * When this ViewHelper is used inside a component, all attributes that are not defined as props will be collected and made available via this ViewHelper.
 * 
 * ## Examples
 * 
 * ### Usage on HTML elements
 * ```html
 * <ui:button class="my-button" data-test="123" disabled />
 * ```
 * Inside the button component, you can use this ViewHelper to render the attributes:
 * ```html
 * <button {ui:attributes()}></button>
 * ```
 * This will render:
 * ```html
 * <button class="my-button" data-test="123" disabled></button>
 * ```
 * 
 * ### Usage on other components
 * When you need to pass the attributes to another component, you can use its attributes prop. 
 * This prop is automatically added to components that use the `ui:attributes` ViewHelper inside them.
 * ```html
 * <ui:someComponent attributes="{ui:attributes()}" />
 * ```
 */
class AttributesViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('skip', 'string', 'A comma-separated list of attributes to skip');
        $this->registerArgument('only', 'string', 'A comma-separated list of attributes to include. All other attributes will be skipped');
        $this->registerArgument('asArray', 'boolean', 'If true, the attributes will be rendered as an array instead of a string. Useful when you need to pass the attributes to a Tag-ViewHelper with the `additionalAttributes` argument', false, false);
    }

    public function render(): mixed
    {
        if (!ComponentUtility::isComponent($this->renderingContext)) {
            throw new \RuntimeException('The attributes ViewHelper can only be used inside a component context.', 1698255600);
        }

        $asArray = $this->arguments['asArray'] ?? false;

        $tagAttributes = $this->renderingContext->getViewHelperVariableContainer()->get(self::class, 'attributes');
        if (empty($tagAttributes)) {
            return $asArray ? [] : '';
        }

        if (!$tagAttributes instanceof TagAttributes) {
            $tagAttributes = new TagAttributes($tagAttributes);
        }

        if (count($tagAttributes) === 0) {
            return $asArray ? [] : '';
        }

        // TODO: maybe we can allow both?
        if ($this->arguments['skip'] && $this->arguments['only']) {
            throw new \RuntimeException('You cannot use both "skip" and "only" arguments at the same time.', 1698255600);
        }

        $skip = $this->arguments['skip'] ? GeneralUtility::trimExplode(',', $this->arguments['skip']) : [];
        if (!empty($skip)) {
            return $tagAttributes->renderWithSkip($skip, $asArray);
        }

        $only = $this->arguments['only'] ? GeneralUtility::trimExplode(',', $this->arguments['only']) : [];
        if (!empty($only)) {
            return $tagAttributes->renderWithOnly($only, $asArray);
        }

        return $asArray ? $tagAttributes->renderAsArray() : (string)$tagAttributes;
    }
}
