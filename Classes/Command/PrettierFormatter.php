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
 * Degrades gracefully (returns `$content` unchanged) when `npx`/prettier/`node_modules` can't be
 * found - expected for a third-party extension with no npm pipeline of its own (see the plan's own
 * note on third-party distribution being a "distinctly different, more manual workflow").
 */
final class PrettierFormatter
{
    public function format(string $content, string $filePath, string $searchFromDir): string
    {
        $projectRoot = $this->findNodeModulesRoot($searchFromDir);
        if ($projectRoot === null) {
            return $content;
        }

        $process = new Process(['npx', 'prettier', '--stdin-filepath', $filePath], $projectRoot);
        $process->setInput($content);
        $process->setTimeout(30);

        try {
            $process->mustRun();
        } catch (\Throwable) {
            return $content;
        }

        return $process->getOutput();
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
