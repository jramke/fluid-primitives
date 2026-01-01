<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Constants;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

class PropsUtility
{
    public static function cleanupReservedProps(array $props, bool $useKeys = true): array
    {
        return array_filter($props, function ($key) {
            return !self::isReservedProp($key);
        }, $useKeys ? ARRAY_FILTER_USE_KEY : 0);
    }

    public static function isReservedProp(string $propKey): bool
    {
        return in_array($propKey, array_values(Constants::RESERVED_PROPS), true);
    }

    public static function createPropsMarkedForClientArgumentDefinition(mixed $defaultValue): ArgumentDefinition
    {
        return new ArgumentDefinition(
            Constants::PROPS_MARKED_FOR_CLIENT_KEY,
            'array',
            'DO NOT USE THIS ARGUMENT, IT IS FOR INTERNAL USE ONLY',
            false,
            $defaultValue
        );
    }

    public static function createPropsMarkedForContextArgumentDefinition(mixed $defaultValue): ArgumentDefinition
    {
        return new ArgumentDefinition(
            Constants::PROPS_MARKED_FOR_CONTEXT_KEY,
            'array',
            'DO NOT USE THIS ARGUMENT, IT IS FOR INTERNAL USE ONLY',
            false,
            $defaultValue
        );
    }

    public static function createSpreadPropsArgumentDefinition(mixed $defaultValue = false): ArgumentDefinition
    {
        return new ArgumentDefinition(
            'spreadProps',
            'mixed',
            'Spread props from a component to another fluid component.',
            false,
            $defaultValue
        );
    }

    public static function duplicateArgumentDefinitionWithNewDefault(ArgumentDefinition $argumentDefinition, mixed $newDefaultValue = null): ArgumentDefinition
    {
        $newDefinition = new ArgumentDefinition(
            $argumentDefinition->getName(),
            $argumentDefinition->getType(),
            $argumentDefinition->getDescription(),
            $argumentDefinition->isRequired(),
            $newDefaultValue !== null ? $newDefaultValue : $argumentDefinition->getDefaultValue(),
            $argumentDefinition->getEscape()
        );

        // We dont validate the default value against the type here, because fluid also does not do this.
        // Also the StrictArgumentProcessor->isValid() allows all arguments with any default value if they are not required.

        return $newDefinition;
    }
}
