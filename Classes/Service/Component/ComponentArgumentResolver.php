<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Annotations\ClientArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\ContextArgumentAnnotation;
use Jramke\FluidPrimitives\Domain\Dto\ResolvedComponentArguments;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Utility\TagAttributesStringParser;
use Jramke\FluidPrimitives\Utility\Typed;
use Jramke\FluidPrimitives\ViewHelpers\AttributesViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Resolves a component's raw call-site arguments into what {@see ComponentRenderer} needs to render
 * it: which props are marked `client`/`context` (via {@see \Jramke\FluidPrimitives\ViewHelpers\PropViewHelper}),
 * which arguments aren't declared props at all ("additional arguments", collected for `ui:attributes`),
 * and the final argument values after the `spreadProps` pattern has been applied.
 */
final readonly class ComponentArgumentResolver
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     */
    public function resolve(
        array $arguments,
        array $argumentDefinitions,
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
    ): ResolvedComponentArguments {
        $propsMarkedForClient = [];
        $propsMarkedForContext = [];

        foreach ($argumentDefinitions as $argDef) {
            foreach ($argDef->getAnnotations() as $annotation) {
                if ($annotation instanceof ClientArgumentAnnotation) {
                    $propsMarkedForClient[$argDef->getName()] = true;
                    continue;
                }

                if ($annotation instanceof ContextArgumentAnnotation) {
                    $propsMarkedForContext[$argDef->getName()] = true;
                    continue;
                }
            }
        }

        $definedArgumentKeys = array_keys($argumentDefinitions);
        $additionalArguments = array_diff_key($arguments, array_flip($definedArgumentKeys));

        // We remove the additional arguments here
        // They are added later again coupled to an internal variable so they can be used by the ui:attributes view helper
        foreach (array_keys($additionalArguments) as $key) {
            unset($arguments[$key]);
        }

        $arguments = $this->resolveSpreadProps(
            $arguments,
            $argumentDefinitions,
            $renderingContext,
            $parentRenderingContext,
        );

        return new ResolvedComponentArguments(
            $arguments,
            $additionalArguments,
            $propsMarkedForClient,
            $propsMarkedForContext,
        );
    }

    /**
     * Exposes the additional (non-declared-prop) arguments as tag attributes so `ui:attributes` can
     * pick them up, merged with an explicit `attributes` argument if one was also given.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $additionalArguments
     */
    public function exposeAdditionalAttributes(
        RenderingContextInterface $renderingContext,
        array $arguments,
        array $additionalArguments,
    ): void {
        /** @var array|string|null $attributesArgument */
        $attributesArgument = $arguments['attributes'] ?? null;
        if (!$this->hasAdditionalAttributes($additionalArguments, $attributesArgument)) {
            return;
        }

        /** @var array<string, mixed> $attributes */
        $attributes = is_array($attributesArgument)
            ? $attributesArgument
            : TagAttributesStringParser::parse(Typed::string($attributesArgument));
        $mergedAttributes = array_merge($additionalArguments, $attributes);

        $renderingContext->getViewHelperVariableContainer()->add(
            AttributesViewHelper::class,
            'attributes',
            new TagAttributes($mergedAttributes),
        );
    }

    /**
     * @param array<string, mixed> $additionalArguments
     */
    private function hasAdditionalAttributes(array $additionalArguments, array|string|null $attributesArgument): bool
    {
        if ($additionalArguments !== []) {
            return true;
        }

        if (is_string($attributesArgument)) {
            return trim($attributesArgument) !== '';
        }

        return is_array($attributesArgument) && $attributesArgument !== [];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     * @return array<string, mixed>
     */
    private function resolveSpreadProps(
        array $arguments,
        array $argumentDefinitions,
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
    ): array {
        if (!($arguments['spreadProps'] ?? false)) {
            return $arguments;
        }

        $propsToUse = Typed::arrayOrNull($parentRenderingContext->getVariableProvider()->get('spreadProps')) ?? [];
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
