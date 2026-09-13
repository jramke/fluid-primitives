<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Command\ComponentAddCommand;
use Jramke\FluidPrimitives\Service\PackageResolver;
use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Package\MetaData;
use TYPO3\CMS\Core\Package\PackageInterface;

#[AllowMockObjectsWithoutExpectations]
final class ComponentAddCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/fluid-primitives-command-test-' . uniqid() . '/';
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createCommandTester(RegistryService $registryService, CacheManager $cacheManager): CommandTester
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getPackagePath')->willReturn($this->tempDir);
        $package->method('getPackageKey')->willReturn('my_ext');
        $metaData = $this->createMock(MetaData::class);
        $metaData->method('getTitle')->willReturn('My Extension');
        $package->method('getPackageMetaData')->willReturn($metaData);

        $packageResolver = $this->createMock(PackageResolver::class);
        $packageResolver->method('getAvailablePackages')->willReturn(['my_ext' => $package]);
        $packageResolver->method('getAvailablePackagesForDisplay')->willReturn(['my_ext' => $package]);

        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([]);

        $command = new ComponentAddCommand($packageResolver, $cacheManager, $extensionConfiguration, $registryService);

        return new CommandTester($command);
    }

    private function createRegistryService(array $files, string $fileContent = '<div>content</div>'): RegistryService
    {
        $registryService = $this->createMock(RegistryService::class);
        $registryService
            ->method('fetchComponent')
            ->willReturn([null, ['name' => 'my-component', 'files' => $files]]);
        $registryService->method('fetchComponentFile')->willReturn([null, $fileContent]);

        return $registryService;
    }

    #[Test]
    public function writesNewComponentFilesToTheTargetExtension(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->once())->method('flushCachesInGroup')->with('pages');

        $tester = $this->createCommandTester($this->createRegistryService(['Root.html']), $cacheManager);
        $tester->execute([
            'component' => 'accordion',
            '--extension' => 'my_ext',
            '--path' => '',
            '--fluid-suffix' => false,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('added', $tester->getDisplay());

        $writtenFile = $this->tempDir . 'my-component/Root.html';
        $this->assertFileExists($writtenFile);
        $this->assertSame('<div>content</div>', file_get_contents($writtenFile));
    }

    #[Test]
    public function throwsWhenTheGivenExtensionDoesNotExist(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $tester = $this->createCommandTester($this->createRegistryService(['Root.html']), $cacheManager);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be found');

        $tester->execute(['component' => 'accordion', '--extension' => 'unknown_ext']);
    }

    #[Test]
    public function skipsExistingFilesWithoutForce(): void
    {
        mkdir($this->tempDir . 'my-component', 0o777, true);
        file_put_contents($this->tempDir . 'my-component/Root.html', 'original content');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->never())->method('flushCachesInGroup');

        $tester = $this->createCommandTester($this->createRegistryService(['Root.html']), $cacheManager);
        $tester->execute([
            'component' => 'accordion',
            '--extension' => 'my_ext',
            '--path' => '',
            '--fluid-suffix' => false,
        ]);

        $this->assertStringContainsString('Skipped', $tester->getDisplay());
        $this->assertSame('original content', file_get_contents($this->tempDir . 'my-component/Root.html'));
    }

    #[Test]
    public function overwritesExistingFilesWithForce(): void
    {
        mkdir($this->tempDir . 'my-component', 0o777, true);
        file_put_contents($this->tempDir . 'my-component/Root.html', 'original content');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->once())->method('flushCachesInGroup')->with('pages');

        $tester = $this->createCommandTester($this->createRegistryService(['Root.html']), $cacheManager);
        $tester->execute([
            'component' => 'accordion',
            '--extension' => 'my_ext',
            '--path' => '',
            '--force' => true,
            '--fluid-suffix' => false,
        ]);

        $this->assertStringContainsString('updated', $tester->getDisplay());
        $this->assertSame('<div>content</div>', file_get_contents($this->tempDir . 'my-component/Root.html'));
    }
}
