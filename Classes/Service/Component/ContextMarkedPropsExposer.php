<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Exposes props marked `context="{true}"` (via {@see \Jramke\FluidPrimitives\ViewHelpers\PropViewHelper})
 * from a composable (non-root) component up into its root component's context - all props from the
 * root component itself are already automatically available there.
 */
final readonly class ContextMarkedPropsExposer
{
    /**
     * @param array<string, true> $propsMarkedForContext
     * @param array<string, mixed> $arguments
     */
    public function expose(
        array $propsMarkedForContext,
        array $arguments,
        array $argumentDefinitions,
        RenderingContextInterface $parentRenderingContext,
        string $viewHelperName,
    ): void {
        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

        $propsMarkedForContextValues = [];
        foreach (array_keys($propsMarkedForContext) as $name) {
            if (!isset($arguments[$name]) && !isset($argumentDefinitions[$name])) {
                continue;
            }

            $propsMarkedForContextValues[$name] =
                $arguments[$name] ?? $argumentDefinitions[$name]->getDefaultValue() ?? null;
        }

        $context = ContextService::getFromRenderingContext($parentRenderingContext, $baseName);
        if ($context instanceof ComponentContextInterface) {
            $context->set(
                ComponentNameUtility::getSubcomponentNameFromViewHelperName($viewHelperName),
                $propsMarkedForContextValues,
            );
        }
    }
}
