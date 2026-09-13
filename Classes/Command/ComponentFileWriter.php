<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\RegistryService;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Fetches and writes a component's files to disk for `ui:add`, then reports the outcome and flushes
 * the page cache if anything actually changed.
 */
final readonly class ComponentFileWriter
{
    public function __construct(
        private RegistryService $registryService,
        private CacheManager $cacheManager,
    ) {}

    /**
     * Fetches and writes each component file to disk, skipping existing files unless `force` is set.
     *
     * @param string[] $files
     * @param array{targetFolder: string, useFluidSuffix: bool, force: bool} $options
     * @return array{skipped: bool, updated: bool, created: bool}
     */
    public function write(SymfonyStyle $io, string $componentKey, array $files, array $options): array
    {
        ['targetFolder' => $targetFolder, 'useFluidSuffix' => $useFluidSuffix, 'force' => $force] = $options;

        $someSkipped = false;
        $someUpdated = false;
        $someCreated = false;

        foreach ($files as $file) {
            [$error, $content] = $this->registryService->fetchComponentFile($componentKey, $file);
            if ($error) {
                $io->warning($error['message']);
                continue;
            }

            $targetFileName = $this->resolveTargetFileName($file, $useFluidSuffix);
            $targetFilePath = $targetFolder . $targetFileName;

            $targetDir = dirname($targetFilePath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0o777, true);
            }

            if (file_exists($targetFilePath)) {
                if (!$force) {
                    $io->writeln('Skipped: ' . $targetFileName);
                    $someSkipped = true;
                    continue;
                }

                file_put_contents($targetFilePath, $content);
                $io->writeln('Updated: ' . $targetFileName);
                $someUpdated = true;
                continue;
            }

            file_put_contents($targetFilePath, $content);
            $io->writeln('Created: ' . $targetFileName);
            $someCreated = true;
        }

        return ['skipped' => $someSkipped, 'updated' => $someUpdated, 'created' => $someCreated];
    }

    /**
     * @param array{skipped: bool, updated: bool, created: bool} $writeResult
     */
    public function reportResult(SymfonyStyle $io, string $componentKey, string $extension, array $writeResult): void
    {
        ['skipped' => $someSkipped, 'updated' => $someUpdated, 'created' => $someCreated] = $writeResult;

        if ($someSkipped && !$someCreated && !$someUpdated) {
            $io->warning([
                'Component "' . $componentKey . '" already exists in extension "' . $extension . '".',
                'No files were changed.',
                'Use the --force option to overwrite existing files.',
            ]);
            return;
        }

        if ($someSkipped) {
            $io->writeln(
                '<comment>Some files were skipped. Use the --force option to overwrite existing files.</comment>',
            );
        }

        if ($someUpdated) {
            $io->success('Component "' . $componentKey . '" updated in extension "' . $extension . '".');
        } elseif ($someCreated) {
            $io->success('Component "' . $componentKey . '" added to extension "' . $extension . '".');
        }

        $this->cacheManager->flushCachesInGroup('pages');
    }

    public function shouldUseFluidSuffixByDefault(): bool
    {
        return (new Typo3Version())->getMajorVersion() >= 14;
    }

    private function resolveTargetFileName(string $file, bool $useFluidSuffix): string
    {
        if (!str_ends_with($file, '.html')) {
            return $file;
        }

        $baseName = str_ends_with($file, '.fluid.html')
            ? substr($file, 0, -strlen('.fluid.html'))
            : substr($file, 0, -strlen('.html'));

        return $useFluidSuffix ? $baseName . '.fluid.html' : $baseName . '.html';
    }
}
