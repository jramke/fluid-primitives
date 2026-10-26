<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

/**
 *
 * Marks the tag of your component whose attributes pass to the caller's element when the caller sets `asChild="{true}"`.
 *
 * Add it inline inside the opening bracket of that tag, like `{ui:ref(...)}` and `{ui:attributes()}`. It also gives the component its `asChild` prop:
 *
 * ```html
 * <button
 *     {ui:ref(name: 'trigger')}
 *     {ui:attributes()}
 *     {ui:asChild()}>
 *     <f:slot />
 * </button>
 * ```
 *
 * See [Composition](/docs/core-concepts/composition) for how callers use `asChild`.
 *
 */
class AsChildViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
{
    protected $escapeOutput = false;

    public function render(): string
    {
        $renderingContext = $this->renderingContext ?? throw new \LogicException(
            'AsChild ViewHelper is missing its rendering context.',
            1_788_100_020,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new MissingContextException(
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
