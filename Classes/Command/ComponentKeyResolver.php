<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Resolves which components `ui:add` adds: the names it was given, or every component of the registry for `--all`.
 */
final readonly class ComponentKeyResolver
{
    public function __construct(
        private RegistryService $registryService,
    ) {}

    /**
     * The names given, or every component of the registry for `--all`. `null` after an error was reported.
     *
     * @return list<string>|null
     */
    public function resolve(InputInterface $input, SymfonyStyle $io): ?array
    {
        $componentKeys = array_values(array_unique(array_map(
            Typed::string(...),
            Typed::arrayOrNull($input->getArgument('components')) ?? [],
        )));
        $all = Typed::bool($input->getOption('all'));

        if ($all === ($componentKeys !== [])) {
            $io->error(
                $all
                    ? 'Pass the names of the components or --all, not both.'
                    : 'Pass the names of the components to add, or --all.',
            );
            return null;
        }
        if (!$all) {
            return $componentKeys;
        }

        [$error, $registry] = $this->registryService->fetchComponentList();
        if ($error !== null) {
            $io->error($error['message']);
            return null;
        }

        return array_values(array_map(static fn(mixed $component): string => Typed::string(
            Typed::arrayOrNull($component)['key'] ?? null,
        ), $registry));
    }
}
