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
        $component = Typed::arrayOrNull($renderingContext->getVariableProvider()->get('component'));
        $fullName = $component !== null ? Typed::stringOrNull($component['fullName'] ?? null) : null;
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

    /**
     * Idempotent on already-camelCase input, unlike `GeneralUtility::underscoredToUpperCamelCase()`
     * (which lowercases the whole string first, destroying any capitalization a dash-less input -
     * i.e. already-camelCase - relied on to mark its word boundaries).
     */
    public static function lowerCaseDashedToCamelCase(string $string): string
    {
        if (!str_contains($string, '-')) {
            return lcfirst($string);
        }

        $segments = explode('-', $string);
        $firstSegment = array_shift($segments);
        return lcfirst($firstSegment) . implode('', array_map(ucfirst(...), $segments));
    }
}
