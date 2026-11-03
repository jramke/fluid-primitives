<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Exception\ReservedComponentNameException;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;

/**
 * The rule behind `<component>.provider`: every declared root whose folder also holds a part gets one,
 * without a template of its own. Its name is the root's folder path plus `.provider`
 * (`dialog.root` → `dialog.provider`, `button` → `button.provider`), and `Provider` is reserved in such
 * a folder.
 */
final class ComponentProviderUtility
{
    public const string NAME = 'provider';

    /**
     * The root `$viewHelperName` provides, or `null` when it is no provider: another name, a real
     * template, or a root without parts.
     */
    public static function resolveProvidedRoot(
        string $viewHelperName,
        ComponentTemplateResolverInterface $templateResolver,
    ): ?string {
        $segments = explode('.', $viewHelperName);
        $ownName = array_pop($segments);
        if ($segments === [] || strtolower($ownName) !== self::NAME) {
            return null;
        }

        $templateName = $templateResolver->resolveTemplateName($viewHelperName);
        if (self::templateExists($templateName, $templateResolver)) {
            return null;
        }

        $directory = dirname($templateName);
        $prefix = implode('.', $segments);
        foreach ([$prefix . '.root', $prefix] as $candidate) {
            $candidateTemplateName = $templateResolver->resolveTemplateName($candidate);
            if (
                dirname($candidateTemplateName) !== $directory ||
                !self::templateExists($candidateTemplateName, $templateResolver) ||
                !ComponentRootUtility::isDeclaredRootFromViewHelperName($candidate, $templateResolver)
            ) {
                continue;
            }

            return count(self::templateNamesIn($directory, $templateResolver)) > 1 ? $candidate : null;
        }

        return null;
    }

    /**
     * The provider of the declared root `$rootViewHelperName`, or `null` if it has none.
     */
    public static function getProviderName(
        string $rootViewHelperName,
        ComponentTemplateResolverInterface $templateResolver,
    ): ?string {
        $prefix = preg_replace('/\.root$/i', replacement: '', subject: $rootViewHelperName) ?? $rootViewHelperName;
        $providerName = $prefix . '.' . self::NAME;

        return self::resolveProvidedRoot($providerName, $templateResolver) === $rootViewHelperName
            ? $providerName
            : null;
    }

    /**
     * Throws when `$viewHelperName` is a real `Provider` template next to a root, which its folder's
     * provider would shadow.
     */
    public static function assertNotReserved(
        string $viewHelperName,
        ComponentTemplateResolverInterface $templateResolver,
    ): void {
        $templateName = $templateResolver->resolveTemplateName($viewHelperName);
        if (strtolower(basename($templateName)) !== self::NAME) {
            return;
        }

        $directory = dirname($templateName);
        $names = array_map(strtolower(...), self::templateNamesIn($directory, $templateResolver));
        if (
            !in_array('root', $names, strict: true) && !in_array(strtolower(basename($directory)), $names, strict: true)
        ) {
            return;
        }

        throw new ReservedComponentNameException(
            sprintf(
                'The template "%s" is named like the provider of the root component in "%s". Rename it, "Provider" is reserved next to a root.',
                $templateName,
                $directory,
            ),
            1_789_100_001,
        );
    }

    private static function templateExists(
        string $templateName,
        ComponentTemplateResolverInterface $templateResolver,
    ): bool {
        return (
            $templateResolver->getTemplatePaths()->resolveTemplateFileForControllerAndActionAndFormat(
                'Default',
                $templateName,
            ) !== null
        );
    }

    /**
     * The template names (without extension) directly inside `$directory`, across all template root paths.
     *
     * @return list<string>
     */
    private static function templateNamesIn(
        string $directory,
        ComponentTemplateResolverInterface $templateResolver,
    ): array {
        $templatePaths = $templateResolver->getTemplatePaths();
        $pattern = '/^(.+?)(?:\.fluid)?\.' . preg_quote($templatePaths->getFormat(), delimiter: '/') . '$/';

        $names = [];
        foreach ($templatePaths->getTemplateRootPaths() as $rootPath) {
            $path = rtrim($rootPath, characters: '/') . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }

            foreach (scandir($path) ?: [] as $file) {
                if (preg_match($pattern, $file, $matches) !== 1 || !is_file($path . '/' . $file)) {
                    continue;
                }

                $names[$matches[1]] = true;
            }
        }

        return array_keys($names);
    }
}
