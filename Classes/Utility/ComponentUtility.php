<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\BaseContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class ComponentUtility
{
    private static array $cachedSettings = [];

    // Keep in sync with: Resources/Private/Client/src/lib/hydration.ts
    private const ID_NAMESPACE_OVERRIDES = [
        'navigation-menu' => 'nav-menu',
    ];

    // Keep in sync with: Resources/Private/Client/src/lib/hydration.ts
    private const PART_SEGMENT_OVERRIDES = [
        'radio-group' => [
            'item' => 'radio',
            'item-hidden-input' => 'radio:input',
            'item-control' => 'radio:control',
            'item-text' => 'radio:label',
        ],
    ];

    public static function id(string $prefix = 'f'): string
    {
        static $counter = 0;
        static $requestSalt = null;

        if ($requestSalt === null) {
            // 6 bytes => 48 bits => 8 base64url chars, generated once per request
            $requestSalt = rtrim(strtr(base64_encode(random_bytes(6)), '+/', '-_'), '=');
        }

        return '«' . $prefix . $requestSalt . base_convert((string)++$counter, 10, 36) . '»';
    }

    public static function isComponent(RenderingContextInterface $renderingContext): bool
    {
        $componentProp = $renderingContext->getVariableProvider()->get('component');
        return (
            is_array($componentProp) &&
            is_string($componentProp['fullName'] ?? null) &&
            $componentProp['fullName'] !== ''
        );
    }

    public static function getComponentFullNameFromViewHelperName(string $viewHelperName): string
    {
        return self::camelCaseToLowerCaseDashed($viewHelperName);
    }

    public static function getComponentBaseNameFromViewHelperName(string $viewHelperName): string
    {
        $fullName = self::getComponentFullNameFromViewHelperName($viewHelperName);
        $fullNameExploded = explode('.', $fullName);
        $baseName = $fullNameExploded[0] ?? $fullName;
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
            return implode('.', array_slice($parts, 1));
        }
        return '';
    }

    public static function getComponentFullNameFromContext(RenderingContextInterface $renderingContext): string
    {
        $component = $renderingContext->getVariableProvider()->get('component');
        if (is_array($component) && isset($component['fullName'])) {
            return self::camelCaseToLowerCaseDashed($component['fullName']);
        }
        return '';
    }

    public static function getComponentBaseNameFromContext(RenderingContextInterface $renderingContext): string
    {
        $fullName = self::getComponentFullNameFromContext($renderingContext);
        $fullNameExploded = explode('.', $fullName);
        $baseName = $fullNameExploded[0] ?? $fullName;
        if ($baseName === 'primitives') {
            $baseName = $fullNameExploded[1] ?? $baseName;
        }
        return $baseName;
    }

    public static function isRootComponent(string|RenderingContextInterface $viewHelperNameOrRenderingContext): bool
    {
        if ($viewHelperNameOrRenderingContext instanceof RenderingContextInterface) {
            $viewHelperName = self::getComponentFullNameFromContext($viewHelperNameOrRenderingContext);
        } else {
            $viewHelperName = $viewHelperNameOrRenderingContext;
        }

        if ($viewHelperName === '' || $viewHelperName === '0') {
            return false;
        }

        $componentParts = explode('.', $viewHelperName);
        if (count($componentParts) === 0) {
            return false;
        }

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

    /**
     * Generates a deterministic part ID following the zag-js DOM convention.
     *
     * - Explicit override in `$idsOverrides[$part]` takes priority.
     * - The root part returns `{idNamespace}:{rootId}` (no suffix), matching zag-js.
     *   Any provided `$value` is ignored for the root part.
     * - Multi-instance parts with a `$value` return `{idNamespace}:{rootId}:{partSegment}:{value}`.
     * - All other parts return `{idNamespace}:{rootId}:{partSegment}`.
     */
    public static function generatePartId(
        string $componentName,
        string $rootId,
        string $part,
        ?string $value = null,
        array $idsOverrides = [],
    ): string {
        if (isset($idsOverrides[$part]) && $idsOverrides[$part] !== '') {
            return (string)$idsOverrides[$part];
        }

        $idNamespace = self::getIdNamespace($componentName);
        $partSegment = self::getPartSegment($componentName, $part);

        if ($part === 'root') {
            return "{$idNamespace}:{$rootId}";
        }

        if ($value !== null && $value !== '') {
            return "{$idNamespace}:{$rootId}:{$partSegment}:{$value}";
        }

        return "{$idNamespace}:{$rootId}:{$partSegment}";
    }

    private static function getIdNamespace(string $componentName): string
    {
        return self::ID_NAMESPACE_OVERRIDES[$componentName] ?? $componentName;
    }

    private static function getPartSegment(string $componentName, string $part): string
    {
        return self::PART_SEGMENT_OVERRIDES[$componentName][$part] ?? $part;
    }

    public static function getRootIdFromContext(RenderingContextInterface $renderingContext): string
    {
        $isRootComponent = self::isRootComponent($renderingContext);
        $rootId = $isRootComponent
            ? $renderingContext->getVariableProvider()->getByPath('rootId')
            : $renderingContext->getVariableProvider()->getByPath('context.rootId');
        return $rootId ?? '';
    }

    public static function camelCaseToLowerCaseDashed(string $string): string
    {
        $result = GeneralUtility::camelCaseToLowerCaseUnderscored($string);
        return str_replace('_', '-', $result);
    }

    public static function lowerCaseDashedToCamelCase(string $string): string
    {
        $result = str_replace('-', '_', $string);
        return GeneralUtility::underscoredToUpperCamelCase($result);
    }

    public static function getSettings(): array
    {
        if (self::$cachedSettings !== []) {
            return self::$cachedSettings;
        }

        try {
            $configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
            $settings = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        } catch (\Throwable) {
            // Return empty settings if configuration cannot be loaded
            // (e.g., no request available, no TypoScript setup in testing context)
            return [];
        }

        $fluidPrimitivesSettings = $settings['plugin.']['tx_fluidprimitives.']['settings.'] ?? [];

        $contentElementSettings = $settings['lib.']['contentElement.']['settings.'] ?? [];
        if ($contentElementSettings !== []) {
            $fluidPrimitivesSettings = array_merge($contentElementSettings, $fluidPrimitivesSettings);
        }

        self::$cachedSettings = GeneralUtility::removeDotsFromTS($fluidPrimitivesSettings) ?? [];
        return self::$cachedSettings;
    }

    public static function getContextClassNameFromViewHelperName(
        string $viewHelperName,
        array $additionalNamespaces,
    ): string {
        $baseClass = BaseContext::class;
        $baseNamespace = substr($baseClass, 0, strrpos($baseClass, '\\'));

        $namespaces = array_merge($additionalNamespaces, [$baseNamespace]);

        $ucFirstComponentBaseName = ucfirst(explode('.', $viewHelperName)[0] ?? '');

        foreach ($namespaces as $namespace) {
            $contextClass = $namespace . '\\' . $ucFirstComponentBaseName . 'Context';
            if (class_exists($contextClass) && is_subclass_of($contextClass, AbstractComponentContext::class)) {
                return $contextClass;
            }
        }

        return $baseClass;
    }
}
