<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class RegistryService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://fluid-primitives.ddev.site/'
            // 'base_uri' => 'https://fluid-primitives.com/'
        ]);
    }

    public function fetchComponent(string $componentKey): array
    {
        if (empty($componentKey)) {
            throw new \InvalidArgumentException('Component key must not be empty.', 1767042111);
        }

        $data = [];
        $error = null;

        try {
            $response = $this->client->get("/registry/components/{$componentKey}");
            $data = json_decode((string)$response->getBody(), true);
        } catch (ClientException $e) {
            $error = [
                'message' => 'Component not found in registry.',
                'details' => $e,
            ];
        }

        if (empty($data['files'] ?? []) || empty($data['name'] ?? null)) {
            $error = [
                'message' => 'Invalid component manifest received from registry.',
                'details' => null,
            ];
        }

        return [$error, $data];
    }

    public function fetchComponentFile(string $componentKey, string $filePath): array
    {
        if (empty($componentKey) || empty($filePath)) {
            throw new \InvalidArgumentException('Component key and file path must not be empty.', 1767042112);
        }

        $data = null;
        $error = null;

        try {
            $response = $this->client->get("/registry/components/{$componentKey}/files/{$filePath}");
            $data = (string)$response->getBody();
        } catch (ClientException $e) {
            $error = [
                'message' => 'Failed to fetch component file from registry.',
                'details' => $e,
            ];
        }

        return [$error, $data];
    }

    public function fetchComponentList(): array
    {
        $data = [];
        $error = null;

        try {
            $response = $this->client->get("/registry/components");
            $data = json_decode((string)$response->getBody(), true);
        } catch (ClientException $e) {
            $error = [
                'message' => 'Failed to fetch component registry.',
                'details' => $e,
            ];
        }

        return [$error, $data];
    }
}
