<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Utility\ContainedPath;
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

        /** @var bool[] $written Whether each file written replaced one that existed */
        $written = [];
        $someFailed = false;
        $usesViteAsset = false;

        foreach ($files as $file) {
            // the names come from the registry's answer, so one must not lead out of the component folder
            if (!$this->isSafeRelativePath($file)) {
                $io->warning('Skipped ' . $this->quote($file) . ': it is not a path inside the component folder.');
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

            $updated = file_exists($targetFilePath);
            if (!$this->writeFile($targetFolder, $targetFilePath, (string)$content)) {
                $io->warning('Could not write ' . $this->quote($targetFileName) . ' inside the component folder.');
                $someFailed = true;
                continue;
            }

            $io->writeln(($updated ? 'Updated: ' : 'Created: ') . $targetFileName);
            $written[] = $updated;
        }

        return [
            'updated' => in_array(true, $written, strict: true),
            'created' => in_array(false, $written, strict: true),
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
     * Whether `$path` stays below the folder it is joined to: not absolute, no empty, `.` or `..` segment, no
     * backslash or control character.
     */
    public function isSafeRelativePath(string $path): bool
    {
        return (
            $path !== '' &&
            !str_starts_with($path, '/') &&
            preg_match('/[\\\\\x00-\x1f\x7f]/', $path) === 0 &&
            array_diff(explode('/', $path), ['', '.', '..']) === explode('/', $path)
        );
    }

    /**
     * A file name comes from the registry, so it must not bring control characters or terminal escape sequences
     * into the output.
     */
    private function quote(string $name): string
    {
        return json_encode($name, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    /**
     * Writes into the component folder and nowhere else: a symlink that leads out of it, in the folder or as the
     * file itself, is not followed.
     */
    private function writeFile(string $targetFolder, string $targetFilePath, string $content): bool
    {
        $targetDir = dirname($targetFilePath);
        if (!is_dir($targetDir) && !mkdir($targetDir, permissions: 0o755, recursive: true) && !is_dir($targetDir)) {
            return false;
        }

        return (
            ContainedPath::isInside($targetFolder, $targetDir) &&
            !is_link($targetFilePath) &&
            file_put_contents($targetFilePath, $content) !== false
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
