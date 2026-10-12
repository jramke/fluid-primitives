<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\CheckboxGroupContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * If there is a checkbox-group context available, merges its variables into the current (Checkbox)
 * component's arguments, view, and context.
 */
final readonly class CheckboxGroupContextVariableMerger
{
    /**
     * @param array<string, ComponentContextInterface> $otherComponentContexts
     * @param array<string, mixed> $arguments
     */
    public function apply(
        array $otherComponentContexts,
        TemplateView $view,
        array &$arguments,
        ?AbstractComponentContext $ctx,
    ): void {
        // $otherComponentContexts is keyed by ContextService's camelCase context key, not the
        // kebab-case baseName used elsewhere (ref attribute names, hydration, FieldIdMapping).
        $checkboxGroupContext = $otherComponentContexts['checkboxGroup'] ?? null;
        if (!$checkboxGroupContext instanceof CheckboxGroupContext) {
            return;
        }

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
    }
}
