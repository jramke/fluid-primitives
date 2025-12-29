<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use GuzzleHttp\Client;
use Jramke\FluidPrimitives\Service\PackageResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageInterface;

#[AsCommand(
    name: 'ui:add',
    description: 'Add a new component from Fluid Primitives',
)]
class ComponentAddCommand extends Command
{
    public function __construct(
        protected readonly PackageResolver $packageResolver,
        protected readonly CacheManager $cacheManager,
        protected readonly ExtensionConfiguration $extensionConfiguration
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add a new component from Fluid Primitives');
        $this->addArgument('component', InputArgument::REQUIRED, 'The name of the component to add (e.g., accordion or scroll-area)');
        $this->addOption(
            'extension',
            '',
            InputOption::VALUE_OPTIONAL,
            'Host extension in which the Component should be stored.'
        );
        $this->addOption(
            'path',
            '',
            InputOption::VALUE_OPTIONAL,
            'Custom path where the Component should be stored.',
            'Resources/Private/Components/ui/'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force overwriting existing component.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $componentKey = $input->getArgument('component');

        $availablePackages = $this->packageResolver->getAvailablePackages();
        if ($availablePackages === []) {
            throw new \RuntimeException('No packages were found in which to store the Component.', 1766947893);
        }

        if ($input->getOption('extension')) {
            $extension = $input->getOption('extension');
            if (!array_key_exists($extension, $availablePackages)) {
                throw new \RuntimeException(
                    'The extension "' . $extension . '" could not be found. Please choose one of these extensions: ' . implode(', ', $this->getPackageKeys($availablePackages)),
                    1678781015
                );
            }
        } else {
            $defaultExtension = $this->extensionConfiguration->get(
                'fluid_primitives',
                'cli',
            )['add']['defaultExtension'] ?? '';

            if (!empty($defaultExtension) && array_key_exists($defaultExtension, $availablePackages)) {
                $extension = $defaultExtension;
            } else {
                $availablePackagesForDisplay = $this->packageResolver->getAvailablePackagesForDisplay();
                if ($availablePackagesForDisplay === []) {
                    $io->writeln('<comment>No local extensions found. Displaying all installed extensions instead.</comment>');
                    $io->writeln('<comment>Maybe you forgot to install a site package?</comment>');
                    $availablePackagesForDisplay = $availablePackages;
                }
                $availablePackageTitles = $this->getPackageTitles($availablePackagesForDisplay);
                $extension = $io->askQuestion(new ChoiceQuestion('Choose an extension in which the Component should be stored', $availablePackageTitles));
                if ($extension === null) {
                    throw new MissingInputException('Aborted.', 1766948173);
                }

                if ($io->confirm('Do you want to set "' . $extension . '" as the default extension for new components?')) {
                    $settings = $this->extensionConfiguration->get('fluid_primitives');
                    if (!is_array($settings)) {
                        $settings = [];
                    }
                    $settings['cli']['add']['defaultExtension'] = $extension;
                    $this->extensionConfiguration->set(
                        'fluid_primitives',
                        $settings
                    );

                    $io->success(sprintf('Default extension "%s" saved.', $extension));
                }
            }
        }

        $client = new Client([
            'base_uri' => Environment::getContext()->isDevelopment()
                ? 'https://fluid-primitives.ddev.site/'
                : 'https://fluid-primitives.com/',
        ]);

        try {
            $response = $client->get("/registry/components/{$componentKey}");
            $manifest = json_decode((string)$response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $io->error('Component not found in registry.');
            return Command::FAILURE;
        }

        $componentFolderName = $manifest['name'] ?? null;
        $files = $manifest['files'] ?? [];

        if (empty($files) || $componentFolderName === null) {
            $io->error('Invalid component manifest received from registry.');
            return Command::FAILURE;
        }

        $targetFolder = $availablePackages[$extension]->getPackagePath() . $input->getOption('path') . $componentFolderName . '/';

        $someSkipped = false;
        $someUpdated = false;
        $someCreated = false;

        foreach ($files as $file) {
            $res = $client->get("/registry/components/{$componentKey}/files/{$file}");
            if ($res->getStatusCode() !== 200) {
                $io->warning("Failed to fetch file {$file}");
                continue;
            }

            $content = (string)$res->getBody();

            $targetFilePath = $targetFolder . $file;

            @mkdir(dirname($targetFilePath), 0777, true);

            if (file_exists($targetFilePath)) {
                if (!$input->getOption('force')) {
                    $io->writeln('Skipped: ' . $file);
                    $someSkipped = true;
                    continue;
                }

                file_put_contents($targetFilePath, $content);
                $io->writeln('Updated: ' . $file);
                $someUpdated = true;
                continue;
            }

            file_put_contents($targetFilePath, $content);
            $io->writeln('Created: ' . $file);
            $someCreated = true;
        }

        if ($someSkipped && !$someCreated && !$someUpdated) {
            $io->warning([
                'Component "' . $componentKey . '" already exists in extension "' . $extension . '".',
                'No files were changed.',
                'Use the --force option to overwrite existing files.',
            ]);
        } else {
            if ($someSkipped) {
                $io->writeln(
                    '<comment>Some files were skipped. Use the --force option to overwrite existing files.</comment>'
                );
            }

            if ($someUpdated) {
                $io->success(
                    'Component "' . $componentKey . '" updated in extension "' . $extension . '".'
                );
            } elseif ($someCreated) {
                $io->success(
                    'Component "' . $componentKey . '" added to extension "' . $extension . '".'
                );
            }

            $this->cacheManager->flushCachesInGroup('pages');
        }


        return Command::SUCCESS;
    }

    private function getPackageTitles(array $availablePackages): array
    {
        return array_map(fn(PackageInterface $package): string => $package->getPackageMetaData()->getTitle(), $availablePackages);
    }

    protected function getPackageKeys(array $availablePackages): array
    {
        return array_map(fn(PackageInterface $package): string => $package->getPackageKey(), $availablePackages);
    }
}
