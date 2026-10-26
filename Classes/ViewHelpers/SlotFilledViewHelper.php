<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\SlotViewHelper;

/**
 *
 * Checks whether a slot has content. It returns the trimmed content, or `false` when the slot is empty.
 *
 * ```html
 * <f:variable name="note" value="{ui:slotFilled()}" />
 * <f:if condition="{note}">
 *     <p class="text-sm">{note -> f:format.raw()}</p>
 * </f:if>
 * ```
 *
 * `name` picks a named slot, the default slot otherwise.
 *
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
        $renderingContext = $this->renderingContext ?? throw new \LogicException(
            'SlotFilled ViewHelper is missing its rendering context.',
            1_788_100_001,
        );
        $variableContainer = $renderingContext->getViewHelperVariableContainer();
        // Narrowed immediately below via is_callable() - no Typed:: equivalent for callables.
        // @mago-expect analysis:mixed-assignment
        $slot = $variableContainer->get(SlotViewHelper::class, Typed::string($this->arguments['name']));
        $content = trim(is_callable($slot) ? (string)$slot() : '');

        if ($content === '' || $content === '0') {
            return false;
        }

        return $content;
    }
}
