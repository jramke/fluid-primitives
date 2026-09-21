<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

/**
 * Idempotently ensures a primitive's own `<Name>.ts` re-exports its generated `<Name>.hydration.ts`
 * type - required so `tsdown`'s per-primitive flat dist entry (`tsdown.config.ts`, one entry per
 * primitive) actually reaches it; a standalone, never-imported-from-`<Name>.ts` file wouldn't reach
 * `dist/<name>.d.ts` at all. The generated file's own content now comes from
 * {@see HydrationTransformedProvider}/{@see HydrationTypeScriptWriter} instead of this class - this
 * is the one piece of the old file-writing job spatie's own model has no equivalent for.
 */
final class HydrationFileWriter
{
    /**
     * Appends `export type { <Name>HydrationProps } from './<Name>.hydration';` right after
     * `<Name>.ts`'s own last top-level `import` statement, if not already present - a no-op on a
     * re-run once the line exists.
     */
    public function ensureReExport(string $classFilePath, string $primitiveName): void
    {
        $content = (string)file_get_contents($classFilePath);
        $exportLine = sprintf(
            "export type { %sHydrationProps } from './%s.hydration';",
            $primitiveName,
            $primitiveName,
        );

        if (str_contains($content, $exportLine)) {
            return;
        }

        $lines = explode("\n", $content);
        $lastImportIndex = null;
        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, 'import ')) {
                continue;
            }

            $lastImportIndex = $index;
        }

        if ($lastImportIndex === null) {
            // No imports at all is not a shape any current primitive's <Name>.ts has, but fail
            // safely rather than corrupt the file if a future one ever doesn't.
            throw new \RuntimeException(
                sprintf('Cannot find an import statement to anchor the generated re-export in "%s".', $classFilePath),
                1_788_400_003,
            );
        }

        array_splice($lines, $lastImportIndex + 1, length: 0, replacement: [$exportLine]);
        file_put_contents($classFilePath, implode("\n", $lines));
    }
}
