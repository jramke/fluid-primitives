<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Builds the JSON of the `<script type="application/json">` block {@see HydrationRegistry} writes to the page:
 * pretty-printed in development, on a single line otherwise. The client library reads every such block of the
 * page into `window.FluidPrimitives` on first access.
 */
final class HydrationScriptBuilder
{
    public function isDevelopment(): bool
    {
        try {
            return Environment::getContext()->isDevelopment();
        } catch (\Throwable) {
            // If Environment is not initialized (e.g., in unit tests), assume production
            return false;
        }
    }

    /**
     * @param array|null $globals `null` leaves them out, for a block behind the one that carries them.
     */
    public function build(array $registry, ?array $globals, array $nestedComponentsByScope, bool $development): string
    {
        // HEX flags prevent a string containing "</script>" from breaking out of the <script> block
        // this gets embedded into (see HydrationRegistry::updateAssetCollector()).
        $flags =
            JSON_THROW_ON_ERROR |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT;

        $data = [
            'hydrationData' => $this->toMap($registry),
            'nestedComponents' => $this->toMap($nestedComponentsByScope),
        ];
        if ($globals !== null) {
            $data = ['globals' => $this->toMap($globals)] + $data;
        }

        if ($development) {
            return json_encode($data, $flags | JSON_PRETTY_PRINT);
        }
        return json_encode($data, $flags);
    }

    private function toMap(array $data): array|\stdClass
    {
        // PHP encodes an empty array as [], but every one of these is a map for the client
        return $data === [] ? new \stdClass() : $data;
    }
}
