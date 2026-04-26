<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Annotations\ClientArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\ContextArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\RequiredAtRuntimeArgumentAnnotation;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\PropsUtility;
use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;
use TYPO3Fluid\Fluid\ViewHelpers\ArgumentViewHelper;

/**
 * Defines a template argument (prop) for a component.
 *
 * You must use this ViewHelper instead of the standard `f:argument` ViewHelper to define props for a component.
 * It mirrors the API of `f:argument` but adds some additional features like exposing the prop to the client hydration data or the context.
 *
 * {% component: "ui:alert", arguments: {"title": "All props from a root component are automatically exposed to the context.", "variant": "info"} %}
 *
 * ## Example
 * ```html
 * <ui:prop name="variant" type="string" optional="{true}" default="primary" />
 * <ui:prop name="size" type="string" optional="{true}" default="medium" client="{true}" />
 * ```
 */
class PropViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'name of the template argument', true);
        $this->registerArgument('type', 'string', 'type of the template argument', true);
        $this->registerArgument('description', 'string', 'description of the template argument');
        $this->registerArgument('optional', 'boolean', 'true if the defined argument should be optional', false, false);
        $this->registerArgument('default', 'mixed', 'default value for optional argument');
        $this->registerArgument(
            'client',
            'boolean',
            'Whether the property should be exposed to the client hydration data. See [Hydration](/docs/core-concepts/hydration) for more information.',
            false,
            false,
        );
        $this->registerArgument(
            'context',
            'boolean',
            'Whether the property should be exposed to the components context. See [Context](/docs/core-concepts/context) for more information.',
            false,
            false,
        );
        $this->registerArgument(
            'requiredAtRuntime',
            'boolean',
            'Whether the property is required at runtime. This means that the component will throw an error if the property is not defined when rendering. This is used internally by the primitives so props spreading works.',
            false,
            false,
        );
    }

    public function render(): string
    {
        if (!ComponentUtility::isComponent($this->renderingContext)) {
            throw new \RuntimeException('The prop ViewHelper can only be used inside a component context.', 1698255600);
        }

        $isRootComponent = ComponentUtility::isRootComponent($this->renderingContext);

        if ($this->arguments['context'] && $isRootComponent) {
            throw new \RuntimeException(
                'The context argument can only be used inside a composable component. All props from the root component are automatically available in the context.',
                1698255601,
            );
        }

        if ($this->arguments['client'] && !$isRootComponent) {
            throw new \RuntimeException('The client argument can only be used inside a root component.', 1698255602);
        }

        if (PropsUtility::isReservedProp($this->arguments['name'])) {
            throw new \RuntimeException(
                'The name "' . $this->arguments['name'] . '" is reserved and cannot be used as prop name.',
                1758400699,
            );
        }

        if (
            $this->arguments['requiredAtRuntime'] &&
            !$this->renderingContext->getVariableProvider()->exists($this->arguments['name'])
        ) {
            throw new \RuntimeException(
                'The prop "' .
                $this->arguments['name'] .
                '" is required for component "' .
                ComponentUtility::getComponentFullNameFromContext($this->renderingContext) .
                '" but was not provided.',
                1776714998,
            );
        }

        return '';
    }

    // here we just mirror the behavior of fluid cores ArgumentViewHelper::nodeInitializedEvent
    // we only add the annotations ourselves
    public static function nodeInitializedEvent(
        ViewHelperNode $node,
        array $arguments,
        ParsingState $parsingState,
    ): void {
        $emptyRenderingContext = new RenderingContext();
        $evaluatedArguments = array_map(static fn(NodeInterface $node): mixed => $node->evaluate(
            $emptyRenderingContext,
        ), $arguments);
        $argumentName = (string)$evaluatedArguments['name'];

        // Make sure that arguments are not nested into other ViewHelpers as this might create confusion
        if ($parsingState->hasNodeTypeInStack(ViewHelperNode::class)) {
            throw new Exception(
                sprintf(
                    'Template argument "%s" needs to be defined at the root level of the template, not within a ViewHelper.',
                    $argumentName,
                ),
                1776459351,
            );
        }

        // Make sure that this argument hasn't already been defined in the template
        $argumentDefinitions = $parsingState->getArgumentDefinitions();
        if (isset($argumentDefinitions[$argumentName])) {
            throw new Exception(
                sprintf('Template argument "%s" has been defined multiple times.', $argumentName),
                1776459352,
            );
        }

        // Automatically make the argument definition optional if it has a default value
        $hasDefaultValue = array_key_exists('default', $evaluatedArguments);
        $optional = ($evaluatedArguments['optional'] ?? false) || $hasDefaultValue;

        $annotations = [];
        if ($evaluatedArguments['client'] ?? false) {
            $annotations[] = new ClientArgumentAnnotation();
        }
        if ($evaluatedArguments['context'] ?? false) {
            $annotations[] = new ContextArgumentAnnotation();
        }
        if ($evaluatedArguments['requiredAtRuntime'] ?? false) {
            $annotations[] = new RequiredAtRuntimeArgumentAnnotation();
        }

        // Create argument definition to be interpreted later during rendering
        // This will also be written to the cache by the TemplateCompiler
        $argumentDefinitions[$argumentName] = new ArgumentDefinition(
            $argumentName,
            (string)$evaluatedArguments['type'],
            array_key_exists('description', $evaluatedArguments) ? (string)$evaluatedArguments['description'] : '',
            !$optional,
            $hasDefaultValue ? $evaluatedArguments['default'] : null,
            null,
            $annotations,
        );
        $parsingState->setArgumentDefinitions($argumentDefinitions);
    }
}
