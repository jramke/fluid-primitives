<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\SlotViewHelper;

/**
 * Check if a slot has content.
 * Returns the content of the slot if it has content, otherwise returns false.
 *
 * Using this ViewHelper is the equivalent of calling `{f:slot() -> f:trim()}`.
 *
 * ## Example
 * ```html
 * <f:variable name="content" value="{ui:slotFilled()}" />
 * <f:if condition="{content}">
 *     // Slot has content
 * </f:if>
 * ```
 */
class SlotFilledViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Name of the slot', false, SlotViewHelper::DEFAULT_SLOT);
    }

    public function render(): false|string
    {
        $variableContainer = $this->renderingContext->getViewHelperVariableContainer();
        $slot = $variableContainer->get(SlotViewHelper::class, $this->arguments['name']);
        $content = trim(is_callable($slot) ? (string)$slot() : '');

        if (empty($content)) {
            return false;
        }

        return $content;
    }
}
