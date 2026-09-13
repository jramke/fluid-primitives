<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Parses and converts component names: ViewHelper tag names (e.g. `Accordion.Item`), their
 * dashed-case component-name equivalent (`accordion.item`), and root/base/subcomponent name parts.
 */
class ComponentNameUtility
{
    public static function getComponentFullNameFromViewHelperName(string $viewHelperName): string
    {
        return self::camelCaseToLowerCaseDashed($viewHelperName);
    }

    public static function getComponentBaseNameFromViewHelperName(string $viewHelperName): string
    {
        $fullName = self::getComponentFullNameFromViewHelperName($viewHelperName);
        $fullNameExploded = explode('.', $fullName);
        $baseName = $fullNameExploded[0];
        if ($baseName === 'primitives') {
            $baseName = $fullNameExploded[1] ?? $baseName;
        }
        return $baseName;
    }

    public static function getSubcomponentNameFromViewHelperName(string $viewHelperName): string
    {
        $fullName = self::getComponentFullNameFromViewHelperName($viewHelperName);
        $parts = explode('.', $fullName);
        if (count($parts) > 1) {
            return implode('.', array_slice($parts, offset: 1));
        }
        return '';
    }

    public static function getComponentFullNameFromContext(RenderingContextInterface $renderingContext): string
    {
        $component = $renderingContext->getVariableProvider()->get('component');
        $fullName = is_array($component) ? Typed::stringOrNull($component['fullName'] ?? null) : null;
        if ($fullName !== null) {
            return self::camelCaseToLowerCaseDashed($fullName);
        }
        return '';
    }

    public static function getComponentBaseNameFromContext(RenderingContextInterface $renderingContext): string
    {
        $fullName = self::getComponentFullNameFromContext($renderingContext);
        $fullNameExploded = explode('.', $fullName);
        $baseName = $fullNameExploded[0];
        if ($baseName === 'primitives') {
            $baseName = $fullNameExploded[1] ?? $baseName;
        }
        return $baseName;
    }

    public static function isRootComponent(string|RenderingContextInterface $viewHelperNameOrRenderingContext): bool
    {
        $viewHelperName = $viewHelperNameOrRenderingContext instanceof RenderingContextInterface
            ? self::getComponentFullNameFromContext($viewHelperNameOrRenderingContext)
            : $viewHelperNameOrRenderingContext;

        if ($viewHelperName === '' || $viewHelperName === '0') {
            return false;
        }

        $componentParts = explode('.', $viewHelperName);
        if (count($componentParts) === 1) {
            return true; // Single part components are considered root components
        }

        $end = $componentParts[1] ?? '';
        return strtolower($end) === 'root';
    }

    // This is not very accurate as a closed component like `alert.simple` would also return true
    // but its (currently) only used for exposing the `context` variable, so it's acceptable for now.
    public static function isComposableComponent(string $viewHelperName): bool
    {
        if ($viewHelperName === '' || $viewHelperName === '0') {
            return false;
        }

        $componentParts = explode('.', $viewHelperName);
        return count($componentParts) > 1;
    }

    public static function camelCaseToLowerCaseDashed(string $string): string
    {
        $result = GeneralUtility::camelCaseToLowerCaseUnderscored($string);
        return str_replace('_', replace: '-', subject: $result);
    }

    public static function lowerCaseDashedToCamelCase(string $string): string
    {
        $result = str_replace('-', replace: '_', subject: $string);
        return GeneralUtility::underscoredToUpperCamelCase($result);
    }
}
