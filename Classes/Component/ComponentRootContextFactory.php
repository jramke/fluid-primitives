<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Factory\ComponentContextFactory;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\StrictArgumentProcessor;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Creates a root component's own {@see \Jramke\FluidPrimitives\Contexts\ComponentContextInterface}
 * and pushes it onto the {@see ContextService} stack so it can be picked up while rendering the
 * component's own template and any composable subcomponents nested inside it.
 */
final readonly class ComponentRootContextFactory
{
    public function __construct(
        private ComponentCollectionInterface $componentResolver,
    ) {}

    /**
     * @param array<string, \TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition> $argumentDefinitions
     */
    public function create(
        array $argumentDefinitions,
        TemplateView $view,
        string $viewHelperName,
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
    ): void {
        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

        $contextVariables = $this->buildContextVariables(
            $argumentDefinitions,
            $view->getRenderingContext()->getVariableProvider(),
        );
        $contextClassName = ComponentUtility::getContextClassNameFromViewHelperName(
            $viewHelperName,
            $this->componentResolver->getContextNamespaces(),
        );
        $contextFactory = GeneralUtility::makeInstance(ComponentContextFactory::class);
        $context = $contextFactory->create(
            $contextClassName,
            $renderingContext,
            $parentRenderingContext,
            $this->componentResolver,
            $contextVariables,
        );

        ContextService::addToRenderingContext($parentRenderingContext, $baseName, $context);
    }

    // This is somewhat what is already done by the template view when we call the render method but we need the variables earlier so we can expose them to the context.
    // We also dont throw anything here as the validation is handled by the mentioned render method.
    private function buildContextVariables(
        array $argumentDefinitions,
        VariableProviderInterface $variableProvider,
    ): array {
        $argumentProcessor = new StrictArgumentProcessor();

        $variablesToRemove = [
            'component',
            'settings',
            'context',
            'class',
            'asChild',
        ];

        $contextVariables = $variableProvider->getAll();

        foreach ($argumentDefinitions as $argumentDefinition) {
            $argumentName = $argumentDefinition->getName();
            if ($variableProvider->exists($argumentName)) {
                $processedValue = $argumentProcessor->process(
                    $variableProvider->get($argumentName),
                    $argumentDefinition,
                );
                if (!$argumentProcessor->isValid($processedValue, $argumentDefinition)) {
                    continue; // Skip invalid values
                }
                $contextVariables[$argumentName] = $processedValue;
            } elseif ($argumentDefinition->isRequired()) {
                continue; // Skip required arguments that are not provided
            } else {
                $contextVariables[$argumentName] = $argumentDefinition->getDefaultValue();
            }
        }

        foreach ($variablesToRemove as $var) {
            unset($contextVariables[$var]);
        }

        return $contextVariables;
    }
}
