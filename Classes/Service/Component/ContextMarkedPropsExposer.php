<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Exposes props marked `context="{true}"` (via {@see \Jramke\FluidPrimitives\ViewHelpers\PropViewHelper})
 * from a composable (non-root) component up into its root component's context - all props from the
 * root component itself are already automatically available there.
 */
final readonly class ContextMarkedPropsExposer
{
    /**
     * Sets the exposed values and returns a closure that restores whatever was there before. The
     * root context object is shared and mutated in place across every part of the whole component
     * tree (there's no per-part scoping), so without restoring afterward, a value exposed by one
     * part (e.g. `select.item`'s own `item`) would keep leaking into every sibling rendered after
     * it (e.g. the next group's `itemGroupLabel`) until some *other* part happens to overwrite the
     * same key again - not just while this part's own children are rendering. The caller is
     * expected to invoke the returned closure once this part's own render (including its slot/
     * children) has fully finished, mirroring the try/finally save-and-restore
     * {@see \Jramke\FluidPrimitives\ViewHelpers\TemplateViewHelper} already does for `isRenderStencil`.
     *
     * @param array<string, true> $propsMarkedForContext
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     */
    public function expose(
        array $propsMarkedForContext,
        array $arguments,
        array $argumentDefinitions,
        RenderingContextInterface $parentRenderingContext,
        string $viewHelperName,
    ): ?\Closure {
        $contextKey = ComponentNameUtility::lowerCaseDashedToCamelCase(ComponentNameUtility::getComponentBaseNameFromViewHelperName(
            $viewHelperName,
        ));

        $context = ContextService::getFromRenderingContext($parentRenderingContext, $contextKey);
        if (!$context instanceof ComponentContextInterface) {
            return null;
        }

        $propsMarkedForContextValues = [];
        foreach (array_keys($propsMarkedForContext) as $name) {
            if (($arguments[$name] ?? null) === null && ($argumentDefinitions[$name] ?? null) === null) {
                continue;
            }

            $propsMarkedForContextValues[$name] =
                $arguments[$name] ?? $argumentDefinitions[$name]->getDefaultValue() ?? null;
        }

        $subcomponentName = ComponentNameUtility::getSubcomponentNameFromViewHelperName($viewHelperName);
        // Opaque, round-tripped value (whatever a previous expose() call put there, unknowable
        // here) - must stay mixed to be restored faithfully, same reasoning as
        // TemplateViewHelper's own $previousComponent/$previousContext.
        // @mago-expect analysis:mixed-assignment
        $previousValue = $context->get($subcomponentName);
        $context->set($subcomponentName, $propsMarkedForContextValues);

        return static function () use ($context, $subcomponentName, $previousValue): void {
            $context->set($subcomponentName, $previousValue);
        };
    }
}
