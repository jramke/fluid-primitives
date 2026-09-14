<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\CheckboxGroupContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Utility\Typed;
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
     * @return string|null The checkbox-group's rootId, if a checkbox-group context was merged in.
     */
    public function apply(
        array $otherComponentContexts,
        TemplateView $view,
        array &$arguments,
        ?AbstractComponentContext $ctx,
    ): ?string {
        $checkboxGroupContext = $otherComponentContexts['checkbox-group'] ?? null;
        if (!$checkboxGroupContext instanceof CheckboxGroupContext) {
            return null;
        }

        $checkboxGroupRootId = Typed::stringOrNull($checkboxGroupContext->get('rootId'));
        $checkboxGroupVariables = $checkboxGroupContext->getChildVariables($arguments);

        // Each key holds a different, genuinely heterogeneous type - stays mixed all the way through,
        // matching getVariableProvider()->add()/AbstractComponentContext::set()'s own mixed
        // acceptance below.
        // @mago-expect analysis:mixed-assignment
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
