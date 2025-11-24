<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\BaseContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

class ComponentUtility
{
    private static array $cachedSettings = [];

    public static function uid(string $prefix = 'f'): string
    {
        static $id = 0;

        $id++;
        $base36 = base_convert((string)$id, 10, 36);

        return '«' . $prefix . $base36 . '»';
    }

    public static function isComponent(RenderingContextInterface $renderingContext): bool
    {
        $componentProp = $renderingContext->getVariableProvider()->get('component');
        if (is_array($componentProp) && isset($componentProp['fullName']) && !empty($componentProp['fullName'])) {
            return true;
        }
        return false;
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

        if (empty($viewHelperName)) return false;

        $componentParts = explode('.', $viewHelperName);
        if (count($componentParts) === 0) return false;

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
        if (empty($viewHelperName)) return false;

        $componentParts = explode('.', $viewHelperName);
        if (count($componentParts) > 1) return true;

        return false;
    }

    public static function getRootIdFromContext(RenderingContextInterface $renderingContext): string
    {
        $isRootComponent = self::isRootComponent($renderingContext);
        $rootId = $isRootComponent ? $renderingContext->getVariableProvider()->getByPath('rootId') : $renderingContext->getVariableProvider()->getByPath('context.rootId');
        return $rootId ?? '';
    }

    public static function camelCaseToLowerCaseDashed(string $string): string
    {
        $result = GeneralUtility::camelCaseToLowerCaseUnderscored($string);
        return str_replace('_', '-', $result);
    }

    public static function getSettings(): array
    {
        if (!empty(self::$cachedSettings)) {
            return self::$cachedSettings;
        }

        $configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        $fluidPrimitivesSettings = $settings['plugin.']['tx_fluidprimitives.']['settings.'] ?? [];

        $contentElementSettings = $settings['lib.']['contentElement.']['settings.'] ?? [];
        if (!empty($contentElementSettings)) {
            $fluidPrimitivesSettings = array_merge($contentElementSettings, $fluidPrimitivesSettings);
        }

        self::$cachedSettings = GeneralUtility::removeDotsFromTS($fluidPrimitivesSettings) ?? [];
        return self::$cachedSettings;
    }

    public static function getContextClassNameFromViewHelperName(string $viewHelperName, array $additionalNamespaces): string
    {
        $baseClass = BaseContext::class;
        $baseNamespace = substr($baseClass, 0, strrpos($baseClass, '\\'));

        $namespaces = array_merge(
            $additionalNamespaces,
            [$baseNamespace]
        );

        $ucFirstComponentBaseName = ucfirst(self::getComponentBaseNameFromViewHelperName($viewHelperName));

        foreach ($namespaces as $namespace) {
            $contextClass = $namespace . '\\' . $ucFirstComponentBaseName . 'Context';
            if (class_exists($contextClass) && is_subclass_of($contextClass, AbstractComponentContext::class)) {
                return $contextClass;
            }
        }

        return $baseClass;
    }
}
