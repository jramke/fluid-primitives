<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Utility\ComponentPartIdUtility;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * If the component supports field (per `Constants::COMPONENTS_THAT_SUPPORT_FIELD`) and there is a
 * field context available, merges the field context's variables into the current component's
 * arguments, view, and context.
 */
final readonly class FieldContextVariableMerger
{
    /**
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     * @param array<string, mixed> $arguments
     * @return string|null The field's rootId, if a field context was merged in.
     */
    public function apply(
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
     * becomes "hiddenInput" for a Switch (per `ComponentPartIdUtility::FIELD_ID_PARTS`) - so the Field's
     * `<label for="...">` (built from its own "control" id) actually reaches the component's real
     * native input.
     *
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     */
    private function remapFieldIds(string $baseName, array $userIds, array $fieldIds, array $otherComponentContexts): array
    {
        $ids = array_merge($userIds, $fieldIds);
        $ids = $this->excludeInheritedIdsWhenNested($baseName, $ids, $otherComponentContexts);

        $updatedIds = $ids;
        foreach ($ids as $fieldIdKey => $fieldIdValue) {
            if (!is_string($fieldIdKey) || !is_string($fieldIdValue)) {
                continue;
            }

            $overrideFieldIdKey = ComponentPartIdUtility::getOverrideFieldIdKey($baseName, $fieldIdKey);
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
     * Removes field id parts if the current component is nested in a parent component that should
     * exclude the field id inheritance (e.g. a Checkbox nested in a CheckboxGroup).
     *
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     */
    private function excludeInheritedIdsWhenNested(string $baseName, array $ids, array $otherComponentContexts): array
    {
        $excludeIdInheritanceForParents = ComponentPartIdUtility::shouldSkipFieldIdsInheritanceWhenNestedIn($baseName);
        if ($excludeIdInheritanceForParents === []) {
            return $ids;
        }

        foreach ($excludeIdInheritanceForParents as $parentBaseName) {
            if (!isset($otherComponentContexts[$parentBaseName])) {
                continue;
            }

            foreach (ComponentPartIdUtility::getFieldIdOverrideKeys() as $fieldIdKey) {
                unset($ids[$fieldIdKey]);
            }
        }

        return $ids;
    }
}
