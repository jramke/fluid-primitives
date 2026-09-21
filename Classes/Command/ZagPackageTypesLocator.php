<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

/**
 * Finds an installed npm package's own `.d.ts` type declarations (via {@see NodeModulesPackageResolver})
 * and resolves one named, possibly re-exported-under-another-name export from them to raw source
 * text - split out from {@see HydrationPropsSourceResolver} purely to keep that class's own
 * complexity down. Only ever asked for a Zag package's `Props` type today, but nothing here is
 * Zag-specific.
 */
final readonly class ZagPackageTypesLocator
{
    public function __construct(
        private NodeModulesPackageResolver $packageResolver,
    ) {}

    /**
     * @return string|null The combined text of the package's own entry `.d.ts`, the sub-file
     *   `$exportedName` is re-exported from (as Zag packages' own `Props` type always is), and -
     *   one bounded extra hop, not open-ended recursion - any *other* npm package that sub-file
     *   itself imports from (e.g. `number-input.types.d.ts` importing `LocaleProperties` from
     *   `@zag-js/types`, which `NumberInputProps` extends) - null when the package or the export
     *   can't be located at all.
     */
    public function locateHaystack(string $packageName, string $exportedName, string $searchFromDir): ?string
    {
        $entryFile = $this->packageResolver->resolveEntryFile($packageName, $searchFromDir);
        if ($entryFile === null) {
            return null;
        }

        $entryContent = (string)file_get_contents($entryFile);
        $haystack = $entryContent;

        $subFile = $this->resolveReExportedSubFile($entryContent, dirname($entryFile), $exportedName);
        if ($subFile === null) {
            return $haystack;
        }

        $subFileContent = (string)file_get_contents($subFile);
        $haystack .= "\n" . $subFileContent;
        $haystack .= $this->haystackForImportedPackages($subFileContent, $searchFromDir);

        return $haystack;
    }

    /**
     * A Props interface's own `extends X, Y` often reaches into a type declared in a *different*
     * Zag package entirely (e.g. `@zag-js/types`'s own `LocaleProperties`/`CommonProperties`) -
     * appends each such package's own entry `.d.ts` (not chased any further) so `hasKey()` can
     * still find a field declared there.
     */
    private function haystackForImportedPackages(string $subFileContent, string $searchFromDir): string
    {
        $matches = [];
        if (preg_match_all('/import\s+\{[^}]*\}\s+from\s+[\'"]([^\'"]+)[\'"]/', $subFileContent, $matches) === 0) {
            return '';
        }

        $extra = '';
        foreach (array_unique($matches[1]) as $importedPackage) {
            if (str_starts_with($importedPackage, '.')) {
                continue; // relative import - already part of the same package, not a separate one.
            }

            $entryFile = $this->packageResolver->resolveEntryFile($importedPackage, $searchFromDir);
            if ($entryFile !== null) {
                $extra .= "\n" . (string)file_get_contents($entryFile);
            }
        }

        return $extra;
    }

    /**
     * Finds `export { ... X as $exportedName ... } from './y.js';` (or a bare `$exportedName`
     * without renaming) in `$fileContent`, and resolves `./y.js` to its sibling `.d.ts` file.
     */
    private function resolveReExportedSubFile(string $fileContent, string $baseDir, string $exportedName): ?string
    {
        $matches = [];
        if (
            preg_match_all(
                '/export\s+(?:type\s+)?\{([^}]*)\}\s+from\s+[\'"]([^\'"]+)[\'"]/',
                $fileContent,
                $matches,
                PREG_SET_ORDER,
            ) === 0
        ) {
            return null;
        }

        foreach ($matches as $match) {
            $subFile = $this->matchExportedNameToSubFile($match[1], $match[2], $baseDir, $exportedName);
            if ($subFile !== null) {
                return $subFile;
            }
        }

        return null;
    }

    private function matchExportedNameToSubFile(
        string $exportList,
        string $source,
        string $baseDir,
        string $exportedName,
    ): ?string {
        foreach (explode(',', $exportList) as $entry) {
            $parts = array_map(trim(...), explode(' as ', trim($entry)));
            $asName = $parts[1] ?? $parts[0];

            if ($asName !== $exportedName) {
                continue;
            }

            $subFile = $baseDir . '/' . (string)preg_replace('/\.js$/', replacement: '.d.ts', subject: $source);
            return is_file($subFile) ? $subFile : null;
        }

        return null;
    }
}
