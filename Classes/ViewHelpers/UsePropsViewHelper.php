<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Service\ComponentCollectionService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\PropsUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

/**
 *
 * Imports the props of another component, so that a wrapper around a primitive declares and forwards them without listing each one.
 *
 * A tooltip root that opens after 200 ms by default:
 *
 * ```html
 * <ui:useProps name="primitives:tooltip.root" as="rootProps" defaults="{openDelay: 200}" />
 *
 * <primitives:tooltip.root spreadProps="{rootProps}">
 *     <f:slot />
 * </primitives:tooltip.root>
 * ```
 *
 * `as` binds the imported prop names to a variable that you pass to `spreadProps` of the component you render. Omit `as` to reuse the props of a component without rendering it, see [Inheriting Props](/docs/core-concepts/arguments#content-inheriting-props).
 *
 * ## Import only some props
 *
 * Pass the prop names to `props`:
 *
 * ```html
 * <ui:useProps name="primitives:tooltip.root" as="rootProps" props="{0: 'openDelay', 1: 'closeDelay'}" />
 * ```
 *
 * ## Require a prop of a wrapped component
 *
 * A prop forwarded with `ui:useProps` and `spreadProps` cannot be a required argument, because Fluid checks required arguments while it parses the template. Declare such a prop with `requiredAtRuntime` on [ui:prop](./prop). The component then throws an exception at render time when the prop is missing.
 *
 */
class UsePropsViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
{
    protected $escapeOutput = false;

    private static ?ComponentCollectionService $componentCollectionService = null;
    private static ?ComponentPrimitivesCollection $componentPrimitivesCollection = null;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Name of component to use the props from', true);
        $this->registerArgument(
            'as',
            'string',
            'Bind the imported, forwardable prop names under this name, so the actual delegate call can use spreadProps="{name}". Needed to delegate rendering. Omit it to only reuse the prop declarations of the referenced component without rendering it.',
        );
        $this->registerArgument(
            'defaults',
            'array',
            'Default values for props to override the imported ones. Key-value pairs',
            false,
            [],
        );
        $this->registerArgument(
            'props',
            'array',
            'Only use a subset of props from the referenced component. Value should be an array of prop names.',
            false,
            [],
        );
    }

    public function render(): string
    {
        if (!ComponentUtility::isComponent(
            $this->renderingContext ?? throw new \LogicException(
                'UseProps ViewHelper is missing its rendering context.',
                1_788_100_012,
            ),
        )) {
            throw new MissingContextException(
                'The useProps viewhelper can only be used inside a component context.',
                1698255600,
            );
        }

        return '';
    }

    #[\Override]
    public function compile(
        $argumentsName,
        $closureName,
        &$initializationPhpCode,
        ViewHelperNode $node,
        TemplateCompiler $compiler,
    ): string {
        return '\'\'';
    }

    public static function nodeInitializedEvent(
        ViewHelperNode $node,
        array $arguments,
        ParsingState $parsingState,
    ): void {
        if (($arguments['name'] ?? null) !== null) {
            $name = $arguments['name'] instanceof TextNode ? $arguments['name']->getText() : '';
            if ($name === '' || $name === '0') {
                throw new InvalidPropValueException('The name argument must not be empty.', 1755936423);
            }

            $isPrimitivesComponent = str_starts_with($name, 'primitives:');
            if ($isPrimitivesComponent) {
                $name = substr($name, strlen('primitives:'));
            }

            $externalComponentDefinition = $isPrimitivesComponent
                ? self::getComponentPrimitivesCollection()->getComponentDefinition($name)
                : self::getComponentCollectionService()
                    ->getCollectionByViewHelperName($name)
                    ->getComponentDefinition(explode(':', $name)[1]);
            $externalArgumentDefinitions = $externalComponentDefinition->getArgumentDefinitions();

            if ($externalArgumentDefinitions === []) {
                return;
            }

            $forwardableArgumentDefinitions = PropsUtility::withoutAdditionalArgumentsAllowedAnnotation(PropsUtility::cleanupNonForwardableProps([
                ...$externalArgumentDefinitions,
            ]));
            $forwardableArgumentDefinitions = self::applySelectedProps(
                $forwardableArgumentDefinitions,
                $arguments,
                $name,
            );
            $forwardableArgumentDefinitions = self::applyDefaultOverrides($forwardableArgumentDefinitions, $arguments);

            $argumentDefinitions = $parsingState->getArgumentDefinitions();

            $mergedArgumentDefinitions = array_merge($forwardableArgumentDefinitions, $argumentDefinitions);

            $as = PropsUtility::evaluateAsName($arguments);
            if ($as !== null) {
                $mergedArgumentDefinitions[$as] = PropsUtility::createDelegationBindingArgumentDefinition(
                    $as,
                    $name,
                    $forwardableArgumentDefinitions,
                    $externalComponentDefinition,
                );
            }

            $parsingState->setArgumentDefinitions($mergedArgumentDefinitions);
        }
    }

    /**
     * @param array<string, ArgumentDefinition> $forwardableArgumentDefinitions
     * @param array<string, NodeInterface> $arguments unevaluated ViewHelper arguments, same shape as
     *        {@see \TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface::nodeInitializedEvent()}
     *        declares - needed explicitly here too so static analysis can narrow `?->evaluate()` below
     * @return array<string, ArgumentDefinition>
     */
    private static function applySelectedProps(
        array $forwardableArgumentDefinitions,
        array $arguments,
        string $referencedComponentName,
    ): array {
        $evaluatedSelectedProps = Typed::arrayOrNull(($arguments['props'] ?? null)?->evaluate(new RenderingContext()));
        if ($evaluatedSelectedProps === null || $evaluatedSelectedProps === []) {
            return $forwardableArgumentDefinitions;
        }

        $selected = [];
        foreach (array_map(Typed::string(...), $evaluatedSelectedProps) as $argumentName) {
            if (($forwardableArgumentDefinitions[$argumentName] ?? null) === null) {
                throw new InvalidPropValueException(
                    "The prop {$argumentName} does not exist in the referenced component {$referencedComponentName}.",
                    1772899866,
                );
            }
            $selected[$argumentName] = $forwardableArgumentDefinitions[$argumentName];
        }

        return $selected;
    }

    /**
     * @param array<string, ArgumentDefinition> $forwardableArgumentDefinitions
     * @param array<string, NodeInterface> $arguments unevaluated ViewHelper arguments, see
     *        {@see applySelectedProps()}'s own docblock for why this is repeated here
     * @return array<string, ArgumentDefinition>
     */
    private static function applyDefaultOverrides(array $forwardableArgumentDefinitions, array $arguments): array
    {
        $evaluatedDefaults = Typed::arrayOrNull(($arguments['defaults'] ?? null)?->evaluate(new RenderingContext()));
        if ($evaluatedDefaults === null || $evaluatedDefaults === []) {
            return $forwardableArgumentDefinitions;
        }

        // $defaultPropValue is deliberately left as-is (mixed) - it's forwarded to
        // duplicateArgumentDefinitionWithNewDefault(mixed $newDefaultValue), since a prop's default
        // can genuinely be any type.
        // @mago-expect analysis:mixed-assignment
        foreach ($evaluatedDefaults as $rawDefaultPropName => $defaultPropValue) {
            $defaultPropName = Typed::string($rawDefaultPropName);
            $existingDefinition = $forwardableArgumentDefinitions[$defaultPropName] ?? null;
            if ($existingDefinition === null) {
                continue;
            }

            $forwardableArgumentDefinitions[$defaultPropName] = PropsUtility::duplicateArgumentDefinitionWithNewDefault(
                $existingDefinition,
                $defaultPropValue,
            );
        }

        return $forwardableArgumentDefinitions;
    }

    protected static function getComponentPrimitivesCollection(): ComponentPrimitivesCollection
    {
        if (!self::$componentPrimitivesCollection instanceof ComponentPrimitivesCollection) {
            self::$componentPrimitivesCollection = GeneralUtility::makeInstance(ComponentPrimitivesCollection::class);
        }
        return self::$componentPrimitivesCollection;
    }

    protected static function getComponentCollectionService(): ComponentCollectionService
    {
        if (!self::$componentCollectionService instanceof ComponentCollectionService) {
            self::$componentCollectionService = GeneralUtility::makeInstance(ComponentCollectionService::class);
        }
        return self::$componentCollectionService;
    }
}
