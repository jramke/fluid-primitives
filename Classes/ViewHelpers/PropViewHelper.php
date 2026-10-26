<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Annotations\ClientArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\ContextArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\RequiredAtRuntimeArgumentAnnotation;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Exception\MissingPropException;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentRootUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\PropsUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

/**
 *
 * Declares a prop of a component. Use it where you would write `f:argument`: it takes the same arguments and adds `client` and `context`.
 *
 * ```html
 * <ui:prop name="variant" type="string" optional="{true}" default="primary" />
 * <ui:prop name="size" type="string" optional="{true}" default="medium" client="{true}" />
 * ```
 *
 * `client` works on a root component and `context` on its other parts. The other way round throws an exception.
 *
 * {% component: "ui:alert", arguments: {"title": "All props from a root component are automatically exposed to the context.", "variant": "info"} %}
 *
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
            'Whether the property is required at runtime. This means that the component will throw an error if the property is not defined when rendering. Primitives use it for props that a wrapper forwards with [ui:useProps](./useProps).',
            false,
            false,
        );
    }

    public function render(): string
    {
        $renderingContext = $this->renderingContext ?? throw new \LogicException(
            'Prop ViewHelper is missing its rendering context.',
            1_788_100_003,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new MissingContextException(
                'The prop ViewHelper can only be used inside a component context.',
                1698255600,
            );
        }

        $isDeclaredRoot = ComponentRootUtility::isDeclaredRootFromContext($renderingContext);
        $name = Typed::string($this->arguments['name']);

        if (Typed::bool($this->arguments['context']) && $isDeclaredRoot) {
            throw new InvalidPropValueException(
                'The context argument can only be used inside a composable component. All props from the root component are automatically available in the context.',
                1698255601,
            );
        }

        if (Typed::bool($this->arguments['client']) && !$isDeclaredRoot) {
            throw new InvalidPropValueException(
                'The client argument can only be used inside a root component.',
                1698255602,
            );
        }

        if (
            Typed::bool($this->arguments['requiredAtRuntime']) &&
            !$renderingContext->getVariableProvider()->exists($name)
        ) {
            throw new MissingPropException(
                'The prop "' .
                $name .
                '" is required for component "' .
                ComponentNameUtility::getComponentFullNameFromContext($renderingContext) .
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
        if (($argumentDefinitions[$argumentName] ?? null) !== null) {
            throw new Exception(
                sprintf('Template argument "%s" has been defined multiple times.', $argumentName),
                1776459352,
            );
        }

        // Catches this specifically at the one place a reserved name could be freshly authored, so
        // ui:useProps is free to forward asChild/class from an imported component without every
        // importer re-triggering this check on inherited (not self-declared) definitions - see
        // AbstractComponentCollection::getComponentDefinition(), which used to run this same check
        // too late (after such imports were already merged in).
        if (PropsUtility::isReservedProp($argumentName)) {
            throw new Exception(
                sprintf('The name "%s" is reserved and cannot be used as a prop name.', $argumentName),
                1776459353,
            );
        }

        // Automatically make the argument definition optional if it has a default value
        $hasDefaultValue = array_key_exists('default', $evaluatedArguments);
        $optional = Typed::bool($evaluatedArguments['optional'] ?? null) || $hasDefaultValue;

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
