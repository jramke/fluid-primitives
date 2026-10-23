<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Annotations\ClientArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\ContextArgumentAnnotation;
use Jramke\FluidPrimitives\Domain\Dto\ResolvedComponentArguments;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\ViewHelpers\AttributesViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Resolves a component's raw call-site arguments into what {@see ComponentRenderer} needs to render
 * it: which props are marked `client`/`context` (via {@see \Jramke\FluidPrimitives\ViewHelpers\PropViewHelper}),
 * which arguments aren't declared props at all ("additional arguments", collected for `ui:attributes`),
 * and the final argument values after the `spreadProps` pattern has been applied ({@see SpreadPropsResolver}).
 */
final readonly class ComponentArgumentResolver
{
    public function __construct(
        private SpreadPropsResolver $spreadPropsResolver,
    ) {}

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

        $arguments = $this->spreadPropsResolver->resolve(
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
        // `attributes` is declared as an array: Fluid rejects a string before the component renders
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($arguments['attributes'] ?? null) ? $arguments['attributes'] : [];
        if ($additionalArguments === [] && $attributes === []) {
            return;
        }

        $renderingContext->getViewHelperVariableContainer()->add(
            AttributesViewHelper::class,
            'attributes',
            new TagAttributes(array_merge($additionalArguments, $attributes)),
        );
    }
}
