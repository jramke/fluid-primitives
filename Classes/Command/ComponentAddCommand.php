<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\PackageResolver;
use Jramke\FluidPrimitives\Service\RegistryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

#[AsCommand(name: 'ui:add', description: 'Add a new component from Fluid Primitives')]
class ComponentAddCommand extends Command
{
    private readonly ComponentTargetExtensionResolver $extensionResolver;

    private readonly ComponentFileWriter $fileWriter;

    public function __construct(
        protected readonly PackageResolver $packageResolver,
        CacheManager $cacheManager,
        ExtensionConfiguration $extensionConfiguration,
        protected readonly RegistryService $registryService,
    ) {
        parent::__construct();
        $this->extensionResolver = new ComponentTargetExtensionResolver($packageResolver, $extensionConfiguration);
        $this->fileWriter = new ComponentFileWriter($registryService, $cacheManager);
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

        $extension = $this->extensionResolver->resolve($input, $io, $availablePackages);

        [$error, $manifest] = $this->registryService->fetchComponent($componentKey);
        if ($error) {
            $io->error($error['message']);
            return Command::FAILURE;
        }

        $componentFolderName = $manifest['name'] ?? null;
        $files = $manifest['files'] ?? [];
        $useFluidSuffix = $input->getOption('fluid-suffix');
        if (!is_bool($useFluidSuffix)) {
            $useFluidSuffix = $this->fileWriter->shouldUseFluidSuffixByDefault();
        }

        $targetFolder =
            $availablePackages[$extension]->getPackagePath() . $input->getOption('path') . $componentFolderName . '/';

        $writeResult = $this->fileWriter->write($io, $componentKey, $files, [
            'targetFolder' => $targetFolder,
            'useFluidSuffix' => $useFluidSuffix,
            'force' => (bool)$input->getOption('force'),
        ]);

        $this->fileWriter->reportResult($io, $componentKey, $extension, $writeResult);

        return Command::SUCCESS;
    }
}
