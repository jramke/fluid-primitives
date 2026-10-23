<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Domain\Dto\ScannedTag;

/**
 * Reads an opening tag out of a Fluid template source without parsing the template: which attributes the tag sets
 * itself and which `{...}` sit in it. A `>` inside a quoted value or a `{...}` does not close the tag.
 */
final class TemplateTagScanner
{
    // @mago-expect lint:halstead
    public static function scan(string $source, int $start): ScannedTag
    {
        $length = strlen($source);
        $depth = 0;
        $quote = null;
        $literal = '';
        $regions = [];
        $regionStart = 0;

        for ($offset = $start; $offset < $length; $offset++) {
            $char = $source[$offset];

            if ($char === '{') {
                if ($depth === 0) {
                    $regionStart = $offset;
                }
                $depth++;
                continue;
            }

            if ($depth > 0) {
                // a region inside a quoted value is part of that value, not a region of the tag
                if ($char === '}' && --$depth === 0 && $quote === null) {
                    $regions[$regionStart] = substr($source, $regionStart, $offset + 1 - $regionStart);
                    $literal .= ' ';
                }
                continue;
            }

            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                $offset++;
                break;
            }

            $literal .= $char;
        }

        $name = [];
        preg_match('/^<([^\s\/>]+)/', $literal, $name);
        $attributes = (string)preg_replace('/^<[^\s\/>]+/', replacement: '', subject: $literal);

        return new ScannedTag($name[1] ?? '', $offset, $regions, self::attributeNames($attributes, $regions));
    }

    /**
     * @param array<int, string> $regions
     * @return list<string>
     */
    private static function attributeNames(string $literal, array $regions): array
    {
        $names = self::tokens($literal);

        foreach ($regions as $region) {
            if (!str_starts_with($region, '{f:if(')) {
                continue;
            }

            $strings = [];
            preg_match_all(
                '/\b(?:then|else)\s*:\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/',
                $region,
                $strings,
                PREG_SET_ORDER,
            );
            foreach ($strings as $string) {
                // `'class="{class}"'` is the attribute `class` once its braces and value are gone
                $attributes = (string)preg_replace(
                    '/\{[^{}]*\}/',
                    replacement: '',
                    subject: $string[1] ?: $string[2] ?? '',
                );
                $attributes = (string)preg_replace('/"[^"]*"|\'[^\']*\'/', replacement: '', subject: $attributes);
                $names = [...$names, ...self::tokens($attributes)];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $attributes): array
    {
        $matches = [];
        preg_match_all('/(?<![^\s])([a-zA-Z_:@][\w:.@-]*)(?==|\s|\/|$)/', $attributes, $matches);

        return array_values($matches[1]);
    }
}
