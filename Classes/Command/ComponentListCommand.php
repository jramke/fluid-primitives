<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\RegistryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ui:list',
    description: 'List available components from Fluid Primitives',
)]
class ComponentListCommand extends Command
{
    public function __construct(
        protected readonly RegistryService $registryService,
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

        [$error, $components] = $this->registryService->fetchComponentList();
        if ($error) {
            $io->error($error['message']);
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
