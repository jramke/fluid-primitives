<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Jramke\FluidPrimitives\Utility\Typed;

class RegistryService
{
    private const string REGISTRY_URL = 'https://fluid-primitives.com';

    public function __construct(
        private readonly ClientInterface $client,
    ) {}

    /**
     * @return array{0: array{message: string, details: \Throwable|null}|null, 1: array<array-key, mixed>}
     */
    public function fetchComponent(string $componentKey): array
    {
        if ($componentKey === '' || $componentKey === '0') {
            throw new \InvalidArgumentException('Component key must not be empty.', 1767042111);
        }

        $data = [];
        $error = null;

        try {
            $response = $this->client->request('GET', self::REGISTRY_URL . "/registry/components/{$componentKey}");
            $data = Typed::arrayOrNull(json_decode((string)$response->getBody(), associative: true)) ?? [];
        } catch (ClientException $e) {
            $error = [
                'message' => 'Component not found in registry.',
                'details' => $e,
            ];
        }

        if (($data['files'] ?? []) === [] || ($data['name'] ?? '') === '') {
            $error = [
                'message' => 'Invalid component manifest received from registry.',
                'details' => null,
            ];
        }

        return [$error, $data];
    }

    /**
     * @return array{0: array{message: string, details: \Throwable|null}|null, 1: string|null}
     */
    public function fetchComponentFile(string $componentKey, string $filePath): array
    {
        if ($componentKey === '' || $componentKey === '0' || ($filePath === '' || $filePath === '0')) {
            throw new \InvalidArgumentException('Component key and file path must not be empty.', 1767042112);
        }

        $data = null;
        $error = null;

        try {
            $response = $this->client->request(
                'GET',
                self::REGISTRY_URL . "/registry/components/{$componentKey}/files/{$filePath}",
            );
            $data = (string)$response->getBody();
        } catch (ClientException $e) {
            $error = [
                'message' => 'Failed to fetch component file from registry.',
                'details' => $e,
            ];
        }

        return [$error, $data];
    }

    /**
     * @return array{0: array{message: string, details: \Throwable|null}|null, 1: array<array-key, mixed>}
     */
    public function fetchComponentList(): array
    {
        $data = [];
        $error = null;

        try {
            $response = $this->client->request('GET', self::REGISTRY_URL . '/registry/components');
            $data = Typed::arrayOrNull(json_decode((string)$response->getBody(), associative: true)) ?? [];
        } catch (ClientException $e) {
            $error = [
                'message' => 'Failed to fetch component registry.',
                'details' => $e,
            ];
        }

        return [$error, $data];
    }
}
