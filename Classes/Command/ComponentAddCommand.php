<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\PackageResolver;
use Jramke\FluidPrimitives\Service\RegistryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageInterface;

#[AsCommand(name: 'ui:add', description: 'Add a new component from Fluid Primitives')]
class ComponentAddCommand extends Command
{
    public function __construct(
        protected readonly PackageResolver $packageResolver,
        protected readonly CacheManager $cacheManager,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly RegistryService $registryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add a new component from Fluid Primitives');
        $this->addArgument(
            'component',
            InputArgument::REQUIRED,
            'The name of the component to add (e.g., accordion or scroll-area)',
        );
        $this->addOption(
            'extension',
            '',
            InputOption::VALUE_OPTIONAL,
            'Host extension in which the Component should be stored.',
        );
        $this->addOption(
            'path',
            '',
            InputOption::VALUE_OPTIONAL,
            'Custom path where the Component should be stored.',
            'Resources/Private/Components/ui/',
        );
        $this->addOption(
            'fluid-suffix',
            '',
            InputOption::VALUE_NEGATABLE,
            'Use .fluid.html suffix for component files. Defaults to enabled on TYPO3 v14+.',
        );
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overwriting existing component.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $componentKey = $input->getArgument('component');

        $availablePackages = $this->packageResolver->getAvailablePackages();
        if ($availablePackages === []) {
            throw new \RuntimeException('No packages were found in which to store the Component.', 1766947893);
        }

        $extension = $this->resolveTargetExtension($input, $io, $availablePackages);

        [$error, $manifest] = $this->registryService->fetchComponent($componentKey);
        if ($error) {
            $io->error($error['message']);
            return Command::FAILURE;
        }

        $componentFolderName = $manifest['name'] ?? null;
        $files = $manifest['files'] ?? [];
        $useFluidSuffix = $input->getOption('fluid-suffix');
        if (!is_bool($useFluidSuffix)) {
            $useFluidSuffix = $this->shouldUseFluidSuffixByDefault();
        }

        $targetFolder =
            $availablePackages[$extension]->getPackagePath() . $input->getOption('path') . $componentFolderName . '/';

        $writeResult = $this->writeComponentFiles($io, $componentKey, $files, [
            'targetFolder' => $targetFolder,
            'useFluidSuffix' => $useFluidSuffix,
            'force' => (bool)$input->getOption('force'),
        ]);

        $this->reportResult($io, $componentKey, $extension, $writeResult);

        return Command::SUCCESS;
    }

    /**
     * Resolves which package the component should be written into: the explicit `--extension` option,
     * the previously-saved default, or an interactive prompt (optionally saving the choice as the new
     * default).
     */
    private function resolveTargetExtension(InputInterface $input, SymfonyStyle $io, array $availablePackages): string
    {
        $extension = $input->getOption('extension');
        if ($extension) {
            if (!array_key_exists($extension, $availablePackages)) {
                throw new \RuntimeException(
                    'The extension "' . $extension . '" could not be found. Please choose one of these extensions: '
                        . implode(', ', $this->getPackageKeys($availablePackages)),
                    1678781015,
                );
            }
            return $extension;
        }

        $defaultExtension =
            $this->extensionConfiguration->get('fluid_primitives', 'cli')['add']['defaultExtension'] ?? '';
        if ($defaultExtension !== '' && array_key_exists($defaultExtension, $availablePackages)) {
            return $defaultExtension;
        }

        $availablePackagesForDisplay = $this->packageResolver->getAvailablePackagesForDisplay();
        if ($availablePackagesForDisplay === []) {
            $io->writeln(
                '<comment>No local extensions found. Displaying all installed extensions instead.</comment>',
            );
            $io->writeln('<comment>Maybe you forgot to install a site package?</comment>');
            $availablePackagesForDisplay = $availablePackages;
        }
        $availablePackageTitles = $this->getPackageTitles($availablePackagesForDisplay);
        $extension = $io->askQuestion(new ChoiceQuestion(
            'Choose an extension in which the Component should be stored',
            $availablePackageTitles,
        ));
        if ($extension === null) {
            throw new MissingInputException('Aborted.', 1766948173);
        }

        if ($io->confirm('Do you want to set "' . $extension . '" as the default extension for new components?')) {
            $settings = $this->extensionConfiguration->get('fluid_primitives');
            if (!is_array($settings)) {
                $settings = [];
            }
            $settings['cli']['add']['defaultExtension'] = $extension;
            $this->extensionConfiguration->set('fluid_primitives', $settings);

            $io->success(sprintf('Default extension "%s" saved.', $extension));
        }

        return $extension;
    }

    /**
     * Fetches and writes each component file to disk, skipping existing files unless `force` is set.
     *
     * @param string[] $files
     * @param array{targetFolder: string, useFluidSuffix: bool, force: bool} $options
     * @return array{skipped: bool, updated: bool, created: bool}
     */
    private function writeComponentFiles(SymfonyStyle $io, string $componentKey, array $files, array $options): array
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
    private function reportResult(SymfonyStyle $io, string $componentKey, string $extension, array $writeResult): void
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

    private function getPackageTitles(array $availablePackages): array
    {
        return array_map(static fn(PackageInterface $package): string => $package
            ->getPackageMetaData()
            ->getTitle(), $availablePackages);
    }

    protected function getPackageKeys(array $availablePackages): array
    {
        return array_map(static fn(PackageInterface $package): string => $package->getPackageKey(), $availablePackages);
    }

    private function shouldUseFluidSuffixByDefault(): bool
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
