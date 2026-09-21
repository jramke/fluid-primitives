<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
use Jramke\FluidPrimitives\Domain\Dto\RootComponentLocation;
use Jramke\FluidPrimitives\Utility\ComponentEnumerator;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Generates one `<Name>.hydration.ts` per root component of `--collection` (default
 * {@see ComponentPrimitivesCollection}), giving `mountAll`/`mount` a real, specific `props` type
 * for that component instead of today's `{ id, ids, [key: string]: unknown }` bag - see the plan's
 * "Typesafe client-side hydration props" design doc for the full rationale.
 *
 * Safe to bootstrap outside a request: {@see ComponentEnumerator}/`getComponentDefinition()` only
 * parse Fluid templates, no HTTP/TSFE dependency, mirroring `GenerateZagDocsCommand`'s own
 * generate-then-commit convention in the docs package.
 */
#[AsCommand(
    name: 'ui:generate-hydration-types',
    description: 'Generate typesafe *.hydration.ts files for a component collection',
)]
class GenerateHydrationTypesCommand extends Command
{
    public function __construct(
        private readonly HydrationComponentPropsCollector $propsCollector,
        private readonly HydrationFileWriter $fileWriter,
        private readonly PrettierFormatter $prettierFormatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'collection',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Fully-qualified class name of the ComponentCollectionInterface to generate for.',
                default: ComponentPrimitivesCollection::class,
            )
            ->addOption(
                'output',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Directory to write every generated file into. Defaults to writing each ' .
                'one alongside its own primitive - only needed for a collection with no npm/tsdown ' .
                'pipeline of its own to re-export from (e.g. a third-party extension).',
            )
            ->addOption(
                'check',
                mode: InputOption::VALUE_NONE,
                description: 'Check for drift against the committed files instead of writing - exits non-zero if any file would change.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $collection = $this->resolveCollection($io, (string)$input->getOption('collection'));
        if ($collection === null) {
            return Command::FAILURE;
        }

        [$outputDir, $check] = $this->resolveOptions($input);

        $locations = ComponentEnumerator::enumerateRootComponents($collection);
        if ($locations === []) {
            $io->warning('No root components found for this collection.');
            return Command::SUCCESS;
        }

        try {
            $drifted = $check
                ? $this->checkLocations($collection, $locations, $outputDir)
                : $this->writeLocations($io, $collection, $locations, $outputDir);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        return $check ? $this->reportCheckResult($io, $drifted) : Command::SUCCESS;
    }

    /**
     * @param list<string> $drifted
     */
    private function reportCheckResult(SymfonyStyle $io, array $drifted): int
    {
        if ($drifted !== []) {
            $io->error('Hydration types are out of date for: ' . implode(', ', $drifted));
            return Command::FAILURE;
        }

        $io->success('Hydration types are up to date.');
        return Command::SUCCESS;
    }

    /**
     * @param list<RootComponentLocation> $locations
     * @return list<string> Always empty - only {@see checkLocations} ever reports drift, but both
     *   share `execute()`'s own `$drifted` assignment, so the shapes need to match.
     */
    private function writeLocations(
        SymfonyStyle $io,
        ComponentCollectionInterface $collection,
        array $locations,
        ?string $outputDir,
    ): array {
        foreach ($locations as $location) {
            $targetFile = $this->targetFile($location, $outputDir);
            // PrettierFormatter writes the formatted result to $targetFile itself as a side
            // effect (see its own docblock for why a real file, not stdin, is required) - this
            // *is* the write.
            $this->prettierFormatter->format(
                $this->generateForComponent($collection, $location),
                $targetFile,
                $location->path,
            );
            $io->writeln(sprintf('Generated: %s', $targetFile));

            $classFile = $location->path . '/' . $location->name . '.ts';
            if ($outputDir === null && is_file($classFile)) {
                $this->fileWriter->ensureReExport($classFile, $location->name);
            }
        }

        return [];
    }

    /**
     * @param list<RootComponentLocation> $locations
     * @return list<string> Target file paths whose generated content no longer matches what's committed.
     */
    private function checkLocations(
        ComponentCollectionInterface $collection,
        array $locations,
        ?string $outputDir,
    ): array {
        $drifted = [];

        foreach ($locations as $location) {
            $targetFile = $this->targetFile($location, $outputDir);
            // Formatted into a scratch sibling, never the real $targetFile itself - --check must
            // never mutate a committed file, only report whether it would change.
            $checkFile = $targetFile . '.check.ts';
            try {
                $content = $this->prettierFormatter->format(
                    $this->generateForComponent($collection, $location),
                    $checkFile,
                    $location->path,
                );
            } finally {
                if (is_file($checkFile)) {
                    unlink($checkFile);
                }
            }

            if (!is_file($targetFile) || file_get_contents($targetFile) !== $content) {
                $drifted[] = $targetFile;
            }
        }

        return $drifted;
    }

    private function generateForComponent(
        ComponentCollectionInterface $collection,
        RootComponentLocation $location,
    ): string {
        $collected = $this->propsCollector->collect($collection, $location);

        return $this->fileWriter->buildFileContent(
            $location->name,
            lcfirst($location->name),
            $collected['propDefinitions'],
            $collected['propsSource'],
        );
    }

    private function targetFile(RootComponentLocation $location, ?string $outputDir): string
    {
        return ($outputDir ?? $location->path) . '/' . $location->name . '.hydration.ts';
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function resolveOptions(InputInterface $input): array
    {
        $outputOption = Typed::stringOrNull($input->getOption('output'));
        $outputDir = $outputOption !== null ? rtrim($outputOption, characters: '/') : null;

        // A VALUE_NONE option's value is always already a genuine bool, same reasoning as
        // ComponentAddCommand's own --force/--fluid-suffix handling - Typed::bool() would also
        // (incorrectly, though harmlessly here) accept boolean-keyword strings.
        // @mago-expect analysis:mixed-assignment
        $checkOption = $input->getOption('check');
        $check = is_bool($checkOption) && $checkOption;

        return [$outputDir, $check];
    }

    private function resolveCollection(SymfonyStyle $io, string $collectionClass): ?ComponentCollectionInterface
    {
        if (!is_a($collectionClass, ComponentCollectionInterface::class, allow_string: true)) {
            $io->error(sprintf('"%s" does not implement ComponentCollectionInterface.', $collectionClass));
            return null;
        }

        // Dynamic class-name resolution from a CLI option - the container can't autowire "whichever
        // class this string names", same reasoning as ComponentContextFactory's own makeInstance() use.
        // is_a() above already narrows $collectionClass to a ComponentCollectionInterface class-string.
        return GeneralUtility::makeInstance($collectionClass);
    }
}
