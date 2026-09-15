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

    /**
     * Returns the component's canonical camelCase base name (e.g. "fileUpload" for both
     * "FileUpload.Root" and "FileUpload.Item") - this is the form used for context storage/lookup
     * and the `component.baseName` Fluid variable. `$viewHelperName` itself is Fluid's own resolved
     * dot-path, PascalCased per segment to match the ViewHelper's PHP class name (e.g.
     * "FileUpload.Root", not the lowercase-first "fileUpload.root" a template author types) -
     * `lcfirst()` undoes just that leading capital, the one part of the resolved name that isn't
     * already what a template author would write. Deliberately does *not* go through
     * `getComponentFullNameFromViewHelperName()`'s kebab-casing - use {@see camelCaseToLowerCaseDashed}
     * on the result for the few things that genuinely need kebab-case (data-scope, hydration keys,
     * `ComponentPartIdUtility`'s override maps).
     */
    public static function getComponentBaseNameFromViewHelperName(string $viewHelperName): string
    {
        $parts = explode('.', $viewHelperName);
        $baseName = $parts[0];
        if (strtolower($baseName) === 'primitives') {
            $baseName = $parts[1] ?? $baseName;
        }
        return lcfirst($baseName);
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

    /**
     * Reads the ambient `component.baseName` Fluid variable directly - already exactly
     * {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::$baseName} as computed once for
     * this component's own render (see `ComponentRenderer::createView()`), so there's nothing to
     * re-derive or re-parse here.
     */
    public static function getComponentBaseNameFromContext(RenderingContextInterface $renderingContext): string
    {
        $component = Typed::arrayOrNull($renderingContext->getVariableProvider()->get('component'));
        return $component !== null ? Typed::stringOrNull($component['baseName'] ?? null) ?? '' : '';
    }

    /**
     * The kebab-case form of {@see getComponentBaseNameFromContext} - for the few things that
     * genuinely need it (data-scope, hydration keys, `ComponentPartIdUtility`'s override maps).
     */
    public static function getClientBaseNameFromContext(RenderingContextInterface $renderingContext): string
    {
        return self::camelCaseToLowerCaseDashed(self::getComponentBaseNameFromContext($renderingContext));
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
