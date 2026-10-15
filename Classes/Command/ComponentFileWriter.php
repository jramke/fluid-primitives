<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\RegistryService;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;

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
     * Fetches and writes each component file to disk, overwriting existing ones: the caller decides
     * beforehand (see {@see existingTargets()}) whether that is allowed.
     * `%componentPath%` in the templates becomes `componentPath`, the `EXT:` path of the component folder.
     *
     * @param string[] $files
     * @param array{targetFolder: string, useFluidSuffix: bool, componentPath: string} $options
     * @return array{updated: bool, created: bool, failed: bool, usesViteAsset: bool}
     */
    public function write(SymfonyStyle $io, string $componentKey, array $files, array $options): array
    {
        [
            'targetFolder' => $targetFolder,
            'useFluidSuffix' => $useFluidSuffix,
            'componentPath' => $componentPath,
        ] = $options;

        $someUpdated = false;
        $someCreated = false;
        $someFailed = false;
        $usesViteAsset = false;

        foreach ($files as $file) {
            // the names come from the registry's answer, so one must not lead out of the component folder
            if (!$this->isSafeRelativePath($file)) {
                $io->warning('Skipped "' . $file . '": it is not a path inside the component folder.');
                $someFailed = true;
                continue;
            }

            [$error, $content] = $this->registryService->fetchComponentFile($componentKey, $file);
            if ($error) {
                $io->warning($error['message']);
                $someFailed = true;
                continue;
            }

            $targetFileName = $this->resolveTargetFileName($file, $useFluidSuffix);
            $targetFilePath = $targetFolder . $targetFileName;

            if (str_ends_with($file, '.html')) {
                $content = str_replace('%componentPath%', $componentPath, (string)$content);
                $usesViteAsset = $usesViteAsset || str_contains($content, '<vite:asset');
            }

            $targetDir = dirname($targetFilePath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, permissions: 0o777, recursive: true);
            }

            if (file_exists($targetFilePath)) {
                file_put_contents($targetFilePath, $content);
                $io->writeln('Updated: ' . $targetFileName);
                $someUpdated = true;
                continue;
            }

            file_put_contents($targetFilePath, $content);
            $io->writeln('Created: ' . $targetFileName);
            $someCreated = true;
        }

        return [
            'updated' => $someUpdated,
            'created' => $someCreated,
            'failed' => $someFailed,
            'usesViteAsset' => $usesViteAsset,
        ];
    }

    /**
     * The target file names, relative to the component folder, that already exist on disk.
     *
     * @param string[] $files
     * @return string[]
     */
    public function existingTargets(array $files, string $targetFolder, bool $useFluidSuffix): array
    {
        $existing = [];
        foreach ($files as $file) {
            if (!$this->isSafeRelativePath($file)) {
                continue;
            }
            $targetFileName = $this->resolveTargetFileName($file, $useFluidSuffix);
            if (file_exists($targetFolder . $targetFileName)) {
                $existing[] = $targetFileName;
            }
        }

        return $existing;
    }

    /**
     * Whether `$path` stays below the folder it is joined to: not absolute, no `..` segment, no backslash or
     * control character.
     */
    public function isSafeRelativePath(string $path): bool
    {
        return (
            $path !== '' &&
            !str_starts_with($path, '/') &&
            preg_match('/[\\\\\x00-\x1f]/', $path) === 0 &&
            !in_array('..', explode('/', $path), strict: true)
        );
    }

    /**
     * @param array{updated: bool, created: bool, failed: bool, usesViteAsset: bool} $writeResult
     */
    public function reportResult(SymfonyStyle $io, string $componentKey, string $extension, array $writeResult): void
    {
        ['updated' => $someUpdated, 'created' => $someCreated] = $writeResult;

        $successMessage = match (true) {
            $someUpdated => 'Component "' . $componentKey . '" updated in extension "' . $extension . '".',
            $someCreated => 'Component "' . $componentKey . '" added to extension "' . $extension . '".',
            default => null,
        };
        if ($successMessage !== null) {
            $io->success($successMessage);
        }

        if ($someCreated || $someUpdated) {
            $this->cacheManager->flushCachesInGroup('pages');
        }
    }

    private function resolveTargetFileName(string $file, bool $useFluidSuffix): string
    {
        if (!str_ends_with($file, '.html')) {
            return $file;
        }

        $baseName = str_ends_with($file, '.fluid.html')
            ? substr($file, offset: 0, length: -strlen('.fluid.html'))
            : substr($file, offset: 0, length: -strlen('.html'));

        return $useFluidSuffix ? $baseName . '.fluid.html' : $baseName . '.html';
    }
}
