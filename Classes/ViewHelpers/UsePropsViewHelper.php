<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
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
 * Use props from another component.
 *
 * This ViewHelper allows you to import all props from another component and register them for the current component.
 * This is helpful/needed when consuming the `primitives` components or when you want to reuse props from another component.
 *
 * You can also override default values of the imported props by passing a `defaults` array with key-value pairs.
 *
 * ## Examples
 *
 * `Tooltip/Root.html` that uses the tooltip primitive and delegates rendering to it - `as` binds the
 * imported, forwardable prop names under a name you choose, so the actual delegate call can reference
 * it directly instead of the opaque `spreadProps="{true}"` this used to require:
 * ```html
 * <ui:useProps name="primitives:tooltip.root" as="rootProps" defaults="{openDelay: 200}" />
 *
 * <primitives:tooltip.root spreadProps="{rootProps}">
 *     <f:slot />
 * </primitives:tooltip.root>
 * ```
 *
 * Omit `as` when you only want to reuse another component's prop shape without rendering it at all -
 * see [Composition](/docs/core-concepts/composition) for when this applies.
 *
 * If you dont want all props from a component, you can also selectively import props by passing an array of prop names to the `props` argument.
 * ```html
 * <ui:useProps name="primitives:tooltip.root" as="rootProps" props="{0: 'openDelay', 1: 'closeDelay'}" />
 *
 * // ...
 * ```
 *
 * ## Limitation
 *
 * Currently its not possible to use this `useProps` and `spreadProps` pattern with required arguments because of how Fluid parses the templates.
 * If a prop for a primitive is required, we use the `requiredAtRuntime` argument on the [ui:prop](./prop) ViewHelper.
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
            'Bind the imported, forwardable prop names under this name, so the actual delegate call can use spreadProps="{name}". Required to genuinely delegate rendering - omit it to only reuse the referenced component\'s prop declarations without rendering it (see Composition docs).',
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
            $this->renderingContext ?? throw new \RuntimeException(
                'UseProps ViewHelper is missing its rendering context.',
                1_788_100_012,
            ),
        )) {
            throw new \RuntimeException(
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
                throw new \RuntimeException('The name argument must not be empty.', 1755936423);
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
                throw new \RuntimeException(
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
