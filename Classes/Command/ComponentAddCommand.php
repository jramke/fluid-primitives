<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Exception\ConfigurationException;
use Jramke\FluidPrimitives\Service\PackageResolver;
use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ui:add', description: 'Add a new component from Fluid Primitives')]
class ComponentAddCommand extends Command
{
    public function __construct(
        protected readonly PackageResolver $packageResolver,
        protected readonly RegistryService $registryService,
        private readonly ComponentTargetExtensionResolver $extensionResolver,
        private readonly ComponentKeyResolver $componentKeyResolver,
        private readonly ComponentFileWriter $fileWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add a new component from Fluid Primitives');
        $this->addArgument(
            'components',
            InputArgument::IS_ARRAY,
            'The names of the components to add (e.g., accordion or scrollArea), separated by spaces',
        );
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Add every component of the registry.');
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
            'Use .fluid.html suffix for component files.',
            true,
        );
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overwriting existing component.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $componentKeys = $this->componentKeyResolver->resolve($input, $io);
        if ($componentKeys === null) {
            return Command::FAILURE;
        }

        $availablePackages = $this->packageResolver->getAvailablePackages();
        if ($availablePackages === []) {
            throw new ConfigurationException('No packages were found in which to store the Component.', 1766947893);
        }

        $extension = $this->extensionResolver->resolve($input, $io, $availablePackages);
        $path = rtrim(Typed::string($input->getOption('path')), characters: '/');
        $path = $path === '' ? '' : $path . '/';
        $useFluidSuffix = $input->getOption('fluid-suffix') !== false;

        $planned = $this->planComponents(
            $io,
            $componentKeys,
            $availablePackages[$extension]->getPackagePath() . $path,
            'EXT:' . $extension . '/' . $path,
            $useFluidSuffix,
        );
        if ($planned === null) {
            return Command::FAILURE;
        }
        [$components, $existing] = $planned;

        if ($existing !== [] && !Typed::bool($input->getOption('force'))) {
            $io->error([
                'These files already exist in extension "' . $extension . '":',
                ...$existing,
                'Nothing was written. Use --force to overwrite the whole components.',
            ]);
            return Command::FAILURE;
        }

        $results = [];
        foreach ($components as $componentKey => $component) {
            $writeResult = $this->fileWriter->write($io, $componentKey, $component['files'], [
                'targetFolder' => $component['targetFolder'],
                'useFluidSuffix' => $useFluidSuffix,
                'componentPath' => $component['componentPath'],
            ]);
            $this->fileWriter->reportResult($io, $componentKey, $extension, $writeResult);
            $results[] = $writeResult;
        }
        $any = static fn(string $flag): bool => in_array(true, array_column($results, $flag), strict: true);
        if ($any('created') || $any('updated')) {
            $this->fileWriter->flushPageCache();
        }

        // @mago-expect lint:no-global
        // @mago-expect analysis:mixed-assignment
        $viteNamespace = $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['vite'] ?? null;
        if ($any('usesViteAsset') && $viteNamespace === null) {
            $io->warning(
                'The templates contain <vite:asset> to load the entry file, but the "vite" Fluid namespace is not ' .
                'registered. Install praetorius/vite-asset-collector and register the namespace, or remove the line.',
            );
        }
        if ($any('failed')) {
            $io->error('Not every file of the components could be written.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Fetches the manifest of every component and collects the target files that already exist, before the first
     * file is written, so a failed check writes nothing. `null` after an error was reported.
     *
     * @param list<string> $componentKeys
     * @param string $targetPath Where the component folders go on disk.
     * @param string $componentPathPrefix The same as an `EXT:` path, for `%componentPath%`.
     * @return array{0: array<string, array{files: string[], targetFolder: string, componentPath: string}>, 1: list<string>}|null
     */
    private function planComponents(
        SymfonyStyle $io,
        array $componentKeys,
        string $targetPath,
        string $componentPathPrefix,
        bool $useFluidSuffix,
    ): ?array {
        $components = [];
        $existing = [];
        foreach ($componentKeys as $componentKey) {
            [$error, $manifest] = $this->registryService->fetchComponent($componentKey);
            if ($error !== null) {
                $io->error($componentKey . ': ' . $error['message']);
                return null;
            }

            $folderName = Typed::string($manifest['name'] ?? null);
            if (!$this->fileWriter->isSafeRelativePath($folderName) || str_contains($folderName, '/')) {
                $io->error('The registry sent "' . $folderName . '" as the name of the component folder.');
                return null;
            }

            $files = array_map(Typed::string(...), Typed::arrayOrNull($manifest['files'] ?? null) ?? []);
            $targetFolder = $targetPath . $folderName . '/';
            foreach ($this->fileWriter->existingTargets($files, $targetFolder, $useFluidSuffix) as $file) {
                $existing[] = '- ' . $folderName . '/' . $file;
            }
            $components[$componentKey] = [
                'files' => $files,
                'targetFolder' => $targetFolder,
                'componentPath' => $componentPathPrefix . $folderName,
            ];
        }

        return [$components, $existing];
    }
}
