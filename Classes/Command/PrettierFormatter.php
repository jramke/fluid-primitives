<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Symfony\Component\Process\Process;

/**
 * Runs generated TS content through this monorepo's own Prettier config before it's written or
 * compared, so `ui:generate-hydration-types --check` diffs against the *canonically formatted*
 * shape the same command would actually commit - without this, every run would report false drift
 * purely from this class's own (merely reasonable, not `.prettierrc`-exact) formatting differing
 * from what a human contributor's editor/pre-commit hook would reformat it to anyway.
 *
 * Formats by actually writing `$content` to `$workPath` and running `prettier --write` on that
 * real file in place, rather than piping through `--stdin-filepath` - this monorepo's own
 * `.prettierrc` loads `prettier-plugin-organize-imports`, which resolves each import through the
 * TypeScript language service and silently no-ops against a virtual stdin path that doesn't exist
 * on disk. `$workPath` must sit in the file's real target directory (its own `<Name>.hydration.ts`
 * for a write, an `*.check.ts` sibling for `--check`) so that resolution - and this file's own
 * relative imports - still work; the caller owns whether that's the real target or a scratch file.
 *
 * Degrades gracefully (returns `$content` unchanged, `$workPath` left holding it) when
 * `npx`/prettier/`node_modules` can't be found - expected for a third-party extension with no npm
 * pipeline of its own (see the plan's own note on third-party distribution being a "distinctly
 * different, more manual workflow").
 */
final class PrettierFormatter
{
    public function format(string $content, string $workPath, string $searchFromDir): string
    {
        file_put_contents($workPath, $content);

        $projectRoot = $this->findNodeModulesRoot($searchFromDir);
        if ($projectRoot === null) {
            return $content;
        }

        // $workPath commonly runs through TYPO3's own vendor/<ext-key> symlink
        // (ExtensionManagementUtility::extPath()), and .prettierignore ignores `vendor` wholesale -
        // Prettier silently no-ops (exit 0, file untouched) on an ignored path instead of erroring,
        // so this resolves to the real, non-ignored path first.
        $realWorkPath = realpath($workPath) ?: $workPath;

        $process = new Process(['npx', 'prettier', '--write', $realWorkPath], $projectRoot);
        $process->setTimeout(30);

        try {
            $process->mustRun();
        } catch (\Throwable) {
            return $content;
        }

        return (string)file_get_contents($realWorkPath);
    }

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
}
