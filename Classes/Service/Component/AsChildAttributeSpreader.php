<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

/**
 * Implements the `asChild` prop pattern: instead of rendering its own wrapping tag, a component
 * spreads its own resolved attributes onto its single child element's opening tag (the child's own
 * attributes win on conflict), mirroring Radix UI's/Base UI's `asChild`/render-prop pattern.
 */
final readonly class AsChildAttributeSpreader
{
    public function spread(string $childHtml, string $componentHtml): string
    {
        // Extract child tag + attributes
        if (!preg_match('/^\s*<([a-zA-Z0-9]+)([^>]*)>/', $childHtml, $childMatches)) {
            return $childHtml; // fallback
        }
        $childTag = $childMatches[1];
        $childAttrs = $this->parseAttributes(trim($childMatches[2]));

        // Extract parent/component attributes
        if (!preg_match('/^\s*<([a-zA-Z0-9]+)([^>]*)>/', $componentHtml, $compMatches)) {
            return $childHtml;
        }
        $componentAttrs = $this->parseAttributes(trim($compMatches[2]));

        foreach ($componentAttrs as $name => $value) {
            if (isset($childAttrs[$name])) {
                continue;
            }

            $childAttrs[$name] = $value;
        }

        // Rebuild attributes
        $finalAttrs = '';
        foreach ($childAttrs as $k => $v) {
            $finalAttrs .= $v === null ? " {$k}" : ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
        }

        // Replace child opening tag
        return preg_replace('/^\s*<' . $childTag . '[^>]*>/', '<' . $childTag . $finalAttrs . '>', $childHtml, 1);
    }

    /**
     * @return array<string, string|null>
     */
    private function parseAttributes(string $attrString): array
    {
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:="([^"]*)")?/', $attrString, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            $attrs[$match[1]] = $match[2] ?? null; // supports boolean attrs
        }

        return $attrs;
    }
}
