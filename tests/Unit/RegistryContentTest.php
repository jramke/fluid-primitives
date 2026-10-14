<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;

/**
 * `ui:add` reads `registry.yaml` straight from the git tag, nothing checks it on the way, so a typo ships.
 */
final class RegistryContentTest extends TestCase
{
    #[Test]
    public function everyComponentOfTheRegistryListsOnlyFilesThatExistInItsFolder(): void
    {
        $registryDir = dirname(__DIR__, 2) . '/Resources/Private/Registry/';
        /** @var array<string, array{name: string, files: list<string>}> $registry */
        $registry = Yaml::parseFile($registryDir . 'registry.yaml');
        $this->assertNotEmpty($registry);

        foreach ($registry as $key => $component) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $component['name'], $key);
            $this->assertNotEmpty($component['files'], $key);
            foreach ($component['files'] as $file) {
                $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]+$/', $file, $key);
                $this->assertFileExists($registryDir . $component['name'] . '/' . $file, $key);
            }
        }
    }

    #[Test]
    public function everyComponentLoadsItsListedEntryFileThroughExactlyOneComponentPathToken(): void
    {
        $registryDir = dirname(__DIR__, 2) . '/Resources/Private/Registry/';
        /** @var array<string, array{name: string, files: list<string>}> $registry */
        $registry = Yaml::parseFile($registryDir . 'registry.yaml');

        foreach ($registry as $key => $component) {
            $entry = $component['name'] . '.entry.ts';
            $this->assertContains($entry, $component['files'], $key);

            $tags = [];
            foreach ($component['files'] as $file) {
                if (str_ends_with($file, '.html')) {
                    preg_match_all(
                        '/<vite:asset entry="%componentPath%\/([^"]+)"/',
                        (string)file_get_contents($registryDir . $component['name'] . '/' . $file),
                        $matches,
                    );
                    array_push($tags, ...$matches[1]);
                }
            }
            $this->assertSame([$entry], $tags, $key);
        }
    }
}
