<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\BaseContext;
use Jramke\FluidPrimitives\Service\ContextService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class ComponentUtility
{
    private static array $cachedSettings = [];

    // Keep in sync with: Resources/Private/Client/src/lib/hydration.ts
    private const ID_NAMESPACE_OVERRIDES = [
        'navigation-menu' => 'nav-menu',
        'clipboard' => 'clip',
        'file-upload' => 'file',
    ];

    // Maps a component's `ui:ref` part name to the enclosing Field's `fieldIds` key ('label' or
    // 'control') it represents. Keep in sync with each field-aware Primitive's `propsWithField()`
    // override in its .ts file (client-side counterpart, via field.dom.ts's getLabelId/getControlId).
    private const FIELD_ID_PARTS = [
        'select' => ['label' => 'label', 'control' => 'hiddenSelect'],
        'combobox' => ['label' => 'label', 'control' => 'input'],
        'number-input' => ['label' => 'label', 'control' => 'input'],
        'switch' => ['label' => 'label', 'control' => 'hiddenInput'],
        'checkbox' => ['label' => 'label', 'control' => 'hiddenInput'],
        'file-upload' => ['label' => 'label', 'control' => 'hiddenInput'],
        'checkbox-group' => ['label' => 'label'],
    ];

    private const FIELD_ID_OVERRIDE_KEYS = ['label', 'control'];

    // A component's FIELD_ID_PARTS override is suppressed while an ancestor context of this name
    // is on the ContextService stack. Mirrors Checkbox.ts's client-side getClosestCheckboxGroup()
    // check: a checkbox nested in a CheckboxGroup must not claim the enclosing Field's label/control
    // id for itself - each checkbox in the group has its own, separate hidden input, so all of them
    // doing so would produce duplicate ids. The group itself (not the individual checkbox) owns it.
    private const FIELD_ID_EXCLUDED_WHEN_NESTED_IN = [
        'checkbox' => ['checkbox-group'],
    ];

    // Keep in sync with: Resources/Private/Client/src/lib/hydration.ts
    private const PART_SEGMENT_OVERRIDES = [
        // TODO: Revisit this override map after upgrading to zag-js v2.
        'radio-group' => [
            'item' => 'radio',
            'itemHiddenInput' => 'radio:input',
            'itemControl' => 'radio:control',
            'itemText' => 'radio:label',
        ],
        'accordion' => [
            'itemTrigger' => 'trigger',
            'itemContent' => 'content',
        ],
        'select' => [
            'hiddenSelect' => 'select',
            'itemGroup' => 'optgroup',
            'itemGroupLabel' => 'optgroup-label',
            'item' => 'option',
        ],
        'combobox' => [
            'positioner' => 'popper',
            'trigger' => 'toggle-btn',
            'clearTrigger' => 'clear-btn',
            'itemGroup' => 'optgroup',
            'itemGroupLabel' => 'optgroup-label',
            'item' => 'option',
        ],
        'tabs' => [
            'trigger' => ['segment' => 'trigger', 'valueSeparator' => '-'],
            'content' => ['segment' => 'content', 'valueSeparator' => '-'],
        ],
        'number-input' => [
            'incrementTrigger' => 'inc',
            'decrementTrigger' => 'dec',
        ],
        'popover' => [
            'positioner' => 'popper',
            'description' => 'desc',
            'closeTrigger' => 'close',
        ],
        'switch' => [
            'hiddenInput' => 'input',
        ],
        'file-upload' => [
            'hiddenInput' => 'input',
            'itemSizeText' => 'item-size',
            'itemDeleteTrigger' => 'item-delete',
        ],
        'tooltip' => [
            'positioner' => 'popper',
        ],
        'dialog' => [
            'closeTrigger' => 'close',
        ],
        'scroll-area' => [
            'root' => ['segment' => 'root', 'rootIdSeparator' => '-'],
            'viewport' => ['segment' => 'viewport', 'rootIdSeparator' => '-'],
            'content' => ['segment' => 'content', 'rootIdSeparator' => '-'],
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
        ['segment' => $partSegment, 'valueSeparator' => $valueSeparator, 'rootIdSeparator' => $rootIdSeparator] = self::getPartConfig($componentName, $part);

        if ($part === 'root') {
            return "{$idNamespace}{$rootIdSeparator}{$rootId}";
        }

        if ($value !== null && $value !== '') {
            return "{$idNamespace}{$rootIdSeparator}{$rootId}:{$partSegment}{$valueSeparator}{$value}";
        }

        return "{$idNamespace}{$rootIdSeparator}{$rootId}:{$partSegment}";
    }

    public static function getOverrideFieldIdKey(string $componentName, string $part): ?string
    {
        return self::FIELD_ID_PARTS[$componentName][$part] ?? null;
    }

    public static function shouldSkipFieldIdsInheritanceWhenNestedIn(string $nestedComponent): array
    {
        return self::FIELD_ID_EXCLUDED_WHEN_NESTED_IN[$nestedComponent] ?? [];
    }

    public static function getFieldIdOverrideKeys(): array
    {
        return self::FIELD_ID_OVERRIDE_KEYS ?? [];
    }

    private static function getIdNamespace(string $componentName): string
    {
        return self::ID_NAMESPACE_OVERRIDES[$componentName] ?? $componentName;
    }

    /**
     * @return array{segment: string, valueSeparator: string, rootIdSeparator: string}
     */
    private static function getPartConfig(string $componentName, string $part): array
    {
        $override = self::PART_SEGMENT_OVERRIDES[$componentName][$part] ?? null;
        if (!is_array($override)) {
            return [
                'segment' => is_string($override) ? $override : $part,
                'valueSeparator' => ':',
                'rootIdSeparator' => ':',
            ];
        }

        $segment = (string)($override['segment'] ?? $part);
        $valueSeparator = (string)($override['valueSeparator'] ?? ':');
        $rootIdSeparator = (string)($override['rootIdSeparator'] ?? ':');
        return ['segment' => $segment, 'valueSeparator' => $valueSeparator, 'rootIdSeparator' => $rootIdSeparator];
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
