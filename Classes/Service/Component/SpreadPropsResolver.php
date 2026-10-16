<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Utility\Typed;
use Jramke\FluidPrimitives\ViewHelpers\AttributesViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Applies the `spreadProps` pattern: forwards the props named by `ui:useProps` from the parent
 * rendering context into a component call's arguments.
 */
final readonly class SpreadPropsResolver
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     * @return array<string, mixed>
     */
    public function resolve(
        array $arguments,
        array $argumentDefinitions,
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
    ): array {
        // spreadProps carries the forwardable prop names directly (bound by UsePropsViewHelper's
        // `as=` argument, see its nodeInitializedEvent()) - no separate parent-scope lookup needed,
        // since the array has nowhere else to travel from ui:useProps to this call.
        $propsToUse = Typed::arrayOrNull($arguments['spreadProps'] ?? null) ?? [];
        if ($propsToUse === []) {
            return $arguments;
        }

        foreach (array_map(Typed::string(...), $propsToUse) as $propToUse) {
            if ($propToUse === 'attributes') {
                // here we can simply grab the TagAttributes object as it already has resolved the additional attributes and the ones from the attributes argument
                // @mago-expect analysis:mixed-assignment
                $spreadTagAttributes =
                    $parentRenderingContext->getViewHelperVariableContainer()->get(
                        AttributesViewHelper::class,
                        'attributes',
                    ) ?? null;
                $arguments[$propToUse] = $spreadTagAttributes instanceof TagAttributes
                    ? $spreadTagAttributes->renderAsArray()
                    : [];
                continue;
            }

            if ($this->isExplicitlyProvidedAtCallSite($propToUse, $arguments, $argumentDefinitions)) {
                continue;
            }

            $arguments[$propToUse] =
                $parentRenderingContext->getVariableProvider()->get(
                    $propToUse,
                ) ?? $renderingContext->getVariableProvider()->get($propToUse) ?? null;
        }

        return $arguments;
    }

    /**
     * On Fluid's compiled render path, an argument this component's own call site never wrote is
     * simply absent from $arguments. But on the *uncached* (interpreted) path - which every
     * template hits the first time it's rendered in a process, before TemplateCompiler has a
     * cached class for it - TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInvoker::invoke() pre-fills
     * every declared-but-omitted argument with its ArgumentDefinition default before this class
     * ever sees it, so the key is always present. A `??=`-style presence check can't tell those
     * two cases apart, and for any prop whose default isn't null (e.g. a `checked` prop defaulting
     * to false), that made an explicit call-site value indistinguishable from "not passed",
     * silently dropping it in favor of the parent's spread value. Comparing against the prop's own
     * default recovers the distinction; it's a no-op for the (common) null-default case, where
     * presence alone was already an unambiguous signal.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     */
    private function isExplicitlyProvidedAtCallSite(
        string $propToUse,
        array $arguments,
        array $argumentDefinitions,
    ): bool {
        if (!array_key_exists($propToUse, $arguments)) {
            return false;
        }

        $argumentDefinition = $argumentDefinitions[$propToUse] ?? null;
        if (!$argumentDefinition instanceof ArgumentDefinition) {
            return $arguments[$propToUse] !== null;
        }

        return $arguments[$propToUse] !== $argumentDefinition->getDefaultValue();
    }
}
