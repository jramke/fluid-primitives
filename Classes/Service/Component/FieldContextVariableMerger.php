<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Contexts\FieldContext;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\FieldIdMapping;
use Jramke\FluidPrimitives\Utility\Typed;
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
        if (!$fieldContext instanceof FieldContext) {
            return null;
        }

        if ($this->takesFieldStateFromAncestor($baseName, $otherComponentContexts)) {
            return null;
        }

        $fieldRootId = Typed::stringOrNull($fieldContext->get('rootId'));
        $fieldVariables = $fieldContext->getChildVariables();

        // Each key holds a different, genuinely heterogeneous type (name: ?string, disabled: ?bool,
        // ids: array, ...) - stays mixed all the way through, matching getVariableProvider()->add()/
        // AbstractComponentContext::set()'s own mixed acceptance below.
        // @mago-expect analysis:mixed-assignment
        foreach ($fieldVariables as $varName => $varValue) {
            if ($varValue === null) {
                continue;
            }

            if ($varName === 'ids' && is_array($varValue)) {
                $varValue = $this->remapFieldIds($baseName, (array)($arguments['ids'] ?? []), $varValue);
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
     * becomes "hiddenInput" for a Switch (per `FieldIdMapping::FIELD_ID_PARTS`) - so the Field's
     * `<label for="...">` (built from its own "control" id) actually reaches the component's real
     * native input.
     */
    private function remapFieldIds(string $baseName, array $userIds, array $fieldIds): array
    {
        $ids = array_merge($userIds, $fieldIds);

        $updatedIds = $ids;
        // Checked with is_string() rather than Typed::string() below - the latter would also accept
        // and coerce numeric scalars, which isn't a valid id value here.
        // @mago-expect analysis:mixed-assignment
        foreach ($ids as $fieldIdKey => $fieldIdValue) {
            if (!is_string($fieldIdKey) || !is_string($fieldIdValue)) {
                continue;
            }

            $overrideFieldIdKey = FieldIdMapping::getOverrideFieldIdKey($baseName, $fieldIdKey);
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
     * Whether the component sits inside an ancestor that provides its field state instead of the
     * Field (e.g. a Checkbox nested in a CheckboxGroup).
     *
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     */
    private function takesFieldStateFromAncestor(string $baseName, array $otherComponentContexts): bool
    {
        foreach (FieldIdMapping::getAncestorsProvidingFieldState($baseName) as $ancestorBaseName) {
            // $otherComponentContexts is keyed by ContextService's camelCase context key;
            // $ancestorBaseName comes from FieldIdMapping's kebab-case map.
            $ancestorContextKey = ComponentNameUtility::lowerCaseDashedToCamelCase($ancestorBaseName);
            if (($otherComponentContexts[$ancestorContextKey] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }
}
