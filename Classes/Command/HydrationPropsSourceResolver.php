<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

/**
 * Resolves a primitive's own `<Name>Props` TS type from its `<Name>.ts` class file, for
 * {@see GenerateHydrationTypesCommand} to `Pick` client props from - the "does this codebase's
 * `extends Component<XProps, ...>` convention hold" half of the plan's "Wire type vs. machine type"
 * precedence order. A convention bet, not a guarantee (see the plan's own "Problems" section): this
 * is deliberately simple regex/string matching over the class file's own import statements, not a
 * real TS parser or filesystem walk of the resolved package - once a Props source resolves, every
 * one of its declared props is `Pick`ed, with no filesystem-backed check that a given prop name
 * actually exists on it. A wrong guess here isn't silently wrong for the common case: `Pick<T, K>`
 * requires `K extends keyof T`, so `npm run types`/`ui:generate-hydration-types --check` catches it
 * as a real type error against the raw source file - except through a *bundled* `.d.ts` consumer
 * (any downstream package importing the built `dist/*.d.ts` with `skipLibCheck` on, which this
 * monorepo's own docs package has), where that constraint violation is silently resolved to
 * `unknown` instead of erroring. {@see HydrationPropsTypeSource::$excludedKeys} is the one narrow
 * exception where this class still positively knows a key can't be `Pick`ed - derived from the
 * class file's own text, never a filesystem lookup.
 */
final class HydrationPropsSourceResolver
{
    /**
     * @return HydrationPropsTypeSource|null Null when the class doesn't follow the
     *   `extends (FieldAwareComponent|Component)<X, Y>` convention at all, or when `X` can't be
     *   traced back to any importable type whatsoever - callers fall back to scalar/enum mapping
     *   per-prop in that case. A locally-declared, non-exported alias type that *extends* a
     *   namespace-imported Zag type with its own additional members (e.g. FileUpload's own `type
     *   FileUploadPrimitiveProps = fileUpload.Props & { existingFilesCount?: number }`) still
     *   resolves - see {@see resolveLocalIntersectionType} - but with those local-only members
     *   listed in the result's {@see HydrationPropsTypeSource::$excludedKeys}, since they aren't
     *   really part of the Zag type being `Pick`ed from.
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
     *
     * @param list<string> $excludedKeys See {@see HydrationPropsTypeSource::$excludedKeys}.
     */
    private function resolveNamespaced(
        string $classFileContent,
        string $propsExpression,
        string $aliasTypeName,
        array $excludedKeys = [],
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

        return new HydrationPropsTypeSource(
            $aliasTypeName,
            sprintf("import type { Props as %s } from '%s';", $aliasTypeName, $packageName),
            $excludedKeys,
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

    /**
     * Matches `type X = y.Props;` and `type X = y.Props & { extra: string };` alike - the optional
     * `& { ... }` group's own field names (parsed straight out of this same match, not looked up
     * anywhere) become {@see HydrationPropsTypeSource::$excludedKeys}, since they belong to the
     * local extension, not to `y.Props` itself.
     */
    private function resolveLocalIntersectionType(
        string $classFileContent,
        string $typeName,
        string $aliasTypeName,
    ): ?HydrationPropsTypeSource {
        $matches = [];
        if (
            preg_match(
                '/\btype\s+' .
                preg_quote($typeName, delimiter: '/') .
                '\s*=\s*(\w+)\.Props(?:\s*&\s*\{([^}]*)\})?\s*;/',
                $classFileContent,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $excludedKeys = $this->extractLocalExtensionKeys($matches[2] ?? '');

        return $this->resolveNamespaced($classFileContent, $matches[1] . '.Props', $aliasTypeName, $excludedKeys);
    }

    /**
     * @return list<string>
     */
    private function extractLocalExtensionKeys(string $objectLiteralBody): array
    {
        $matches = [];
        if (preg_match_all('/(\w+)\??\s*:/', $objectLiteralBody, $matches) === 0) {
            return [];
        }

        return array_values($matches[1]);
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

        return new HydrationPropsTypeSource($typeName, sprintf(
            "import type { %s } from '%s';",
            $typeName,
            $importSource,
        ));
    }
}
