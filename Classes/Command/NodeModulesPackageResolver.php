<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

/**
 * Locates an installed npm package's own type-declarations entry file, walking up from a given
 * directory to find `node_modules` first - split out from {@see ZagPackageTypesLocator} purely to
 * keep that class's own complexity down.
 */
final class NodeModulesPackageResolver
{
    public function resolveEntryFile(string $packageName, string $searchFromDir): ?string
    {
        $nodeModulesRoot = $this->findNodeModulesRoot($searchFromDir);
        if ($nodeModulesRoot === null) {
            return null;
        }

        $packageDir = $nodeModulesRoot . '/node_modules/' . $packageName;

        $typesPath = $this->readDeclaredTypesPath($packageDir . '/package.json');
        if ($typesPath !== null) {
            $resolved = $packageDir . '/' . ltrim($typesPath, characters: '/');
            if (is_file($resolved)) {
                return $resolved;
            }
        }

        $default = $packageDir . '/dist/index.d.ts';
        return is_file($default) ? $default : null;
    }

    /**
     * Walks up from `$startDir` looking for a `node_modules` folder - the monorepo root is several
     * levels above any primitive's own `Resources/Private/Primitives/<Name>` folder, and the exact
     * depth isn't a contract worth hard-coding.
     */
    private function findNodeModulesRoot(string $startDir): ?string
    {
        $dir = $startDir;

        for ($i = 0; $i < 12; $i++) {
            if (is_dir($dir . '/node_modules')) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }

            $dir = $parent;
        }

        return null;
    }

    private function readDeclaredTypesPath(string $packageJsonFile): ?string
    {
        if (!is_file($packageJsonFile)) {
            return null;
        }

        // package.json is arbitrary JSON with no static shape here - narrowed immediately below
        // via is_array()/is_string().
        // @mago-expect analysis:mixed-assignment
        $packageJson = json_decode((string)file_get_contents($packageJsonFile), associative: true);
        if (!is_array($packageJson)) {
            return null;
        }

        // @mago-expect analysis:mixed-assignment
        $exports = $packageJson['exports'] ?? null;
        if (is_array($exports)) {
            // @mago-expect analysis:mixed-assignment
            $rootExport = $exports['.'] ?? $exports;
            if (is_array($rootExport) && is_string($rootExport['types'] ?? null)) {
                return $rootExport['types'];
            }
        }

        return is_string($packageJson['types'] ?? null) ? $packageJson['types'] : null;
    }
}
