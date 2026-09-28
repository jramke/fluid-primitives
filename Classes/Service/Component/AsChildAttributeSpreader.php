<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Constants;

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
        $childMatches = null;
        if (!preg_match('/^\s*<([a-zA-Z0-9]+)([^>]*)>/', $childHtml, $childMatches)) {
            return $childHtml; // fallback
        }
        $childTag = $childMatches[1];
        $childAttrs = $this->parseAttributes(trim($childMatches[2]));

        // Extract the component's own asChild-target tag + attributes. Searched anywhere in the
        // rendered output (not just anchored at the start) and identified by
        // Constants::AS_CHILD_TARGET_MARKER - the attribute AsChildViewHelper renders onto whichever
        // tag it was placed on - rather than assuming it's the first tag in the string, so a
        // component with more than one bare tag still merges onto the one its author actually marked.
        $compMatches = null;
        if (!preg_match(
            '/<([a-zA-Z0-9]+)([^>]*\b' . preg_quote(Constants::AS_CHILD_TARGET_MARKER, delimiter: '/') . '\b[^>]*)>/',
            $componentHtml,
            $compMatches,
        )) {
            // No marked tag in $componentHtml itself - this call has no wrapper tag of its own to
            // merge onto (a thin ui:useProps + spreadProps wrapper, which only ever forwards
            // asChild="{true}" one level down to a real primitive that DOES declare {ui:asChild()}).
            // $componentHtml is then already that primitive's fully-rendered, already-merged result
            // (its own marker consumed by this same method one call down) - the correct thing to
            // return, not the wrapper's own raw, unmerged slot content.
            return $componentHtml;
        }
        $componentAttrs = $this->parseAttributes(trim($compMatches[2]));
        unset($componentAttrs[Constants::AS_CHILD_TARGET_MARKER]);

        foreach ($componentAttrs as $name => $value) {
            if (($childAttrs[$name] ?? null) !== null) {
                continue;
            }

            $childAttrs[$name] = $value;
        }

        // Rebuild attributes. $v was extracted straight out of already-rendered HTML (both
        // $childHtml and $componentHtml are fully-rendered Fluid output, already escaped exactly
        // once by whatever produced them) - re-escaping it here would double-encode any entity
        // already present (e.g. a Tailwind `[&_svg]` selector's `&amp;` becoming `&amp;amp;`), so
        // it's reused verbatim instead of passing through htmlspecialchars() again.
        $finalAttrs = '';
        foreach ($childAttrs as $k => $v) {
            $finalAttrs .= $v === null ? " {$k}" : ' ' . $k . '="' . $v . '"';
        }

        // Replace child opening tag
        return (string)preg_replace(
            '/^\s*<' . $childTag . '[^>]*>/',
            '<' . $childTag . $finalAttrs . '>',
            $childHtml,
            limit: 1,
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function parseAttributes(string $attrString): array
    {
        $matches = null;
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:="([^"]*)")?/', $attrString, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            $attrs[$match[1]] = $match[2] ?? null; // supports boolean attrs
        }

        return $attrs;
    }
}
