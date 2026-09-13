<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Service\PackageResolver;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Package\PackageInterface;

/**
 * Resolves which package `ui:add` should write a component into: the explicit `--extension` option,
 * the previously-saved default, or an interactive prompt (optionally saving the choice as the new
 * default).
 */
final class ComponentTargetExtensionResolver
{
    public function __construct(
        private readonly PackageResolver $packageResolver,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @param array<string, PackageInterface> $availablePackages
     */
    public function resolve(InputInterface $input, SymfonyStyle $io, array $availablePackages): string
    {
        $extension = $input->getOption('extension');
        if ($extension) {
            if (!array_key_exists($extension, $availablePackages)) {
                throw new \RuntimeException(
                    'The extension "' . $extension . '" could not be found. Please choose one of these extensions: '
                        . implode(', ', $this->getPackageKeys($availablePackages)),
                    1678781015,
                );
            }
            return $extension;
        }

        $defaultExtension =
            $this->extensionConfiguration->get('fluid_primitives', 'cli')['add']['defaultExtension'] ?? '';
        if ($defaultExtension !== '' && array_key_exists($defaultExtension, $availablePackages)) {
            return $defaultExtension;
        }

        return $this->promptForExtension($io, $availablePackages);
    }

    /**
     * @param array<string, PackageInterface> $availablePackages
     */
    private function promptForExtension(SymfonyStyle $io, array $availablePackages): string
    {
        $availablePackagesForDisplay = $this->packageResolver->getAvailablePackagesForDisplay();
        if ($availablePackagesForDisplay === []) {
            $io->writeln(
                '<comment>No local extensions found. Displaying all installed extensions instead.</comment>',
            );
            $io->writeln('<comment>Maybe you forgot to install a site package?</comment>');
            $availablePackagesForDisplay = $availablePackages;
        }

        $extension = $io->askQuestion(new ChoiceQuestion(
            'Choose an extension in which the Component should be stored',
            $this->getPackageTitles($availablePackagesForDisplay),
        ));
        if ($extension === null) {
            throw new MissingInputException('Aborted.', 1766948173);
        }

        if ($io->confirm('Do you want to set "' . $extension . '" as the default extension for new components?')) {
            $this->saveAsDefaultExtension($extension);
            $io->success(sprintf('Default extension "%s" saved.', $extension));
        }

        return $extension;
    }

    private function saveAsDefaultExtension(string $extension): void
    {
        $settings = $this->extensionConfiguration->get('fluid_primitives');
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['cli']['add']['defaultExtension'] = $extension;
        $this->extensionConfiguration->set('fluid_primitives', $settings);
    }

    /**
     * @param array<string, PackageInterface> $availablePackages
     */
    private function getPackageTitles(array $availablePackages): array
    {
        return array_map(static fn(PackageInterface $package): string => $package
            ->getPackageMetaData()
            ->getTitle(), $availablePackages);
    }

    /**
     * @param array<string, PackageInterface> $availablePackages
     */
    private function getPackageKeys(array $availablePackages): array
    {
        return array_map(static fn(PackageInterface $package): string => $package->getPackageKey(), $availablePackages);
    }
}
