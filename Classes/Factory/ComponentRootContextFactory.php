<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Factory;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\StrictArgumentProcessor;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Creates a root component's own {@see \Jramke\FluidPrimitives\Contexts\ComponentContextInterface}
 * and pushes it onto the {@see ContextService} stack so it can be picked up while rendering the
 * component's own template and any composable subcomponents nested inside it.
 */
final readonly class ComponentRootContextFactory
{
    public function __construct(
        private ComponentContextFactory $contextFactory,
    ) {}

    /**
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     */
    public function create(
        array $argumentDefinitions,
        TemplateView $view,
        string $viewHelperName,
        RenderingContextInterface $parentRenderingContext,
        ComponentCollectionInterface $componentResolver,
    ): void {
        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

        $contextVariables = $this->buildContextVariables(
            $argumentDefinitions,
            $view->getRenderingContext()->getVariableProvider(),
        );
        $contextClassName = ComponentUtility::getContextClassNameFromViewHelperName(
            $viewHelperName,
            $componentResolver->getContextNamespaces(),
        );
        $context = $this->contextFactory->create(
            $contextClassName,
            $view->getRenderingContext(),
            $parentRenderingContext,
            $componentResolver,
            $contextVariables,
        );

        ContextService::addToRenderingContext($parentRenderingContext, $baseName, $context);
    }

    // This is somewhat what is already done by the template view when we call the render method but we need the variables earlier so we can expose them to the context.
    // We also dont throw anything here as the validation is handled by the mentioned render method.
    /**
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     */
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
        if (!is_array($contextVariables)) {
            // VariableProviderInterface::getAll() is contractually allowed to return an ArrayAccess
            // instance instead of a plain array, but every context variable downstream of this method
            // (and the rest of this class's own array<string, mixed> plumbing) assumes a real array -
            // fail clearly here rather than silently misbehaving if a custom VariableProvider is ever
            // introduced that actually exercises that part of the contract.
            throw new \RuntimeException(
                'Expected VariableProviderInterface::getAll() to return an array, got an ArrayAccess instance.',
                1_788_100_014,
            );
        }

        foreach ($argumentDefinitions as $argumentDefinition) {
            $argumentName = $argumentDefinition->getName();

            if (!$variableProvider->exists($argumentName)) {
                if ($argumentDefinition->isRequired()) {
                    continue; // Skip required arguments that are not provided
                }

                $contextVariables[$argumentName] = $argumentDefinition->getDefaultValue();
                continue;
            }

            // StrictArgumentProcessor::process() is Fluid core's own generic argument coercion - its
            // result is inherently mixed, matching isValid()'s own mixed acceptance right below.
            // @mago-expect analysis:mixed-assignment
            $processedValue = $argumentProcessor->process($variableProvider->get($argumentName), $argumentDefinition);
            if (!$argumentProcessor->isValid($processedValue, $argumentDefinition)) {
                continue; // Skip invalid values
            }
            $contextVariables[$argumentName] = $processedValue;
        }

        foreach ($variablesToRemove as $var) {
            unset($contextVariables[$var]);
        }

        return $contextVariables;
    }
}
