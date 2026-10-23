<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Utility\TemplateTagScanner;
use TYPO3Fluid\Fluid\Core\Parser\TemplateProcessorInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Makes `{ui:attributes()}` leave out every attribute the same HTML tag sets itself, so a template never
 * repeats them in `skip`. A caller's attribute never overrides what the template writes.
 *
 * It rewrites the source before it is parsed: `<div hidden="{x}" {ui:attributes()}>` becomes
 * `<div hidden="{x}" {ui:attributes(skip: 'hidden')}>`. The names come from the literal attributes of the tag and
 * from the `then` and `else` strings of an `f:if` written in it, so an attribute that is only rendered under a
 * condition counts as set. An element carrying the call is the only target: a component tag forwards the attributes
 * through its `attributes` prop, and its own attributes are props.
 */
final readonly class OwnedAttributesTemplateProcessor implements TemplateProcessorInterface
{
    public function setRenderingContext(RenderingContextInterface $renderingContext): void {}

    public function preProcessSource(string $templateSource): string
    {
        if (!str_contains($templateSource, '{ui:attributes(')) {
            return $templateSource;
        }

        $replacements = [];
        $length = strlen($templateSource);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($templateSource[$offset] !== '<' || !ctype_alpha($templateSource[$offset + 1] ?? '')) {
                continue;
            }

            $tag = TemplateTagScanner::scan($templateSource, $offset);
            $offset = $tag->end - 1;

            if ($tag->attributeNames === [] || str_contains($tag->name, ':')) {
                continue;
            }

            foreach ($tag->regions as $start => $region) {
                $call = [];
                if (!preg_match('/^\{ui:attributes\(([^()]*)\)\}$/', $region, $call)) {
                    continue;
                }

                $arguments = trim($call[1]);
                if (preg_match('/\b(?:skip|only)\s*:/', $arguments)) {
                    continue;
                }

                $replacements[$start] = [
                    strlen($region),
                    sprintf(
                        "{ui:attributes(%sskip: '%s')}",
                        $arguments === '' ? '' : $arguments . ', ',
                        implode(',', $tag->attributeNames),
                    ),
                ];
            }
        }

        krsort($replacements);
        foreach ($replacements as $start => [$callLength, $replacement]) {
            $templateSource = substr_replace($templateSource, $replacement, $start, $callLength);
        }

        return $templateSource;
    }
}
