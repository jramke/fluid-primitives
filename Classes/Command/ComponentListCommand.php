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
    name: 'ui:list',
    description: 'List available components from Fluid Primitives',
)]
class ComponentListCommand extends Command
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
        $this->setDescription('List available components from Fluid Primitives');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $client = new Client([
            'base_uri' => Environment::getContext()->isDevelopment()
                ? 'https://fluid-primitives.ddev.site/'
                : 'https://fluid-primitives.com/',
        ]);

        try {
            $response = $client->get("/registry/components");
            $components = json_decode((string)$response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $io->error('Failed to fetch component registry: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->title('Available Components for installation');
        $tableRows = [];
        foreach ($components as $component) {
            $tableRows[] = [
                $component['key'],
                $component['name'],
                $component['description'] ?? '',
            ];
        }
        $io->table(['Key', 'Name', 'Description'], $tableRows);

        return Command::SUCCESS;
    }
}
