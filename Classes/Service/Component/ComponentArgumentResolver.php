<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Annotations\ClientArgumentAnnotation;
use Jramke\FluidPrimitives\Annotations\ContextArgumentAnnotation;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Domain\Dto\ProvidedRoot;
use Jramke\FluidPrimitives\Domain\Dto\ResolvedComponentArguments;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Exception\ProvidedRootArgumentException;
use Jramke\FluidPrimitives\Utility\Typed;
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

    /**
     * The arguments of a `<component>.provider`, plus what its root still takes itself: its `rootId`, what
     * describes its element, its link to the provider and attributes no prop declares. Without a provider, the root's own arguments.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     * @return array<string, mixed>
     */
    public function mergeProvided(
        ?ProvidedRoot $provided,
        array $arguments,
        array $argumentDefinitions,
        string $baseName,
    ): array {
        if (!$provided instanceof ProvidedRoot) {
            return $arguments;
        }

        $own = [];
        // Narrowed per key below; argument values are as untyped as the props they belong to.
        // @mago-expect analysis:mixed-assignment
        foreach ($arguments as $name => $value) {
            $definition = $argumentDefinitions[$name] ?? null;
            if (
                !$definition instanceof ArgumentDefinition ||
                in_array(
                    $name,
                    [...Constants::ROOT_ELEMENT_PROPS, Constants::PROP_ROOT_ID, Constants::PROP_PROVIDER],
                    strict: true,
                )
            ) {
                $own[$name] = $value;
                continue;
            }

            // An uncached render gets every default filled in, a compiled one only what the tag sets: a value
            // that differs from the default is the one sign of a prop the root set itself in both
            if ($value !== $definition->getDefaultValue()) {
                throw new ProvidedRootArgumentException(
                    sprintf(
                        'The %s with provider "%s" takes its props from its <%s.provider>. Move "%s" there.',
                        $baseName,
                        Typed::string($arguments[Constants::PROP_PROVIDER] ?? null),
                        $baseName,
                        $name,
                    ),
                    1_789_100_004,
                );
            }
        }

        return [...$provided->arguments, ...$own];
    }
}
