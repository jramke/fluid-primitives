<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Builds the `window.FluidPrimitives` inline script payload {@see HydrationRegistry} writes to the
 * page: pretty-printed and unminified in development, compacted to a single line otherwise.
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

    public function build(array $registry, array $globals, array $nestedComponentsByScope, bool $development): string
    {
        $js = <<<JS
        (function() {
        window.FluidPrimitives = {
            uncontrolledInstances: {},
            globals: {$this->toJson($globals, $development)},
            hydrationData: {$this->toJson($registry, $development)},
            nestedComponents: {$this->toJson($nestedComponentsByScope, $development)}
        };
        })();
        JS;

        if ($development) {
            return $js;
        }

        return $this->minify($js);
    }

    private function minify(string $js): string
    {
        $js = str_replace("\n", replace: '', subject: $js);
        $js = str_replace("\r", replace: '', subject: $js);
        $js = preg_replace('/\s+/', replacement: ' ', subject: $js); // replace multiple whitespaces with one space
        return (string)preg_replace('/\s*([{}();=])\s*/', replacement: '$1', subject: (string)$js); // remove spaces around special characters
    }

    private function toJson(array $data, bool $development): string
    {
        // HEX flags prevent a string containing "</script>" from breaking out of the inline
        // <script> tag this gets embedded into (see HydrationRegistry::updateAssetCollector()).
        $flags =
            JSON_THROW_ON_ERROR |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT;

        if ($development) {
            return json_encode($data, $flags | JSON_PRETTY_PRINT);
        }
        return json_encode($data, $flags);
    }
}
