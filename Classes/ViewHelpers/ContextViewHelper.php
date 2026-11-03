<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Exception\MissingPropException;
use Jramke\FluidPrimitives\Registry\ProvidedRootRegistry;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Reads the context of the closest enclosing component of another type.
 *
 * Use it when a nested component needs something from an ancestor, for example a custom button inside a dialog:
 *
 * ```html
 * <ui:context name="ui:dialog" as="dialogContext" />
 * ```
 *
 * `name` is the component with its namespace, as you write its tag, and `as` the variable that receives its context. A component reads its own context through the `context` variable. Asking `ui:context` for it throws an exception.
 *
 * With `provider` it reads the context a `<component>.provider` created instead, from anywhere after the provider, also outside a component:
 *
 * ```html
 * <ui:dialog.provider id="contact" />
 * <ui:context name="ui:dialog" provider="contact" as="contactDialog" />
 * ```
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
            'The component with its namespace, as you write its tag (e.g. "ui:fileUpload")',
            true,
        );
        $this->registerArgument('as', 'string', 'Variable name to assign the result to', false, '');
        $this->registerArgument(
            Constants::PROP_PROVIDER,
            'string',
            'The `id` of a `<component>.provider` to read the context of, instead of the closest enclosing component.',
            false,
            '',
        );
    }

    public function render(): ?ComponentContextInterface
    {
        $renderingContext = $this->renderingContext ?? throw new \LogicException(
            'Context ViewHelper is missing its rendering context.',
            1_788_100_010,
        );

        $name = Typed::string($this->arguments['name']);
        if ($name === '') {
            throw new MissingPropException('The "name" argument is required for the context ViewHelper.', 1754253444);
        }
        $matches = [];
        if (preg_match('/^([^:]+):(.+)$/', $name, $matches) !== 1) {
            throw new InvalidPropValueException(
                sprintf(
                    'The name of ui:context includes the namespace of the component, e.g. "ui:dialog". "%s" has none.',
                    $name,
                ),
                1_789_100_005,
            );
        }
        [, $namespace, $baseName] = $matches;
        $collections = array_values(array_filter(
            $renderingContext->getViewHelperResolver()->getNamespaces()[$namespace] ?? [],
            is_string(...),
        ));

        $providerId = Typed::string($this->arguments[Constants::PROP_PROVIDER]);
        if ($providerId !== '') {
            $usedBy = sprintf('<ui:context name="%s">', $name);
            return $this->assign(ProvidedRootRegistry::require($collections, $baseName, $providerId, $usedBy)->context);
        }

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new MissingContextException(
                'The context ViewHelper can only be used inside a component, or with the id of a provider.',
                1754253443,
            );
        }

        if ($baseName === ComponentNameUtility::getComponentBaseNameFromContext($renderingContext)) {
            throw new InvalidPropValueException(
                'You cannot access the context of the current component using the context ViewHelper. Use the exposed "context" variable instead.',
                1754253445,
            );
        }

        $context = ContextService::getFromRenderingContext($renderingContext, $baseName);
        if (
            $context instanceof ComponentContextInterface &&
            !in_array($context->getComponentResolver()->getNamespace(), $collections, strict: true)
        ) {
            throw new InvalidPropValueException(
                sprintf(
                    'The closest enclosing %s is not a %s. Name it with the namespace you render it with.',
                    $baseName,
                    $name,
                ),
                1_789_100_006,
            );
        }

        return $this->assign($context);
    }

    private function assign(?ComponentContextInterface $context): ?ComponentContextInterface
    {
        $as = Typed::string($this->arguments['as']);
        if ($as === '' || !$this->renderingContext instanceof RenderingContextInterface) {
            return $context;
        }

        $this->renderingContext->getVariableProvider()->add($as, $context);
        return null;
    }
}
