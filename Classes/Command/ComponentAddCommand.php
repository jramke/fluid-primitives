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
        $files = array_map(Typed::string(...), Typed::arrayOrNull($manifest['files'] ?? null) ?? []);
        // Checked with is_bool() rather than Typed::bool() below - this is a VALUE_NONE flag, so a
        // real value here is always already a genuine bool; Typed::bool() would also (incorrectly for
        // this option) accept boolean-keyword strings.
        // @mago-expect analysis:mixed-assignment
        $useFluidSuffix = $input->getOption('fluid-suffix');
        if (!is_bool($useFluidSuffix)) {
            $useFluidSuffix = $this->fileWriter->shouldUseFluidSuffixByDefault();
        }

        $targetFolder =
            $availablePackages[$extension]->getPackagePath() .
            Typed::string($input->getOption('path')) .
            $componentFolderName .
            '/';

        $writeResult = $this->fileWriter->write($io, $componentKey, $files, [
            'targetFolder' => $targetFolder,
            'useFluidSuffix' => $useFluidSuffix,
            'force' => Typed::bool($input->getOption('force')),
        ]);

        $this->fileWriter->reportResult($io, $componentKey, $extension, $writeResult);

        return Command::SUCCESS;
    }
}
