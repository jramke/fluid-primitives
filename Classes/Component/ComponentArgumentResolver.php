<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Annotations\ClientArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\ContextArgumentAnnotation;
use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Domain\Model\TagAttributesStringParser;
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

        $arguments = $this->resolveSpreadProps($arguments, $renderingContext, $parentRenderingContext);

        return new ResolvedComponentArguments($arguments, $additionalArguments, $propsMarkedForClient, $propsMarkedForContext);
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
        $attributesArgument = $arguments['attributes'] ?? null;
        if (!$this->hasAdditionalAttributes($additionalArguments, $attributesArgument)) {
            return;
        }

        $attributes = is_array($attributesArgument)
            ? $attributesArgument
            : TagAttributesStringParser::parse($attributesArgument ?? '');
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
    private function hasAdditionalAttributes(array $additionalArguments, mixed $attributesArgument): bool
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
     * @return array<string, mixed>
     */
    private function resolveSpreadProps(
        array $arguments,
        RenderingContextInterface $renderingContext,
        RenderingContextInterface $parentRenderingContext,
    ): array {
        if (!($arguments['spreadProps'] ?? false)) {
            return $arguments;
        }

        $propsToUse = $parentRenderingContext->getVariableProvider()->get('spreadProps') ?? [];
        if (!is_array($propsToUse) || $propsToUse === []) {
            return $arguments;
        }

        foreach ($propsToUse as $propToUse) {
            if ($propToUse === 'attributes') {
                // here we can simply grab the TagAttributes object as it already has resolved the additional attributes and the ones from the attributes argument
                $spreadTagAttributes =
                    $parentRenderingContext->getViewHelperVariableContainer()->get(
                        AttributesViewHelper::class,
                        'attributes',
                    ) ?? null;
                $propValue = $spreadTagAttributes instanceof TagAttributes ? $spreadTagAttributes->renderAsArray() : [];
            } else {
                $propValue =
                    $arguments[$propToUse] ?? $parentRenderingContext->getVariableProvider()->get(
                        $propToUse,
                    ) ?? $renderingContext->getVariableProvider()->get($propToUse) ?? null;
            }
            $arguments[$propToUse] = $propValue;
        }

        return $arguments;
    }
}
