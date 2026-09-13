<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\BaseContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class ComponentUtility
{
    private static array $cachedSettings = [];

    public static function id(string $prefix = 'f'): string
    {
        static $counter = 0;
        static $requestSalt = null;

        if ($requestSalt === null) {
            // 6 bytes => 48 bits => 8 base64url chars, generated once per request
            $requestSalt = rtrim(strtr(base64_encode(random_bytes(6)), to: '-_', from: '+/'), characters: '=');
        }

        return '«' . $prefix . $requestSalt . base_convert((string)++$counter, from_base: 10, to_base: 36) . '»';
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

    public static function getRootIdFromContext(RenderingContextInterface $renderingContext): string
    {
        $isRootComponent = ComponentNameUtility::isRootComponent($renderingContext);
        $rootId = $isRootComponent
            ? $renderingContext->getVariableProvider()->getByPath('rootId')
            : $renderingContext->getVariableProvider()->getByPath('context.rootId');
        return $rootId ?? '';
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

        self::$cachedSettings = GeneralUtility::removeDotsFromTS($fluidPrimitivesSettings);
        return self::$cachedSettings;
    }

    /**
     * @return class-string<ComponentContextInterface>
     */
    public static function getContextClassNameFromViewHelperName(
        string $viewHelperName,
        array $additionalNamespaces,
    ): string {
        $baseClass = BaseContext::class;
        $backslashPosition = strrpos($baseClass, needle: '\\');
        $baseNamespace = $backslashPosition === false ? '' : substr($baseClass, offset: 0, length: $backslashPosition);

        $namespaces = array_merge($additionalNamespaces, [$baseNamespace]);

        $ucFirstComponentBaseName = ucfirst(explode('.', $viewHelperName)[0]);

        foreach ($namespaces as $namespace) {
            $contextClass = $namespace . '\\' . $ucFirstComponentBaseName . 'Context';
            if (class_exists($contextClass) && is_subclass_of($contextClass, AbstractComponentContext::class)) {
                return $contextClass;
            }
        }

        return $baseClass;
    }
}
