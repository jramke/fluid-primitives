<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Jramke\FluidPrimitives\Service;

use Composer\InstalledVersions;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * Resolves packages using the TYPO3 PackageManager
 *
 * @copyright this class is copied from https://github.com/FriendsOfTYPO3/content-blocks/blob/549312c03ec78f852b4b2b267b4ede2985302b31/Classes/Service/PackageResolver.php
 */
readonly class PackageResolver
{
    public function __construct(
        protected PackageManager $packageManager,
    ) {}

    /**
     * @return array<string, PackageInterface>
     */
    public function getAvailablePackages(): array
    {
        /** @var array<string, PackageInterface> $packages */
        $packages = $this->packageManager->getAvailablePackages();
        return $this->removeFrameworkExtensions($packages);
    }

    /**
     * @return array<string, PackageInterface>
     */
    public function getAvailablePackagesForDisplay(): array
    {
        /** @var array<string, PackageInterface> $packages */
        $packages = $this->packageManager->getAvailablePackages();
        $packages = $this->removeFrameworkExtensions($packages);
        if (Environment::isComposerMode()) {
            return $this->filterNonLocalComposerPackages($packages);
        }
        return $packages;
    }

    /**
     * The `Classes/` folder of every locally-authored package in the current project (the same set
     * {@see getAvailablePackagesForDisplay()} resolves - a real path-repository package, not a
     * vendor/Composer-installed one, TYPO3 system extensions excluded either way).
     *
     * @return list<string>
     */
    public function getLocalClassesDirectories(): array
    {
        $directories = [];

        foreach ($this->getAvailablePackagesForDisplay() as $package) {
            $classesDirectory = rtrim($package->getPackagePath(), '/') . '/Classes';
            if (is_dir($classesDirectory)) {
                $directories[] = $classesDirectory;
            }
        }

        return $directories;
    }

    public function getComposerProjectVendor(): string
    {
        if (!Environment::isComposerMode()) {
            return '';
        }
        $rootPackageName = $this->getRootPackageName();
        $parts = explode('/', $rootPackageName);
        return $parts[0];
    }

    /**
     * @param array<string, PackageInterface> $packages
     * @return array<string, PackageInterface>
     */
    protected function removeFrameworkExtensions(array $packages): array
    {
        return array_filter(
            $packages,
            static fn(PackageInterface $package): bool => !$package->getPackageMetaData()->isFrameworkType(),
        );
    }

    /**
     * @param array<string, PackageInterface> $packages
     * @return array<string, PackageInterface>
     */
    protected function filterNonLocalComposerPackages(array $packages): array
    {
        $composerLockPath = Environment::getProjectPath() . '/composer.lock';
        if (!file_exists($composerLockPath)) {
            return $packages;
        }
        $composerLockContents = file_get_contents($composerLockPath);
        if ($composerLockContents === false) {
            return $packages;
        }
        $composerLock = Typed::arrayOrNull(json_decode($composerLockContents, associative: true)) ?? [];
        $composerLockPackages = array_merge(
            Typed::arrayOrNull($composerLock['packages'] ?? null) ?? [],
            Typed::arrayOrNull($composerLock['packages-dev'] ?? null) ?? [],
        );
        $composerLockMap = [];
        foreach (array_map(Typed::arrayOrNull(...), $composerLockPackages) as $package) {
            if ($package === null) {
                continue;
            }
            $name = Typed::stringOrNull($package['name'] ?? null);
            if ($name === null) {
                continue;
            }
            $composerLockMap[$name] = Typed::stringOrNull($package['dist']['type'] ?? null);
        }
        $filterPackages = function (PackageInterface $package) use ($composerLockMap): bool {
            $name = Typed::stringOrNull($package->getValueFromComposerManifest('name'));
            if ($name !== null && array_key_exists($name, $composerLockMap)) {
                return $composerLockMap[$name] === 'path';
            }
            return $name === $this->getRootPackageName();
        };
        return array_filter($packages, $filterPackages);
    }

    protected function getRootPackageName(): string
    {
        return InstalledVersions::getRootPackage()['name'];
    }
}
