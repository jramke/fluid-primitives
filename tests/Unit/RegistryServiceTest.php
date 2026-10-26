<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;

final class RegistryServiceTest extends TestCase
{
    private const string INDEX = <<<'YAML'
    accordion:
      name: Accordion
      description: A set of collapsible panels
      files:
        - Root.fluid.html
        - Accordion.entry.ts
    scrollArea:
      name: ScrollArea
      description: A scroll container
      files:
        - Root.fluid.html
    empty:
      name: Empty
      files: []
    YAML;

    private string $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = sys_get_temp_dir() . '/fluid-primitives-registry-' . bin2hex(random_bytes(4));
        $registry = $this->package . '/Resources/Private/Registry';
        mkdir($registry . '/Accordion', 0o755, recursive: true);
        mkdir($registry . '/ScrollArea', 0o755, recursive: true);
        file_put_contents($registry . '/registry.yaml', self::INDEX);
        file_put_contents($registry . '/Accordion/Root.fluid.html', '<div>accordion</div>');
        file_put_contents($registry . '/ScrollArea/Root.fluid.html', '<div>scroll</div>');
        file_put_contents($registry . '/secret.txt', 'not a component file');
        symlink($registry . '/secret.txt', $registry . '/Accordion/link.txt');
    }

    protected function tearDown(): void
    {
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->package, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->package);
        parent::tearDown();
    }

    #[Test]
    public function readsTheRegistryThatShipsWithTheInstalledPackage(): void
    {
        $registry = new RegistryService($this->packageManager());

        [$error, $manifest] = $registry->fetchComponent('accordion');
        $this->assertNull($error);
        $this->assertSame(['Root.fluid.html', 'Accordion.entry.ts'], $manifest['files']);

        [$error, $content] = $registry->fetchComponentFile('accordion', 'Root.fluid.html');
        $this->assertNull($error);
        $this->assertSame('<div>accordion</div>', $content);

        [$error, $components] = $registry->fetchComponentList();
        $this->assertNull($error);
        $this->assertSame(['accordion', 'scrollArea'], array_column($components, 'key'));
    }

    #[Test]
    public function reportsWhatIsWrongWithTheRegistryInsteadOfThrowing(): void
    {
        $registry = new RegistryService($this->packageManager());

        [$error] = $registry->fetchComponent('nope');
        $this->assertSame('Component not found in registry.', $error['message'] ?? null);

        // a component without files is no component, however the registry got there
        [$error] = $registry->fetchComponent('empty');
        $this->assertSame('Component not found in registry.', $error['message'] ?? null);

        [$error, $content] = $registry->fetchComponentFile('accordion', 'Accordion.entry.ts');
        $this->assertSame('Failed to fetch component file from registry.', $error['message'] ?? null);
        $this->assertNull($content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pathsOutsideTheComponentFolder(): array
    {
        return [
            'a parent folder' => ['../secret.txt'],
            'a parent folder after a folder' => ['Examples/../../secret.txt'],
            'another component' => ['../ScrollArea/Root.fluid.html'],
            'an absolute path' => ['/etc/hosts'],
            'a symlink out of the folder' => ['link.txt'],
        ];
    }

    #[Test]
    #[DataProvider('pathsOutsideTheComponentFolder')]
    public function neverReadsAFileOutsideTheFolderOfTheComponent(string $path): void
    {
        [$error, $content] = (new RegistryService($this->packageManager()))->fetchComponentFile('accordion', $path);

        $this->assertSame('Failed to fetch component file from registry.', $error['message'] ?? null);
        $this->assertNull($content);
    }

    #[Test]
    #[DataProvider('spellingsOfAMultiWordKey')]
    public function resolvesACamelCaseKeyAndItsOlderKebabCaseSpelling(string $key): void
    {
        $registry = new RegistryService($this->packageManager());

        [$error, $manifest] = $registry->fetchComponent($key);
        $this->assertNull($error);
        $this->assertSame('scrollArea', $manifest['key']);

        [, $content] = $registry->fetchComponentFile($key, 'Root.fluid.html');
        $this->assertSame('<div>scroll</div>', $content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function spellingsOfAMultiWordKey(): array
    {
        return ['camelCase' => ['scrollArea'], 'kebab-case' => ['scroll-area']];
    }

    #[Test]
    public function failsWhenThePackageHasNoRegistryIndex(): void
    {
        unlink($this->package . '/Resources/Private/Registry/registry.yaml');

        [$error] = (new RegistryService($this->packageManager()))->fetchComponentList();

        $this->assertSame('Failed to fetch component registry.', $error['message'] ?? null);
    }

    private function packageManager(): PackageManager
    {
        $package = $this->createStub(PackageInterface::class);
        $package->method('getPackagePath')->willReturn($this->package . '/');
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('getPackage')->willReturn($package);

        return $packageManager;
    }
}
