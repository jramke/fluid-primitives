<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Factory\ComponentContextFactory;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\StrictArgumentProcessor;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Everything {@see ComponentRenderer} does to create, expose, or merge {@see ComponentContextInterface}
 * state while rendering a component: creating and pushing a root component's own context, exposing
 * `context`-marked props from a composable (non-root) component up into it, and merging a related
 * ancestor context's variables into a component that supports it (Field -> any field-aware component,
 * CheckboxGroup -> Checkbox).
 */
final readonly class ComponentContextPropagator
{
    public function __construct(
        private ComponentCollectionInterface $componentResolver,
    ) {}

    /**
     * @param array<string, \TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition> $argumentDefinitions
     */
    public function createRootContext(
        array $argumentDefinitions,
        TemplateView $view,
        string $viewHelperName,
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
    ): void {
        $baseName = ComponentUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

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
        \TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface $variableProvider,
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

    /**
     * Exposes props marked for context from non root components to the context of this component -
     * all props from the root component are automatically available in the context.
     *
     * @param array<string, true> $propsMarkedForContext
     * @param array<string, mixed> $arguments
     */
    public function exposePropsMarkedForContext(
        array $propsMarkedForContext,
        array $arguments,
        array $argumentDefinitions,
        RenderingContextInterface $parentRenderingContext,
        string $viewHelperName,
    ): void {
        $baseName = ComponentUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

        $propsMarkedForContextValues = [];
        foreach (array_keys($propsMarkedForContext) as $name) {
            if (!isset($arguments[$name]) && !isset($argumentDefinitions[$name])) {
                continue;
            }

            $propsMarkedForContextValues[$name] = $arguments[$name] ?? $argumentDefinitions[$name]->getDefaultValue() ?? null;
        }

        $context = ContextService::getFromRenderingContext($parentRenderingContext, $baseName);
        if ($context instanceof ComponentContextInterface) {
            $context->set(
                ComponentUtility::getSubcomponentNameFromViewHelperName($viewHelperName),
                $propsMarkedForContextValues,
            );
        }
    }

    /**
     * If the component supports field and there is a field context available, merges the field
     * context variables into the current component (its arguments, view, and context).
     *
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     * @param array<string, mixed> $arguments
     * @return string|null The field's rootId, if a field context was merged in.
     */
    public function applyFieldContextVariables(
        array $otherComponentContexts,
        string $baseName,
        TemplateView $view,
        array &$arguments,
        ?AbstractComponentContext $ctx,
    ): ?string {
        $fieldContext = $otherComponentContexts['field'] ?? null;
        if (!$fieldContext) {
            return null;
        }

        $fieldRootId = $fieldContext->get('rootId') ?? null;
        $fieldVariables = $fieldContext->getChildVariables();

        foreach ($fieldVariables as $varName => $varValue) {
            if ($varValue === null) {
                continue;
            }

            if ($varName === 'ids' && is_array($varValue)) {
                $varValue = $this->remapFieldIds($baseName, (array)($arguments['ids'] ?? []), $varValue, $otherComponentContexts);
            }

            $view->getRenderingContext()->getVariableProvider()->add($varName, $varValue);
            $arguments[$varName] = $varValue;
            if ($ctx instanceof AbstractComponentContext) {
                $ctx->set($varName, $varValue);
            }
        }

        return $fieldRootId;
    }

    /**
     * Merges the Field's generic ("label"/"control") generated ids with whatever ids the component
     * itself was given, then maps the generic keys to the component's own part names - e.g. "control"
     * becomes "hiddenInput" for a Switch (per `ComponentUtility::FIELD_ID_PARTS`) - so the Field's
     * `<label for="...">` (built from its own "control" id) actually reaches the component's real
     * native input.
     *
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     */
    private function remapFieldIds(string $baseName, array $userIds, array $fieldIds, array $otherComponentContexts): array
    {
        $ids = array_merge($userIds, $fieldIds);

        // Remove field id parts if the current component is nested in a parent component that should exclude the field id inheritance
        $excludeIdInheritanceForParents = ComponentUtility::shouldSkipFieldIdsInheritanceWhenNestedIn($baseName);
        if ($excludeIdInheritanceForParents !== []) {
            foreach ($excludeIdInheritanceForParents as $parentBaseName) {
                if (!!($otherComponentContexts[$parentBaseName] ?? false)) {
                    foreach (ComponentUtility::getFieldIdOverrideKeys() as $fieldIdKey) {
                        unset($ids[$fieldIdKey]);
                    }
                }
            }
        }

        $updatedIds = $ids;
        foreach ($ids as $fieldIdKey => $fieldIdValue) {
            if (!is_string($fieldIdKey) || !is_string($fieldIdValue)) {
                continue;
            }

            $overrideFieldIdKey = ComponentUtility::getOverrideFieldIdKey($baseName, $fieldIdKey);
            if ($overrideFieldIdKey === null) {
                $updatedIds[$fieldIdKey] = $fieldIdValue;
                continue;
            }

            unset($updatedIds[$fieldIdKey]);
            $updatedIds[$overrideFieldIdKey] = $fieldIdValue;
        }

        return $updatedIds;
    }

    /**
     * If the component supports checkbox-group and there is a checkbox-group context available,
     * merges the checkbox-group context variables into the current component.
     *
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     * @param array<string, mixed> $arguments
     * @return string|null The checkbox-group's rootId, if a checkbox-group context was merged in.
     */
    public function applyCheckboxGroupContextVariables(
        array $otherComponentContexts,
        TemplateView $view,
        array &$arguments,
        ?AbstractComponentContext $ctx,
    ): ?string {
        $checkboxGroupContext = $otherComponentContexts['checkbox-group'] ?? null;
        if (!$checkboxGroupContext) {
            return null;
        }

        $checkboxGroupRootId = $checkboxGroupContext->get('rootId') ?? null;
        $checkboxGroupVariables = $checkboxGroupContext->getChildVariables($arguments);

        foreach ($checkboxGroupVariables as $varName => $varValue) {
            if ($varValue === null) {
                continue;
            }
            $view->getRenderingContext()->getVariableProvider()->remove($varName);
            $view->getRenderingContext()->getVariableProvider()->add($varName, $varValue);
            $arguments[$varName] = $varValue;
            if ($ctx instanceof AbstractComponentContext) {
                $ctx->set($varName, $varValue);
            }
        }

        return $checkboxGroupRootId;
    }
}
