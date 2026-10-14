<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * Reads the component registry (`Resources/Private/Registry`) of the installed version of the library from its
 * git tag, so a project never gets templates that need a newer release.
 */
class RegistryService
{
    private const string BASE_URL = 'https://raw.githubusercontent.com/jramke/fluid-primitives/%s/Resources/Private/Registry/';

    /** @var array<string, array{key: string, name: string, description: string, files: list<string>}>|null */
    private ?array $components = null;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly PackageManager $packageManager,
    ) {}

    /**
     * @return array{0: array{message: string, details: \Throwable|null}|null, 1: array<array-key, mixed>}
     */
    public function fetchComponent(string $componentKey): array
    {
        if ($componentKey === '' || $componentKey === '0') {
            throw new \InvalidArgumentException('Component key must not be empty.', 1767042111);
        }

        // the keys are camelCase like the Fluid tags, the kebab-case spelling of older releases still resolves
        $componentKey = ComponentNameUtility::lowerCaseDashedToCamelCase($componentKey);

        [$error, $components] = $this->loadComponents();
        if ($error !== null) {
            return [$error, []];
        }

        if (!array_key_exists($componentKey, $components)) {
            return [['message' => 'Component not found in registry.', 'details' => null], []];
        }

        return [null, $components[$componentKey]];
    }

    /**
     * @return array{0: array{message: string, details: \Throwable|null}|null, 1: string|null}
     */
    public function fetchComponentFile(string $componentKey, string $filePath): array
    {
        if ($componentKey === '' || $componentKey === '0' || ($filePath === '' || $filePath === '0')) {
            throw new \InvalidArgumentException('Component key and file path must not be empty.', 1767042112);
        }

        $componentKey = ComponentNameUtility::lowerCaseDashedToCamelCase($componentKey);

        [$error, $components] = $this->loadComponents();
        if ($error !== null || !array_key_exists($componentKey, $components)) {
            return [$error ?? ['message' => 'Component not found in registry.', 'details' => null], null];
        }

        try {
            $response = $this->client->request(
                'GET',
                $this->baseUrl() . $components[$componentKey]['name'] . '/' . $filePath,
            );
        } catch (GuzzleException $e) {
            return [['message' => 'Failed to fetch component file from registry.', 'details' => $e], null];
        }

        return [null, (string)$response->getBody()];
    }

    /**
     * @return array{0: array{message: string, details: \Throwable|null}|null, 1: array<array-key, mixed>}
     */
    public function fetchComponentList(): array
    {
        [$error, $components] = $this->loadComponents();

        return [$error, array_values($components)];
    }

    /**
     * @return array{0: array{message: string, details: \Throwable|null}|null, 1: array<string, array{key: string, name: string, description: string, files: list<string>}>}
     */
    private function loadComponents(): array
    {
        if ($this->components !== null) {
            return [null, $this->components];
        }

        try {
            $response = $this->client->request('GET', $this->baseUrl() . 'registry.yaml');
            $data = Typed::arrayOrNull(Yaml::parse((string)$response->getBody())) ?? [];
        } catch (GuzzleException|ParseException $e) {
            return [['message' => 'Failed to fetch component registry.', 'details' => $e], []];
        }

        $components = [];
        // Narrowed right below: the registry is hand-maintained, its shape is not trusted.
        // @mago-expect analysis:mixed-assignment
        foreach ($data as $key => $config) {
            $config = Typed::arrayOrNull($config) ?? [];
            $name = Typed::string($config['name'] ?? null);
            $files = array_map(Typed::string(...), Typed::arrayOrNull($config['files'] ?? null) ?? []);
            if ($name === '' || $files === []) {
                continue;
            }

            $components[(string)$key] = [
                'key' => (string)$key,
                'name' => $name,
                'description' => Typed::string($config['description'] ?? null),
                'files' => array_values($files),
            ];
        }

        if ($components === []) {
            return [['message' => 'Invalid component manifest received from registry.', 'details' => null], []];
        }

        return [null, $this->components = $components];
    }

    private function baseUrl(): string
    {
        return sprintf(self::BASE_URL, $this->ref());
    }

    /**
     * The tag of the installed release. Anything that is no release version (a branch checkout) gets `main`.
     */
    private function ref(): string
    {
        $version = $this->packageManager->getPackage('fluid_primitives')->getPackageMetaData()->getVersion();
        // TYPO3 hands out the Composer-normalized prerelease (1.0.0-beta2), the tag is 1.0.0-beta.2
        $version = (string)preg_replace('/-([A-Za-z]+)(\d+)$/', replacement: '-$1.$2', subject: $version);

        return preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', $version) === 1 ? $version : 'main';
    }
}
