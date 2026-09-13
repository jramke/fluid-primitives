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
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
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
 * `Tooltip/Root.html` that uses the tooltip primitive:
 * ```html
 * <ui:useProps name="primitives:tooltip.root" defaults="{openDelay: 200}" />
 *
 * <primitives:tooltip.root spreadProps="{true}">
 *     <f:slot />
 * </primitives:tooltip.root>
 * ```
 *
 * If you dont want all props from a component, you can also selectively import props by passing an array of prop names to the `props` argument.
 * ```html
 * <ui:useProps name="primitives:tooltip.root" props="{0: 'openDelay', 1: 'closeDelay'}" />
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

            $externalArgumentDefinitions = $isPrimitivesComponent
                ? self::getComponentPrimitivesCollection()->getComponentDefinition($name)->getArgumentDefinitions()
                : self::getComponentCollectionService()
                    ->getCollectionByViewHelperName($name)
                    ->getComponentDefinition(explode(':', $name)[1])
                    ->getArgumentDefinitions();

            if ($externalArgumentDefinitions === []) {
                return;
            }

            $externalArgumentDefinitionsWithoutReserved = PropsUtility::cleanupReservedProps([
                ...$externalArgumentDefinitions,
            ]);

            $evaluatedSelectedProps = Typed::arrayOrNull(($arguments['props'] ?? null)?->evaluate(
                new RenderingContext(),
            ));
            if ($evaluatedSelectedProps !== null && $evaluatedSelectedProps !== []) {
                $externalArgumentDefinitionsWithoutReservedUpdated = [];
                foreach ($evaluatedSelectedProps as $rawArgumentName) {
                    $argumentName = Typed::string($rawArgumentName);
                    if (($externalArgumentDefinitionsWithoutReserved[$argumentName] ?? null) === null) {
                        throw new \RuntimeException(
                            "The prop {$argumentName} does not exist in the referenced component {$name}.",
                            1772899866,
                        );
                    }
                    $externalArgumentDefinitionsWithoutReservedUpdated[$argumentName] =
                        $externalArgumentDefinitionsWithoutReserved[$argumentName];
                }
                $externalArgumentDefinitionsWithoutReserved = $externalArgumentDefinitionsWithoutReservedUpdated;
            }

            $evaluatedDefaults = Typed::arrayOrNull(($arguments['defaults'] ?? null)?->evaluate(
                new RenderingContext(),
            ));
            if ($evaluatedDefaults !== null && $evaluatedDefaults !== []) {
                foreach ($evaluatedDefaults as $rawDefaultPropName => $defaultPropValue) {
                    $defaultPropName = Typed::string($rawDefaultPropName);
                    $existingDefinition = $externalArgumentDefinitionsWithoutReserved[$defaultPropName] ?? null;
                    if ($existingDefinition === null) {
                        continue;
                    }

                    $externalArgumentDefinitionsWithoutReserved[$defaultPropName] = PropsUtility::duplicateArgumentDefinitionWithNewDefault(
                        $existingDefinition,
                        $defaultPropValue,
                    );
                }
            }

            $argumentDefinitions = $parsingState->getArgumentDefinitions();

            $mergedArgumentDefinitions = array_merge($externalArgumentDefinitionsWithoutReserved, $argumentDefinitions);

            $mergedArgumentDefinitions['spreadProps'] = PropsUtility::createSpreadPropsArgumentDefinition(array_keys(
                $externalArgumentDefinitions,
            ));

            $parsingState->setArgumentDefinitions($mergedArgumentDefinitions);
        }
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
