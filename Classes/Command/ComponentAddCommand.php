<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

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
        private readonly ComponentFileWriter $fileWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add a new component from Fluid Primitives');
        $this->addArgument(
            'component',
            InputArgument::REQUIRED,
            'The name of the component to add (e.g., accordion or scrollArea)',
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
            'Use .fluid.html suffix for component files.',
            true,
        );
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overwriting existing component.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $componentKey = Typed::string($input->getArgument('component'));

        $availablePackages = $this->packageResolver->getAvailablePackages();
        if ($availablePackages === []) {
            throw new \RuntimeException('No packages were found in which to store the Component.', 1766947893);
        }

        $extension = $this->extensionResolver->resolve($input, $io, $availablePackages);

        [$error, $manifest] = $this->registryService->fetchComponent($componentKey);
        if ($error !== null) {
            $io->error($error['message']);
            return Command::FAILURE;
        }

        $componentFolderName = Typed::string($manifest['name'] ?? null);
        if (!$this->fileWriter->isSafeRelativePath($componentFolderName) || str_contains($componentFolderName, '/')) {
            $io->error('The registry sent "' . $componentFolderName . '" as the name of the component folder.');
            return Command::FAILURE;
        }

        $files = array_map(Typed::string(...), Typed::arrayOrNull($manifest['files'] ?? null) ?? []);
        $path = rtrim(Typed::string($input->getOption('path')), characters: '/');
        $path = $path === '' ? '' : $path . '/';
        $targetFolder = $availablePackages[$extension]->getPackagePath() . $path . $componentFolderName . '/';

        $useFluidSuffix = $input->getOption('fluid-suffix') !== false;

        $existing = $this->fileWriter->existingTargets($files, $targetFolder, $useFluidSuffix);
        if ($existing !== [] && !Typed::bool($input->getOption('force'))) {
            $io->error([
                'Component "' . $componentKey . '" already exists in extension "' . $extension . '":',
                ...array_map(static fn(string $file): string => '- ' . $file, $existing),
                'Nothing was written. Use --force to overwrite the whole component.',
            ]);
            return Command::FAILURE;
        }

        $writeResult = $this->fileWriter->write($io, $componentKey, $files, [
            'targetFolder' => $targetFolder,
            'useFluidSuffix' => $useFluidSuffix,
            'componentPath' => 'EXT:' . $extension . '/' . $path . $componentFolderName,
        ]);

        $this->fileWriter->reportResult($io, $componentKey, $extension, $writeResult);
        // @mago-expect lint:no-global
        // @mago-expect analysis:mixed-assignment
        $viteNamespace = $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['vite'] ?? null;
        if ($writeResult['usesViteAsset'] && $viteNamespace === null) {
            $io->warning(
                'The templates contain <vite:asset> to load the entry file, but the "vite" Fluid namespace is not ' .
                'registered. Install praetorius/vite-asset-collector and register the namespace, or remove the line.',
            );
        }
        if ($writeResult['failed']) {
            $io->error('Not every file of the component could be written.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
