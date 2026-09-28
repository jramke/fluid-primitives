<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Annotations\AdditionalArgumentsAllowedAnnotation;
use Jramke\FluidPrimitives\Annotations\InternalBindingAnnotation;
use Jramke\FluidPrimitives\Constants;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinition;
use TYPO3Fluid\Fluid\Core\Definition\Annotation\ArgumentAnnotationInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

class PropsUtility
{
    /**
     * Strips both kinds of non-forwardable props: fixed, framework-reserved names
     * ({@see Constants::NON_FORWARDABLE_PROPS}) and dynamically-named internal bindings (e.g.
     * `ui:useProps`'s own `as=` binding), which carry {@see InternalBindingAnnotation} on
     * themselves instead, since their name is author-chosen and can't be enumerated up front.
     *
     * @param array<string, ArgumentDefinition> $props
     * @return array<string, ArgumentDefinition>
     */
    public static function cleanupNonForwardableProps(array $props): array
    {
        return array_filter(
            $props,
            static function (ArgumentDefinition $prop, string $key): bool {
                if (in_array($key, Constants::NON_FORWARDABLE_PROPS, strict: true)) {
                    return false;
                }
                foreach ($prop->getAnnotations() as $annotation) {
                    if ($annotation instanceof InternalBindingAnnotation) {
                        return false;
                    }
                }
                return true;
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public static function isReservedProp(string $propKey): bool
    {
        return in_array($propKey, Constants::RESERVED_PROPS, strict: true);
    }

    public static function createSpreadPropsArgumentDefinition(mixed $defaultValue = false): ArgumentDefinition
    {
        return new ArgumentDefinition(
            'spreadProps',
            'mixed',
            'Spread props from a component to another fluid component.',
            false,
            $defaultValue,
        );
    }

    public static function duplicateArgumentDefinitionWithNewDefault(
        ArgumentDefinition $argumentDefinition,
        mixed $newDefaultValue = null,
    ): ArgumentDefinition {
        // We dont validate the default value against the type here, because fluid also does not do this.
        // Also the StrictArgumentProcessor->isValid() allows all arguments with any default value if they are not required.

        return new ArgumentDefinition(
            $argumentDefinition->getName(),
            $argumentDefinition->getType(),
            $argumentDefinition->getDescription(),
            $argumentDefinition->isRequired(),
            $newDefaultValue ?? $argumentDefinition->getDefaultValue(),
            $argumentDefinition->getEscape(),
        );
    }

    /**
     * Evaluates `ui:useProps`'s `as` argument to its literal name (must be a compile-time-constant
     * string, since it becomes an argument-definition key - not a general Fluid expression the way
     * `props`/`defaults` values are). `null` when omitted, meaning the import is declaration-reuse
     * only, not a genuine delegation - see {@see \Jramke\FluidPrimitives\ViewHelpers\UsePropsViewHelper}.
     *
     * @param array<string, mixed> $arguments
     */
    public static function evaluateAsName(array $arguments): ?string
    {
        if (($arguments['as'] ?? null) === null) {
            return null;
        }

        $as = $arguments['as'] instanceof TextNode ? $arguments['as']->getText() : '';
        if ($as === '' || $as === '0') {
            throw new \RuntimeException('The as argument must not be empty.', 1_788_200_001);
        }

        return $as;
    }

    /**
     * A forwarded argument definition (e.g. `attributes`, added by `AttributesViewHelper` on
     * whatever component originally declared it) may itself carry `AdditionalArgumentsAllowedAnnotation`
     * - but merely inheriting that *prop* through a plain `ui:useProps` import (no `as=`) must not
     * also inherit the *fact* it was tagged with, or a component that only reuses declarations
     * (never delegates) would incorrectly end up with `additionalArgumentsAllowed` too. The prop
     * itself still needs to forward untouched (spreadProps's special "attributes" handling in
     * {@see \Jramke\FluidPrimitives\Service\Component\ComponentArgumentResolver::resolveSpreadProps()}
     * depends on it staying in the forwarded set) - only this one annotation is stripped. A genuine
     * `as=` delegation still gets the correct fact independently, via
     * {@see createDelegationBindingArgumentDefinition()} checking `additionalArgumentsAllowed()`
     * directly on the imported component's own definition.
     *
     * @param array<string, ArgumentDefinition> $forwardableArgumentDefinitions
     * @return array<string, ArgumentDefinition>
     */
    public static function withoutAdditionalArgumentsAllowedAnnotation(array $forwardableArgumentDefinitions): array
    {
        return array_map(
            static fn(ArgumentDefinition $definition): ArgumentDefinition => new ArgumentDefinition(
                $definition->getName(),
                $definition->getType(),
                $definition->getDescription(),
                $definition->isRequired(),
                $definition->getDefaultValue(),
                $definition->getEscape(),
                array_values(array_filter(
                    $definition->getAnnotations(),
                    static fn(ArgumentAnnotationInterface $annotation): bool => !
                        $annotation instanceof AdditionalArgumentsAllowedAnnotation
                    ,
                )),
            ),
            $forwardableArgumentDefinitions,
        );
    }

    /**
     * Builds `ui:useProps`'s `as=`-bound argument definition: the array of forwardable prop names
     * (for the paired `spreadProps="{name}"` delegate call to read at render time), always carrying
     * {@see InternalBindingAnnotation} (it's internal wiring, not a real prop of whatever was
     * imported from - never forward it further), and {@see AdditionalArgumentsAllowedAnnotation}
     * when the imported component itself allows additional arguments.
     *
     * @param array<string, ArgumentDefinition> $forwardableArgumentDefinitions
     */
    public static function createDelegationBindingArgumentDefinition(
        string $as,
        string $referencedComponentName,
        array $forwardableArgumentDefinitions,
        ComponentDefinition $externalComponentDefinition,
    ): ArgumentDefinition {
        $annotations = [new InternalBindingAnnotation()];
        if ($externalComponentDefinition->additionalArgumentsAllowed()) {
            $annotations[] = new AdditionalArgumentsAllowedAnnotation();
        }

        return new ArgumentDefinition(
            $as,
            'array',
            "Prop names forwarded from \"{$referencedComponentName}\" - use spreadProps=\"{{$as}}\" on the delegate call.",
            false,
            array_keys($forwardableArgumentDefinitions),
            null,
            $annotations,
        );
    }
}
