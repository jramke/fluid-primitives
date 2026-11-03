<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

/**
 *
 * Lets a part render outside its root. Add it to the template of the part, and the part takes a `provider` prop with the `id` of a `<component>.provider`:
 *
 * ```html
 * <ui:detachable />
 *
 * <button {ui:ref(name: 'trigger')} {ui:attributes()}>
 *     <f:slot />
 * </button>
 * ```
 *
 * ```html
 * <ui:dialog.provider id="cookie-settings" />
 * <ui:dialog.trigger provider="cookie-settings">Cookie settings</ui:dialog.trigger>
 * <ui:dialog.root provider="cookie-settings">...</ui:dialog.root>
 * ```
 *
 * A wrapper built with `ui:useProps` adds its own `<ui:detachable />` to take `provider`.
 *
 */
class DetachableViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
{
    public function render(): string
    {
        return '';
    }

    public static function nodeInitializedEvent(
        ViewHelperNode $node,
        array $arguments,
        ParsingState $parsingState,
    ): void {
        $argumentDefinitions = $parsingState->getArgumentDefinitions();
        $argumentDefinitions[Constants::PROP_PROVIDER] = new ArgumentDefinition(
            Constants::PROP_PROVIDER,
            'string',
            'The `id` of the `<component>.provider` this part belongs to, when it renders outside its root.',
            false,
            null,
        );
        $parsingState->setArgumentDefinitions($argumentDefinitions);
    }
}
