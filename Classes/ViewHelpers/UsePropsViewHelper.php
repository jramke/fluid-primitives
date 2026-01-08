<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Service\ComponentCollectionService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\PropsUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;

/**
 * Use props from another component.
 *
 * This ViewHelper allows you to import all props from another component and register them for the current component.
 * This is helpful/needed when consuming the `primitives` components or when you want to reuse props from another component.
 * 
 * You can also override default values of the imported props by passing a `defaults` array with key-value pairs.
 *
 * ## Example
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
 * ## Limitation
 * 
 * Currently its not possible to use this `useProps` and `spreadProps` pattern with required arguments because of how Fluid parses the templates. 
 * If a prop for a primitive is required, we use the [ui:error](./error) ViewHelper to manually throw an error if the prop is not set.
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
        $this->registerArgument('defaults', 'array', 'Default values for props to override the imported ones. Key-value pairs', false, []);
    }

    public function render(): string
    {
        if (!ComponentUtility::isComponent($this->renderingContext)) {
            throw new \RuntimeException('The useProps viewhelper can only be used inside a component context.', 1698255600);
        }

        return '';
    }


    public function compile($argumentsName, $closureName, &$initializationPhpCode, ViewHelperNode $node, TemplateCompiler $compiler): string
    {
        return '\'\'';
    }

    public static function nodeInitializedEvent(ViewHelperNode $node, array $arguments, ParsingState $parsingState): void
    {
        if (isset($arguments['name'])) {
            $name = $arguments['name'] instanceof TextNode ? $arguments['name']->getText() : '';
            if (empty($name)) {
                throw new \RuntimeException('The name argument must not be empty.', 1755936423);
            }

            if (str_starts_with($name, 'primitives:')) {
                $name = substr($name, strlen('primitives:'));
                $externalArgumentDefinitions = self::getComponentPrimitivesCollection()->getComponentDefinition($name)->getArgumentDefinitions();
            } else {
                $externalArgumentDefinitions = self::getComponentCollectionService()->getCollectionByViewHelperName($name)->getComponentDefinition(explode(':', $name)[1])->getArgumentDefinitions();
            }

            if (empty($externalArgumentDefinitions)) {
                return;
            }

            $externalArgumentDefinitionsWithoutReserved = PropsUtility::cleanupReservedProps([...$externalArgumentDefinitions]);

            if (isset($arguments['defaults']) && $evaluatedDefaults = $arguments['defaults']->evaluate(new RenderingContext())) {
                foreach ($evaluatedDefaults as $defaultPropName => $defaultPropValue) {
                    if (isset($externalArgumentDefinitionsWithoutReserved[$defaultPropName])) {
                        $externalArgumentDefinitionsWithoutReserved[$defaultPropName] = PropsUtility::duplicateArgumentDefinitionWithNewDefault($externalArgumentDefinitionsWithoutReserved[$defaultPropName], $defaultPropValue);
                    }
                }
            }

            $argumentDefinitions = $parsingState->getArgumentDefinitions();

            // Merge props marked for client from external component definition and current
            if (isset($argumentDefinitions[Constants::PROPS_MARKED_FOR_CLIENT_KEY]) && isset($externalArgumentDefinitions[Constants::PROPS_MARKED_FOR_CLIENT_KEY])) {
                $argumentDefinitions[Constants::PROPS_MARKED_FOR_CLIENT_KEY] = PropsUtility::createPropsMarkedForClientArgumentDefinition(array_merge($argumentDefinitions[Constants::PROPS_MARKED_FOR_CLIENT_KEY]->getDefaultValue(), $externalArgumentDefinitions[Constants::PROPS_MARKED_FOR_CLIENT_KEY]->getDefaultValue()));
                unset($externalArgumentDefinitionsWithoutReserved[Constants::PROPS_MARKED_FOR_CLIENT_KEY]);
            }

            // Merge props marked for context from external component definition and current
            if (isset($argumentDefinitions[Constants::PROPS_MARKED_FOR_CONTEXT_KEY]) && isset($externalArgumentDefinitions[Constants::PROPS_MARKED_FOR_CONTEXT_KEY])) {
                $argumentDefinitions[Constants::PROPS_MARKED_FOR_CONTEXT_KEY] = PropsUtility::createPropsMarkedForContextArgumentDefinition(array_merge($argumentDefinitions[Constants::PROPS_MARKED_FOR_CONTEXT_KEY]->getDefaultValue(), $externalArgumentDefinitions[Constants::PROPS_MARKED_FOR_CONTEXT_KEY]->getDefaultValue()));
                unset($externalArgumentDefinitionsWithoutReserved[Constants::PROPS_MARKED_FOR_CONTEXT_KEY]);
            }

            $mergedArgumentDefinitions = array_merge($externalArgumentDefinitionsWithoutReserved, $argumentDefinitions);

            $mergedArgumentDefinitions['spreadProps'] = PropsUtility::createSpreadPropsArgumentDefinition(array_keys($externalArgumentDefinitions));

            $parsingState->setArgumentDefinitions($mergedArgumentDefinitions);
        }
    }

    protected static function getComponentPrimitivesCollection(): ComponentPrimitivesCollection
    {
        if (self::$componentPrimitivesCollection === null) {
            self::$componentPrimitivesCollection = GeneralUtility::makeInstance(ComponentPrimitivesCollection::class);
        }
        return self::$componentPrimitivesCollection;
    }

    protected static function getComponentCollectionService(): ComponentCollectionService
    {
        if (self::$componentCollectionService === null) {
            self::$componentCollectionService = GeneralUtility::makeInstance(ComponentCollectionService::class);
        }
        return self::$componentCollectionService;
    }
}
