<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Package\MetaData;
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
    empty:
      name: Empty
      files: []
    YAML;

    #[Test]
    #[DataProvider('installedVersions')]
    public function readsTheRegistryOfTheTagOfTheInstalledVersion(string $installed, string $expectedRef): void
    {
        $urls = [];
        $client = $this->createStub(ClientInterface::class);
        $client
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url) use (&$urls): Response {
                $urls[] = $url;
                return new Response(200, body: str_ends_with($url, 'registry.yaml') ? self::INDEX : '<div />');
            });

        $registry = new RegistryService($client, $this->packageManagerWithVersion($installed));
        $registry->fetchComponent('accordion');
        $registry->fetchComponentFile('accordion', 'Root.fluid.html');

        $base =
            'https://raw.githubusercontent.com/jramke/fluid-primitives/' .
            $expectedRef .
            '/Resources/Private/Registry/';
        // the index is read once for the manifest and the file, the file comes out of the component folder
        $this->assertSame([$base . 'registry.yaml', $base . 'Accordion/Root.fluid.html'], $urls);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function installedVersions(): array
    {
        return [
            'release' => ['1.2.0', '1.2.0'],
            'prerelease' => ['1.0.0-beta.1', '1.0.0-beta.1'],
            'not a release' => ['dev-main', 'main'],
        ];
    }

    #[Test]
    public function reportsWhatIsWrongWithTheRegistryInsteadOfThrowing(): void
    {
        $notFound = new ClientException('Not Found', new Request('GET', '/'), new Response(404));
        $client = $this->createStub(ClientInterface::class);
        $client->method('request')->willReturnOnConsecutiveCalls(
            new Response(200, body: self::INDEX),
            $this->throwException($notFound),
        );

        $registry = new RegistryService($client, $this->packageManagerWithVersion('1.0.0'));

        [$error, $manifest] = $registry->fetchComponent('accordion');
        $this->assertNull($error);
        $this->assertSame(['Root.fluid.html', 'Accordion.entry.ts'], $manifest['files']);

        [$error] = $registry->fetchComponent('nope');
        $this->assertSame('Component not found in registry.', $error['message'] ?? null);

        // a component without files is no component, however the registry got there
        [$error] = $registry->fetchComponent('empty');
        $this->assertSame('Component not found in registry.', $error['message'] ?? null);

        [$error, $content] = $registry->fetchComponentFile('accordion', 'Root.fluid.html');
        $this->assertSame('Failed to fetch component file from registry.', $error['message'] ?? null);
        $this->assertNull($content);
    }

    #[Test]
    public function failsWhenTheIndexCannotBeFetched(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client
            ->method('request')
            ->willThrowException(new ClientException('Not Found', new Request('GET', '/'), new Response(404)));

        $registry = new RegistryService($client, $this->packageManagerWithVersion('1.0.0'));

        [$error] = $registry->fetchComponentList();
        $this->assertSame('Failed to fetch component registry.', $error['message'] ?? null);
    }

    private function packageManagerWithVersion(string $version): PackageManager
    {
        $metaData = $this->createStub(MetaData::class);
        $metaData->method('getVersion')->willReturn($version);
        $package = $this->createStub(PackageInterface::class);
        $package->method('getPackageMetaData')->willReturn($metaData);
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('getPackage')->willReturn($package);

        return $packageManager;
    }
}
