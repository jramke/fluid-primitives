<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

/**
 * Resolves a primitive's own `<Name>Props` TS type from its `<Name>.ts` class file, for
 * {@see GenerateHydrationTypesCommand} to `Pick` client props from - the "does this codebase's
 * `extends Component<XProps, ...>` convention hold" half of the plan's "Wire type vs. machine type"
 * precedence order. A convention bet, not a guarantee (see the plan's own "Problems" section): this
 * is deliberately simple regex/string matching, not a real TS parser, so `hasKey()` is a best-effort
 * substring search across whatever source text was found, not a scoped, brace-aware member list.
 * Every prop this misclassifies still degrades safely - it just falls through to a direct
 * scalar/enum mapping instead of a `Pick`, which is never *wrong*, only less precise about
 * referencing the upstream type - and `npm run types` catches an over-eager `Pick` of a key that
 * doesn't actually exist, since `Pick<T, K>` requires `K extends keyof T`.
 */
final class HydrationPropsSourceResolver
{
    public function __construct(
        private readonly ZagPackageTypesLocator $typesLocator,
    ) {}

    /**
     * @return HydrationPropsTypeSource|null Null when the class doesn't follow the
     *   `extends (FieldAwareComponent|Component)<X, Y>` convention at all, or when `X` can't be
     *   traced back to any importable type whatsoever (a locally-declared, non-exported alias type
     *   like FileUpload's own `FileUploadPrimitiveProps` still resolves, via its own intersected
     *   `fileUpload.Props`, to that Zag package's real Props type - see
     *   {@see resolveLocalIntersectionType}) - callers fall back to scalar/enum mapping per-prop in
     *   that case.
     */
    public function resolve(
        string $classFileContent,
        string $classFilePath,
        string $aliasTypeName,
    ): ?HydrationPropsTypeSource {
        $matches = [];
        if (
            preg_match('/extends\s+(?:FieldAwareComponent|Component)<\s*([\w.]+)\s*,/', $classFileContent, $matches) !==
            1
        ) {
            return null;
        }

        $propsExpression = $matches[1];

        return str_contains($propsExpression, '.')
            ? $this->resolveNamespaced($classFileContent, $propsExpression, $aliasTypeName)
            : $this->resolveNamedImport($classFileContent, dirname($classFilePath), $propsExpression, $aliasTypeName);
    }

    /**
     * `select.Props` style - a Zag package namespace-imported as `import * as select from
     * '@zag-js/select'`. `Props` is always the re-exported name Zag packages use for their own
     * machine's props interface (confirmed uniform across every primitive in this codebase).
     */
    private function resolveNamespaced(
        string $classFileContent,
        string $propsExpression,
        string $aliasTypeName,
    ): ?HydrationPropsTypeSource {
        [$alias] = explode('.', $propsExpression, limit: 2);

        $matches = [];
        if (
            preg_match(
                '/import\s+\*\s+as\s+' . preg_quote($alias, delimiter: '/') . '\s+from\s+[\'"]([^\'"]+)[\'"]/',
                $classFileContent,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $packageName = $matches[1];
        $haystack = $this->typesLocator->locateHaystack($packageName, 'Props', getcwd() ?: __DIR__);
        if ($haystack === null) {
            return null;
        }

        return new HydrationPropsTypeSource(
            $aliasTypeName,
            sprintf("import type { Props as %s } from '%s';", $aliasTypeName, $packageName),
            $haystack,
        );
    }

    /**
     * `FieldArrayProps` style - a hand-rolled primitive's own type, named-imported from a relative
     * path (e.g. `import type { FieldArrayApi, FieldArrayProps } from './src/field-array.types';`).
     * Reuses that same import source verbatim for the generated file's own import, since it's
     * written to the same directory as `<Name>.ts` itself.
     */
    private function resolveNamedImport(
        string $classFileContent,
        string $classFileDir,
        string $typeName,
        string $aliasTypeName,
    ): ?HydrationPropsTypeSource {
        $matches = [];
        if (
            preg_match_all(
                '/import\s+(?:type\s+)?\{([^}]*)\}\s+from\s+[\'"]([^\'"]+)[\'"]/',
                $classFileContent,
                $matches,
                PREG_SET_ORDER,
            ) !== 0
        ) {
            foreach ($matches as $match) {
                $source = $this->matchNamedImportSource($match[1], $match[2], $classFileDir, $typeName);
                if ($source !== null) {
                    return $source;
                }
            }
        }

        // Not import-able (e.g. FileUpload's own local, non-exported `type FileUploadPrimitiveProps
        // = fileUpload.Props & { existingFilesCount?: number }`) - if the local declaration
        // intersects a namespace-imported Zag type, that's still a real, importable Pick source for
        // every field it declares (just not for the handful this local type adds on top of it).
        return $this->resolveLocalIntersectionType($classFileContent, $typeName, $aliasTypeName);
    }

    private function resolveLocalIntersectionType(
        string $classFileContent,
        string $typeName,
        string $aliasTypeName,
    ): ?HydrationPropsTypeSource {
        $matches = [];
        if (
            preg_match(
                '/\btype\s+' . preg_quote($typeName, delimiter: '/') . '\s*=\s*(\w+)\.Props\b/',
                $classFileContent,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return $this->resolveNamespaced($classFileContent, $matches[1] . '.Props', $aliasTypeName);
    }

    private function matchNamedImportSource(
        string $importList,
        string $importSource,
        string $classFileDir,
        string $typeName,
    ): ?HydrationPropsTypeSource {
        $importedNames = array_map(
            static fn(string $name): string => trim(explode(' as ', trim($name))[0]),
            explode(',', $importList),
        );

        if (!in_array($typeName, $importedNames, strict: true)) {
            return null;
        }

        $resolvedPath = $classFileDir . '/' . $importSource . '.ts';
        if (!is_file($resolvedPath)) {
            return null;
        }

        return new HydrationPropsTypeSource(
            $typeName,
            sprintf("import type { %s } from '%s';", $typeName, $importSource),
            (string)file_get_contents($resolvedPath),
        );
    }
}
