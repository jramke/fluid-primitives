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
 * Access the context of another parent component.
 *
 * This is useful when you have nested components and you want to access the context of a parent component from another type.
 * You cannot access the context of the current component using this ViewHelper. Use the exposed "context" variable instead.
 *
 * ## Example
 * ```html
 * <ui:context name="dialog" as="dialogContext" />
 * ```
 */
class ContextViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'The name of the component of which we want the context', true);
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

        if ((string)$this->arguments['name'] === '') {
            throw new \RuntimeException('The "name" argument is required for the context ViewHelper.', 1754253444);
        }

        $componentName = ComponentNameUtility::getComponentBaseNameFromContext($renderingContext);
        if ($componentName === (string)$this->arguments['name']) {
            throw new \RuntimeException(
                'You cannot access the context of the current component using the context ViewHelper. Use the exposed "context" variable instead.',
                1754253445,
            );
        }

        $context = ContextService::getFromRenderingContext($renderingContext, (string)$this->arguments['name']);

        $as = Typed::string($this->arguments['as']);
        if ($as !== '') {
            $renderingContext->getVariableProvider()->add($as, $context);
            return null;
        }

        return $context;
    }
}
