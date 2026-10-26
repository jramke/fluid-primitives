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
        $globalsJson = $this->toJson($globals, $development);
        $hydrationJson = $this->toJson($registry, $development);
        $nestedJson = $this->toJson($nestedComponentsByScope, $development);

        if (!$development) {
            // not a regex over the whole script: it would also rewrite whitespace inside the JSON strings
            return "(function(){window.FluidPrimitives={componentInstances:{},globals:{$globalsJson},hydrationData:{$hydrationJson},nestedComponents:{$nestedJson}};})();";
        }

        return <<<JS
        (function() {
        window.FluidPrimitives = {
            componentInstances: {},
            globals: {$globalsJson},
            hydrationData: {$hydrationJson},
            nestedComponents: {$nestedJson}
        };
        })();
        JS;
    }

    private function toJson(array $data, bool $development): string
    {
        // PHP encodes an empty array as [], but every one of these is a map for the client
        $value = $data === [] ? new \stdClass() : $data;

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
            return json_encode($value, $flags | JSON_PRETTY_PRINT);
        }
        return json_encode($value, $flags);
    }
}
