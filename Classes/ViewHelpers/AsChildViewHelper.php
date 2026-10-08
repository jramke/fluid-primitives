<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

/**
 * Opts a component into the `asChild` prop and marks which of its tags is the asChild target.
 *
 * Place this inline, inside the opening bracket of whichever tag should receive the merged
 * attributes when a caller passes `asChild="{true}"` - the same way `{ui:ref(...)}` and
 * `{ui:attributes()}` are already placed.
 *
 * ## Example
 * ```html
 * <button
 *     {ui:ref(name: 'trigger')}
 *     {ui:attributes()}
 *     {ui:asChild()}>
 *     <f:slot />
 * </button>
 * ```
 */
class AsChildViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
{
    protected $escapeOutput = false;

    public function render(): string
    {
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'AsChild ViewHelper is missing its rendering context.',
            1_788_100_020,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new \RuntimeException(
                'The asChild ViewHelper can only be used inside a component context.',
                1_788_100_021,
            );
        }

        $asChild = Typed::bool($renderingContext->getVariableProvider()->get('asChild'));

        return $asChild ? Constants::AS_CHILD_TARGET_MARKER : '';
    }

    public static function nodeInitializedEvent(
        ViewHelperNode $node,
        array $arguments,
        ParsingState $parsingState,
    ): void {
        $argumentDefinitions = $parsingState->getArgumentDefinitions();
        if (array_key_exists('asChild', $argumentDefinitions)) {
            return;
        }

        $argumentDefinitions['asChild'] = new ArgumentDefinition(
            'asChild',
            'boolean',
            'If true the component uses its child only without the component template. Like Radix UI asChild or Base UI render props.',
            false,
            null,
        );
        $parsingState->setArgumentDefinitions($argumentDefinitions);
    }
}
