<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Command\ComponentAddCommand;
use Jramke\FluidPrimitives\Command\ComponentFileWriter;
use Jramke\FluidPrimitives\Command\ComponentKeyResolver;
use Jramke\FluidPrimitives\Command\ComponentTargetExtensionResolver;
use Jramke\FluidPrimitives\Exception\ConfigurationException;
use Jramke\FluidPrimitives\Service\PackageResolver;
use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
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

        $command = new ComponentAddCommand(
            $packageResolver,
            $registryService,
            $extensionResolver,
            new ComponentKeyResolver($registryService),
            $fileWriter,
        );

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
            'components' => ['accordion'],
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

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('could not be found');

        $tester->execute(['components' => ['accordion'], '--extension' => 'unknown_ext']);
    }

    #[Test]
    public function writesNothingAndFailsWhenAnyFileExistsWithoutForce(): void
    {
        mkdir($this->tempDir . 'my-component', 0o777, true);
        file_put_contents($this->tempDir . 'my-component/Root.html', 'original content');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->never())->method('flushCachesInGroup');

        $tester = $this->createCommandTester(
            $this->createRegistryService(['Root.html', 'Item.html', 'Root.entry.ts']),
            $cacheManager,
        );
        $tester->execute([
            'components' => ['accordion'],
            '--extension' => 'my_ext',
            '--path' => '',
            '--fluid-suffix' => false,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('- my-component/Root.html', $tester->getDisplay());
        $this->assertStringContainsString('Nothing was written', $tester->getDisplay());
        $this->assertSame('original content', file_get_contents($this->tempDir . 'my-component/Root.html'));
        $this->assertSame(['Root.html'], $this->writtenFiles());
    }

    #[Test]
    public function overwritesTheWholeComponentWithForce(): void
    {
        mkdir($this->tempDir . 'my-component', 0o777, true);
        file_put_contents($this->tempDir . 'my-component/Root.html', 'original content');

        $tester = $this->createCommandTester(
            $this->createRegistryService(['Root.html', 'Item.html']),
            $this->createStub(CacheManager::class),
        );
        $tester->execute([
            'components' => ['accordion'],
            '--extension' => 'my_ext',
            '--path' => '',
            '--force' => true,
            '--fluid-suffix' => false,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('<div>content</div>', file_get_contents($this->tempDir . 'my-component/Root.html'));
        $this->assertSame(['Item.html', 'Root.html'], $this->writtenFiles());
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
            'components' => ['accordion'],
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
            'components' => ['accordion'],
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
        $tester->execute(['components' => ['accordion'], '--extension' => 'my_ext', '--path' => '']);

        $this->assertSame(['Root.fluid.html'], $this->writtenFiles());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function componentPaths(): iterable
    {
        yield 'the default path' => [
            'Resources/Private/Components/ui/',
            'my_ext',
            'EXT:my_ext/Resources/Private/Components/ui/my-component',
        ];
        yield 'a custom path without trailing slash' => [
            'Resources/Private/Ui',
            'my_ext',
            'EXT:my_ext/Resources/Private/Ui/my-component',
        ];
        yield 'no path' => ['', 'my_ext', 'EXT:my_ext/my-component'];
    }

    #[Test]
    #[DataProvider('componentPaths')]
    public function replacesTheComponentPathTokenInTemplatesOnly(
        string $path,
        string $extension,
        string $expected,
    ): void {
        $tester = $this->createCommandTester(
            $this->createRegistryService(
                ['Root.html', 'Root.entry.ts'],
                '<vite:asset entry="%componentPath%/Root.entry.ts" />',
            ),
            $this->createStub(CacheManager::class),
        );
        $tester->execute(['components' => ['accordion'], '--extension' => $extension, '--path' => $path]);

        $this->assertSame(0, $tester->getStatusCode());
        $folder = $this->tempDir . ($path === '' ? '' : rtrim($path, '/') . '/') . 'my-component/';
        $this->assertSame(
            '<vite:asset entry="' . $expected . '/Root.entry.ts" />',
            file_get_contents($folder . 'Root.fluid.html'),
        );
        $this->assertSame(
            '<vite:asset entry="%componentPath%/Root.entry.ts" />',
            file_get_contents($folder . 'Root.entry.ts'),
        );
    }

    #[Test]
    public function warnsWhenTheViteNamespaceIsNotRegistered(): void
    {
        $registryService = $this->createRegistryService(
            ['Root.html'],
            '<vite:asset entry="%componentPath%/Root.entry.ts" />',
        );
        $execute = fn(): string => (function () use ($registryService): string {
            $tester = $this->createCommandTester($registryService, $this->createStub(CacheManager::class));
            $tester->execute([
                'components' => ['accordion'],
                '--extension' => 'my_ext',
                '--path' => '',
                '--force' => true,
            ]);

            return (string)preg_replace('/\s+/', ' ', $tester->getDisplay());
        })();

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['vite']);
        $this->assertStringContainsString('"vite" Fluid namespace is not registered', $execute());

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['vite'] = [
            'Praetorius\\ViteAssetCollector\\ViewHelpers',
        ];
        $this->assertStringNotContainsString('namespace is not registered', $execute());
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
        yield 'a dot segment' => ['./escape.html'];
        yield 'an empty segment' => ['sub//escape.html'];
        yield 'a delete character' => ["escape\x7f.html"];
        yield 'an escape sequence for the terminal' => ["\x1b[31mescape.html"];
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
        $tester->execute(['components' => ['accordion'], '--extension' => 'my_ext', '--path' => '']);

        $this->assertSame(1, $tester->getStatusCode());
        // the console wraps the message at the width of the terminal
        $this->assertStringContainsString(
            'not a path inside the component folder',
            (string)preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
        // a name never brings a control character of its own into the terminal
        $this->assertStringNotContainsString("\x1b", $tester->getDisplay());
        $this->assertSame(['Root.fluid.html'], $this->writtenFiles());
        $this->assertFileDoesNotExist($this->tempDir . 'escape.html');
        $this->assertFileDoesNotExist('/escape.html');
    }

    #[Test]
    public function doesNotWriteThroughASymlinkThatLeavesTheComponentFolder(): void
    {
        $outside = $this->tempDir . 'outside/';
        mkdir($outside);
        mkdir($this->tempDir . 'my-component');
        symlink($outside, $this->tempDir . 'my-component/Linked');

        $tester = $this->createCommandTester(
            $this->createRegistryService(['Root.html', 'Linked/Evil.html']),
            $this->createStub(CacheManager::class),
        );
        $tester->execute([
            'components' => ['accordion'],
            '--extension' => 'my_ext',
            '--path' => '',
            '--fluid-suffix' => false,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertFileDoesNotExist($outside . 'Evil.html');
        $this->assertFileExists($this->tempDir . 'my-component/Root.html');
    }

    #[Test]
    public function refusesAFolderNameThatLeavesTheTargetFolder(): void
    {
        $registryService = $this->createMock(RegistryService::class);
        $registryService->method('fetchComponent')->willReturn([null, ['name' => '../evil', 'files' => ['Root.html']]]);
        $registryService->expects($this->never())->method('fetchComponentFile');

        $tester = $this->createCommandTester($registryService, $this->createStub(CacheManager::class));
        $tester->execute(['components' => ['accordion'], '--extension' => 'my_ext', '--path' => 'components/']);

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
        $tester->execute(['components' => ['accordion'], '--extension' => 'my_ext', '--path' => '']);

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
        $tester->execute(['components' => ['accordion'], '--path' => ''], ['interactive' => true]);

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
        $tester->execute(['components' => ['accordion'], '--path' => ''], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame(['Root.fluid.html'], $this->writtenFiles());
    }

    /**
     * The files below the component folder, relative to it.
     *
     * @return list<string>
     */
    /**
     * A registry of `toast` (Toast/Root.html) and `toaster` (Toaster/Toaster.html), nothing else.
     */
    private function createTwoComponentRegistryService(): RegistryService
    {
        $components = [
            'toast' => ['key' => 'toast', 'name' => 'Toast', 'files' => ['Root.html']],
            'toaster' => ['key' => 'toaster', 'name' => 'Toaster', 'files' => ['Toaster.html']],
        ];
        $registryService = $this->createStub(RegistryService::class);
        $registryService->method('fetchComponent')->willReturnCallback(static fn(string $key): array => (
            array_key_exists($key, $components)
                ? [null, $components[$key]]
                : [['message' => 'Component not found in registry.', 'details' => null], []]
        ));
        $registryService->method('fetchComponentFile')->willReturn([null, '<div>content</div>']);
        $registryService->method('fetchComponentList')->willReturn([null, array_values($components)]);

        return $registryService;
    }

    #[Test]
    public function addsSeveralComponentsAndFlushesThePageCacheOnce(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->once())->method('flushCachesInGroup')->with('pages');

        $tester = $this->createCommandTester($this->createTwoComponentRegistryService(), $cacheManager);
        $tester->execute([
            'components' => ['toast', 'toaster'],
            '--extension' => 'my_ext',
            '--path' => '',
            '--fluid-suffix' => false,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($this->tempDir . 'Toast/Root.html');
        $this->assertFileExists($this->tempDir . 'Toaster/Toaster.html');
    }

    #[Test]
    public function writesNoComponentWhenAnotherOneIsUnknownOrInTheWay(): void
    {
        $registryService = $this->createTwoComponentRegistryService();
        $add = function (array $components) use ($registryService): CommandTester {
            $tester = $this->createCommandTester($registryService, $this->createStub(CacheManager::class));
            $tester->execute([
                'components' => $components,
                '--extension' => 'my_ext',
                '--path' => '',
                '--fluid-suffix' => false,
            ]);
            return $tester;
        };

        $unknown = $add(['toast', 'toastr']);
        $this->assertSame(1, $unknown->getStatusCode());
        $this->assertStringContainsString('toastr: Component not found in registry.', $unknown->getDisplay());
        $this->assertFileDoesNotExist($this->tempDir . 'Toast/Root.html');

        mkdir($this->tempDir . 'Toaster');
        file_put_contents($this->tempDir . 'Toaster/Toaster.html', 'original content');
        $inTheWay = $add(['toast', 'toaster']);
        $this->assertSame(1, $inTheWay->getStatusCode());
        $this->assertStringContainsString('- Toaster/Toaster.html', $inTheWay->getDisplay());
        $this->assertFileDoesNotExist($this->tempDir . 'Toast/Root.html');
    }

    #[Test]
    public function addsEveryComponentOfTheRegistryWithAllAndTakesNoNamesWithIt(): void
    {
        $registryService = $this->createTwoComponentRegistryService();
        $add = function (array $arguments) use ($registryService): CommandTester {
            $tester = $this->createCommandTester($registryService, $this->createStub(CacheManager::class));
            $tester->execute([...$arguments, '--extension' => 'my_ext', '--path' => '', '--fluid-suffix' => false]);
            return $tester;
        };

        $this->assertStringContainsString('or --all', $add([])->getDisplay());
        $this->assertStringContainsString('not both', $add(['components' => ['toast'], '--all' => true])->getDisplay());
        $this->assertFileDoesNotExist($this->tempDir . 'Toast/Root.html');

        $this->assertSame(0, $add(['--all' => true])->getStatusCode());
        $this->assertFileExists($this->tempDir . 'Toast/Root.html');
        $this->assertFileExists($this->tempDir . 'Toaster/Toaster.html');
    }

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
