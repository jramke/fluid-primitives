<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Command\ComponentAddCommand;
use Jramke\FluidPrimitives\Command\ComponentFileWriter;
use Jramke\FluidPrimitives\Command\ComponentTargetExtensionResolver;
use Jramke\FluidPrimitives\Service\PackageResolver;
use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Package\MetaData;
use TYPO3\CMS\Core\Package\PackageInterface;

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

    private function createCommandTester(
        RegistryService $registryService,
        CacheManager $cacheManager,
        ?ExtensionConfiguration $extensionConfiguration = null,
    ): CommandTester {
        $package = $this->createStub(PackageInterface::class);
        $package->method('getPackagePath')->willReturn($this->tempDir);
        $package->method('getPackageKey')->willReturn('my_ext');
        $metaData = $this->createStub(MetaData::class);
        $metaData->method('getTitle')->willReturn('My Extension');
        $package->method('getPackageMetaData')->willReturn($metaData);

        $packageResolver = $this->createStub(PackageResolver::class);
        $packageResolver->method('getAvailablePackages')->willReturn(['my_ext' => $package]);
        $packageResolver->method('getAvailablePackagesForDisplay')->willReturn(['my_ext' => $package]);

        if (!$extensionConfiguration instanceof ExtensionConfiguration) {
            $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
            $extensionConfiguration->method('get')->willReturn([]);
        }

        $extensionResolver = new ComponentTargetExtensionResolver($packageResolver, $extensionConfiguration);
        $fileWriter = new ComponentFileWriter($registryService, $cacheManager);

        $command = new ComponentAddCommand($packageResolver, $registryService, $extensionResolver, $fileWriter);

        return new CommandTester($command);
    }

    private function createRegistryService(array $files, string $fileContent = '<div>content</div>'): RegistryService
    {
        $registryService = $this->createStub(RegistryService::class);
        $registryService->method('fetchComponent')->willReturn([null, ['name' => 'my-component', 'files' => $files]]);
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
        $cacheManager = $this->createStub(CacheManager::class);
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

    /**
     * @return iterable<string, array{0: string, 1: bool, 2: string}>
     */
    public static function fileNames(): iterable
    {
        yield 'html gets the fluid suffix' => ['Root.html', true, 'Root.fluid.html'];
        yield 'a fluid.html file keeps it' => ['Root.fluid.html', true, 'Root.fluid.html'];
        yield 'fluid.html loses the suffix without it' => ['Root.fluid.html', false, 'Root.html'];
        yield 'html stays html without the suffix' => ['Root.html', false, 'Root.html'];
        yield 'another extension passes through' => ['src/root.ts', true, 'src/root.ts'];
    }

    #[Test]
    #[DataProvider('fileNames')]
    public function namesTheTemplatesAfterTheSuffixOption(string $file, bool $fluidSuffix, string $expected): void
    {
        $tester = $this->createCommandTester(
            $this->createRegistryService([$file]),
            $this->createStub(CacheManager::class),
        );
        $tester->execute([
            'component' => 'accordion',
            '--extension' => 'my_ext',
            '--path' => '',
            $fluidSuffix ? '--fluid-suffix' : '--no-fluid-suffix' => true,
        ]);

        $this->assertSame([$expected], $this->writtenFiles());
    }

    #[Test]
    public function usesTheFluidSuffixUnlessToldOtherwise(): void
    {
        $tester = $this->createCommandTester(
            $this->createRegistryService(['Root.html']),
            $this->createStub(CacheManager::class),
        );
        $tester->execute(['component' => 'accordion', '--extension' => 'my_ext', '--path' => '']);

        $this->assertSame(['Root.fluid.html'], $this->writtenFiles());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function fileNamesOutsideTheComponentFolder(): iterable
    {
        yield 'a parent folder' => ['../escape.html'];
        yield 'a parent folder further down' => ['sub/../../escape.html'];
        yield 'an absolute path' => ['/escape.html'];
        yield 'a backslash' => ['..\\escape.html'];
    }

    #[Test]
    #[DataProvider('fileNamesOutsideTheComponentFolder')]
    public function neverFetchesOrWritesAFileNamedOutsideTheComponentFolder(string $badName): void
    {
        $registryService = $this->createMock(RegistryService::class);
        $registryService
            ->method('fetchComponent')
            ->willReturn([null, ['name' => 'my-component', 'files' => ['Root.html', $badName]]]);
        // the good file is the positive control: the run works, only the bad name is refused
        $registryService
            ->expects($this->once())
            ->method('fetchComponentFile')
            ->with('accordion', 'Root.html')
            ->willReturn([null, '<div>content</div>']);
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->once())->method('flushCachesInGroup')->with('pages');

        $tester = $this->createCommandTester($registryService, $cacheManager);
        $tester->execute(['component' => 'accordion', '--extension' => 'my_ext', '--path' => '']);

        $this->assertSame(1, $tester->getStatusCode());
        // the console wraps the message at the width of the terminal
        $this->assertStringContainsString(
            'not a path inside the component folder',
            (string)preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
        $this->assertSame(['Root.fluid.html'], $this->writtenFiles());
        $this->assertFileDoesNotExist($this->tempDir . 'escape.html');
        $this->assertFileDoesNotExist('/escape.html');
    }

    #[Test]
    public function refusesAFolderNameThatLeavesTheTargetFolder(): void
    {
        $registryService = $this->createMock(RegistryService::class);
        $registryService->method('fetchComponent')->willReturn([null, ['name' => '../evil', 'files' => ['Root.html']]]);
        $registryService->expects($this->never())->method('fetchComponentFile');

        $tester = $this->createCommandTester($registryService, $this->createStub(CacheManager::class));
        $tester->execute(['component' => 'accordion', '--extension' => 'my_ext', '--path' => 'components/']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame([], $this->writtenFiles());
        $this->assertDirectoryDoesNotExist($this->tempDir . 'evil');
    }

    #[Test]
    public function failsWithoutFlushingTheCacheWhenNoFileCouldBeDownloaded(): void
    {
        $registryService = $this->createStub(RegistryService::class);
        $registryService
            ->method('fetchComponent')
            ->willReturn([null, ['name' => 'my-component', 'files' => ['Root.html', 'Item.html']]]);
        $registryService
            ->method('fetchComponentFile')
            ->willReturn([['message' => 'Failed to fetch component file from registry.', 'details' => null], null]);
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->never())->method('flushCachesInGroup');

        $tester = $this->createCommandTester($registryService, $cacheManager);
        $tester->execute(['component' => 'accordion', '--extension' => 'my_ext', '--path' => '']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to fetch component file', $tester->getDisplay());
        $this->assertStringNotContainsString('added', $tester->getDisplay());
        $this->assertSame([], $this->writtenFiles());
    }

    #[Test]
    public function asksForTheExtensionAndKeepsTheAnswerAsTheDefault(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([]);
        $extensionConfiguration
            ->expects($this->once())
            ->method('set')
            ->with('fluid_primitives', ['cli' => ['add' => ['defaultExtension' => 'my_ext']]]);

        $tester = $this->createCommandTester(
            $this->createRegistryService(['Root.html']),
            $this->createStub(CacheManager::class),
            $extensionConfiguration,
        );
        $tester->setInputs(['my_ext', 'yes']);
        $tester->execute(['component' => 'accordion', '--path' => ''], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Default extension "my_ext" saved.', $tester->getDisplay());
        $this->assertSame(['Root.fluid.html'], $this->writtenFiles());
    }

    #[Test]
    public function usesTheSavedDefaultExtensionWithoutAsking(): void
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn(['add' => ['defaultExtension' => 'my_ext']]);

        $tester = $this->createCommandTester(
            $this->createRegistryService(['Root.html']),
            $this->createStub(CacheManager::class),
            $extensionConfiguration,
        );
        // no input is given: a prompt would abort the run
        $tester->execute(['component' => 'accordion', '--path' => ''], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame(['Root.fluid.html'], $this->writtenFiles());
    }

    /**
     * The files below the component folder, relative to it.
     *
     * @return list<string>
     */
    private function writtenFiles(): array
    {
        $folder = $this->tempDir . 'my-component';
        if (!is_dir($folder)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $folder,
            \FilesystemIterator::SKIP_DOTS,
        )) as $file) {
            $files[] = substr((string)$file, strlen($folder) + 1);
        }
        sort($files);

        return $files;
    }
}
