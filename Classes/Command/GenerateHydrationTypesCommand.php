<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
use Jramke\FluidPrimitives\Domain\Dto\RootComponentLocation;
use Jramke\FluidPrimitives\Utility\ComponentEnumerator;
use Jramke\FluidPrimitives\Utility\Typed;
use Spatie\TypeScriptTransformer\Data\WriteableFile;
use Spatie\TypeScriptTransformer\Support\Loggers\SymfonyConsoleLogger;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
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
 * "Typesafe client-side hydration props" design doc for the full rationale. Also (re)generates the
 * one shared file every `#[TypeScript]`-attributed class under `Classes/Domain/Dto/` transforms
 * into, since a component's own `collection`-style props can reference one of those.
 *
 * Drives spatie/typescript-transformer's own pipeline (`TypeScriptTransformer::resolveState()`) but
 * deliberately stops short of its `WriteFilesAction`/manifest: `--output` must be able to point
 * anywhere on disk for a third-party collection, not just somewhere under one common
 * `outputDirectory` root that `WriteFilesAction` would concatenate its own `WriteableFile::$path`
 * onto, so this class writes files itself - same as before - reusing only the *resolved* content
 * spatie's own `resolveFilesAction` produces. `withoutManifest()` is still set on the config for
 * correctness (a future caller of `TypeScriptTransformer` proper would want it), even though this
 * class's own bypass already means no manifest is ever written regardless.
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

        $config = $this->buildConfig($collection, $locations, $outputDir);
        $transformer = TypeScriptTransformer::create($config, new SymfonyConsoleLogger($io));

        try {
            [$transformedCollection] = $transformer->resolveState();
            $writeableFiles = $transformer->resolveFilesAction->execute($transformedCollection);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        return $check
            ? $this->reportCheckResult($io, $this->driftedFiles($writeableFiles))
            : $this->writeFiles($io, $writeableFiles, $locations, $outputDir);
    }

    /**
     * @param list<RootComponentLocation> $locations
     */
    private function buildConfig(
        ComponentCollectionInterface $collection,
        array $locations,
        ?string $outputDir,
    ): TypeScriptTransformerConfig {
        // realpath(), not just dirname(__DIR__, 2): TYPO3's own extension path resolution hands
        // ComponentEnumerator a vendor/<package>/... path that may itself be a symlink (composer
        // path repositories, this monorepo's own setup among them), while __DIR__ here may or may
        // not already be realpath()'d depending on how the classloader resolved it - cross-file
        // relative imports below need both sides to agree on one canonical filesystem path, not a
        // mix of a symlink path and its real target.
        $packageRoot = (string)realpath(dirname(__DIR__, levels: 2));

        return TypeScriptTransformerConfigFactory::create()
            ->outputDirectory($packageRoot)
            ->transformDirectories($packageRoot . '/Classes/Domain/Dto')
            ->transformer(AttributedClassTransformer::class, EnumTransformer::class)
            ->provider(new HydrationTransformedProvider($collection, $locations, $outputDir, $this->propsCollector))
            ->writer(new HydrationTypeScriptWriter($packageRoot . '/Resources/Private/Client/src/types.generated.ts'))
            ->withoutManifest()
            ->get();
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
     * @param array<WriteableFile> $writeableFiles
     * @return list<string> Absolute paths whose generated content no longer matches what's committed.
     */
    private function driftedFiles(array $writeableFiles): array
    {
        $drifted = [];

        foreach ($writeableFiles as $file) {
            if (!(!is_file($file->path) || file_get_contents($file->path) !== $file->contents)) {
                continue;
            }

            $drifted[] = $file->path;
        }

        return $drifted;
    }

    /**
     * @param array<WriteableFile> $writeableFiles
     * @param list<RootComponentLocation> $locations
     */
    private function writeFiles(SymfonyStyle $io, array $writeableFiles, array $locations, ?string $outputDir): int
    {
        foreach ($writeableFiles as $file) {
            $directory = dirname($file->path);
            if (!is_dir($directory)) {
                mkdir($directory, recursive: true);
            }

            file_put_contents($file->path, $file->contents);
            $io->writeln(sprintf('Generated: %s', $file->path));
        }

        foreach ($locations as $location) {
            $classFile = $location->path . '/' . $location->name . '.ts';
            if ($outputDir === null && is_file($classFile)) {
                $this->fileWriter->ensureReExport($classFile, $location->name);
            }
        }

        return Command::SUCCESS;
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
