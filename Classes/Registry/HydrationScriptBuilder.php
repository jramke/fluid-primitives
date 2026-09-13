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

    public function build(array $registry, array $globals, bool $development): string
    {
        $js = <<<JS
        (function() {
        window.FluidPrimitives = {
            uncontrolledInstances: {},
            globals: {$this->toJson($globals, $development)},
            hydrationData: {$this->toJson($registry, $development)}
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
        $js = str_replace("\n", '', $js);
        $js = str_replace("\r", '', $js);
        $js = preg_replace('/\s+/', ' ', $js); // replace multiple whitespaces with one space
        return preg_replace('/\s*([{}();=])\s*/', '$1', (string)$js); // remove spaces around special characters
    }

    private function toJson(array $data, bool $development): string
    {
        if ($development) {
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
