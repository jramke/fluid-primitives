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
        $componentProp = Typed::arrayOrNull($renderingContext->getVariableProvider()->get('component'));
        return (
            $componentProp !== null &&
            is_string($componentProp['fullName'] ?? null) &&
            $componentProp['fullName'] !== ''
        );
    }

    /**
     * Reads whichever `rootId` this component actually needs - its own, direct `rootId` Fluid
     * variable for a declared-root template, or its nearest ancestor's via `context.rootId`
     * otherwise. Deliberately keyed off the *declared* flag, not the effective one: a component's
     * own `rootId` variable is only ever visible on its own internal rendering context, which a
     * declared-root template always has - whether or not it's effectively delegating via
     * `spreadProps` - because it's always reached via a direct nested component render call. A
     * genuinely composable (non-declared-root) child, by contrast, is authored as slot content and
     * evaluated against the *calling* rendering context instead, where `rootId` was never assigned -
     * only `context.rootId` (via {@see \Jramke\FluidPrimitives\Service\ContextService}'s stack)
     * survives that boundary.
     */
    public static function getRootIdFromContext(RenderingContextInterface $renderingContext): string
    {
        $isDeclaredRoot = ComponentRootUtility::isDeclaredRootFromContext($renderingContext);
        return Typed::string(
            $isDeclaredRoot
                ? $renderingContext->getVariableProvider()->getByPath('rootId')
                : $renderingContext->getVariableProvider()->getByPath('context.rootId'),
        );
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

        $fluidPrimitivesSettings = Typed::arrayOrNull(
            $settings['plugin.']['tx_fluidprimitives.']['settings.'] ?? null,
        ) ?? [];

        $contentElementSettings = Typed::arrayOrNull($settings['lib.']['contentElement.']['settings.'] ?? null) ?? [];
        if ($contentElementSettings !== []) {
            $fluidPrimitivesSettings = array_merge($contentElementSettings, $fluidPrimitivesSettings);
        }

        self::$cachedSettings = GeneralUtility::removeDotsFromTS($fluidPrimitivesSettings);
        return self::$cachedSettings;
    }

    /**
     * Resolves the Context class for a root component by mirroring its own template's folder
     * structure as a PHP namespace, rather than prefixing the class name with it - so an
     * atomic-design tier folder becomes a namespace segment (`Contexts\Atoms\ButtonContext`, not
     * `AtomsButtonContext`), and a nested example/demo component never accidentally resolves to an
     * unrelated ancestor's or sibling's context class purely because they happen to share a first
     * or last name segment (e.g. `Icon/Menu` must resolve to `Contexts\Icon\MenuContext`, never the
     * real, unrelated `Contexts\MenuContext`).
     *
     * $resolvedTemplateName is the `/`-separated path from
     * {@see AbstractComponentCollection::resolveTemplateName()}, e.g. "CheckboxGroup/Examples/SelectAll"
     * or "Accordion/Root" - not the raw dotted ViewHelper name.
     *
     * @param string[] $additionalNamespaces
     * @return class-string<ComponentContextInterface>
     */
    public static function getContextClassNameFromViewHelperName(
        string $resolvedTemplateName,
        array $additionalNamespaces,
    ): string {
        $baseClass = BaseContext::class;
        $backslashPosition = strrpos($baseClass, needle: '\\');
        $baseNamespace = $backslashPosition === false ? '' : substr($baseClass, offset: 0, length: $backslashPosition);

        $namespaces = array_merge($additionalNamespaces, [$baseNamespace]);
        [$namespaceSuffix, $identityName] = self::splitResolvedTemplateNameIntoContextIdentity($resolvedTemplateName);

        foreach ($namespaces as $namespace) {
            $contextClass =
                $namespace .
                ($namespaceSuffix !== '' ? '\\' . $namespaceSuffix : '') .
                '\\' .
                $identityName .
                'Context';
            if (class_exists($contextClass) && is_subclass_of($contextClass, AbstractComponentContext::class)) {
                return $contextClass;
            }
        }

        return $baseClass;
    }

    /**
     * Splits a resolved template path ("CheckboxGroup/Examples/SelectAll") into the namespace path
     * mirroring its own containing folder ("CheckboxGroup\Examples") and the component's own
     * identity name ("SelectAll") that becomes the class name.
     *
     * The file's own name IS that identity, unless it's either literally "Root" or repeats its own
     * containing folder's name (Root.html + parts, or a single-file component) - in that case the
     * file itself adds no identity of its own, so its *folder's* name is used instead, and dropped
     * from the namespace path so it isn't duplicated.
     *
     * @return array{0: string, 1: string}
     */
    private static function splitResolvedTemplateNameIntoContextIdentity(string $resolvedTemplateName): array
    {
        $segments = explode('/', $resolvedTemplateName);
        $ownName = array_pop($segments);
        $folderName = $segments === [] ? null : end($segments);

        if ($folderName !== null && (strtolower($ownName) === 'root' || $folderName === $ownName)) {
            // $folderName already IS $segments's current last element (just peeked via end() above,
            // not removed) - reuse it instead of a second array_pop() so the popped identity value
            // and the removal of that element from $segments can't drift apart from each other.
            array_pop($segments);
            return [implode('\\', $segments), $folderName];
        }

        return [implode('\\', $segments), $ownName];
    }
}
