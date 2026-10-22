<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Reads the context of the closest enclosing component of another type.
 *
 * Use it when a nested component needs something from an ancestor, for example a custom button inside a dialog:
 *
 * ```html
 * <ui:context name="dialog" as="dialogContext" />
 * ```
 *
 * `name` is the camelCase name of the enclosing component and `as` the variable that receives its context. A component reads its own context through the `context` variable. Asking `ui:context` for it throws an exception.
 *
 */
class ContextViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'name',
            'string',
            'The camelCase base name of the component of which we want the context (e.g. "fileUpload")',
            true,
        );
        $this->registerArgument('as', 'string', 'Variable name to assign the result to', false, '');
    }

    public function render(): ?ComponentContextInterface
    {
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'Context ViewHelper is missing its rendering context.',
            1_788_100_010,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new \RuntimeException('The context ViewHelper can only be used inside a component.', 1754253443);
        }

        $requestedBaseName = (string)$this->arguments['name'];
        if ($requestedBaseName === '') {
            throw new \RuntimeException('The "name" argument is required for the context ViewHelper.', 1754253444);
        }

        if ($requestedBaseName === ComponentNameUtility::getComponentBaseNameFromContext($renderingContext)) {
            throw new \RuntimeException(
                'You cannot access the context of the current component using the context ViewHelper. Use the exposed "context" variable instead.',
                1754253445,
            );
        }

        $context = ContextService::getFromRenderingContext($renderingContext, $requestedBaseName);

        $as = Typed::string($this->arguments['as']);
        if ($as !== '') {
            $renderingContext->getVariableProvider()->add($as, $context);
            return null;
        }

        return $context;
    }
}
