<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Traits\HasIndicatorStateTrait;
use Jramke\FluidPrimitives\Utility\Typed;

class CollapsibleContext extends AbstractComponentContext
{
    use HasIndicatorStateTrait;

    public function getState(): string
    {
        return Typed::bool($this->get('defaultOpen')) ? 'open' : 'closed';
    }

    public function getHasCollapsedSize(): bool
    {
        return (
            (bool)Typed::stringOrNull($this->get('collapsedHeight')) ||
            (bool)Typed::stringOrNull($this->get('collapsedWidth'))
        );
    }

    // we can only apply the styles related to the collapsed size,
    // the --height and --width variables are applied on the client
    public function getContentStyleString(): string
    {
        $styles = [];

        if (Typed::boolOrNull($this->get('defaultOpen')) === false) {
            $collapsedHeight = $this->getCssLength('collapsedHeight');
            if ($collapsedHeight) {
                $styles[] = "--collapsed-height: {$collapsedHeight};";
                $styles[] = 'overflow: hidden;';
                $styles[] = "min-height: {$collapsedHeight};";
                $styles[] = "max-height: {$collapsedHeight};";
            }

            $collapsedWidth = $this->getCssLength('collapsedWidth');
            if ($collapsedWidth) {
                $styles[] = "--collapsed-width: {$collapsedWidth};";
                $styles[] = 'overflow: hidden;';
                $styles[] = "min-width: {$collapsedWidth};";
                $styles[] = "max-width: {$collapsedWidth};";
            }
        }

        return implode(' ', $styles);
    }

    /**
     * The value goes into a `style` attribute, where a `;` would add declarations of its own.
     */
    private function getCssLength(string $prop): ?string
    {
        $length = Typed::stringOrNull($this->get($prop));
        if ($length === null || $length === '') {
            return null;
        }

        $isLength = preg_match('/^-?(\d+|\d*\.\d+)([a-z]{1,5}|%)?$/i', $length) === 1;
        $isMath = preg_match('/^(calc|min|max|clamp)\([0-9a-z\s.,+*\/%()-]+\)$/i', $length) === 1;
        if (!$isLength && !$isMath) {
            throw new InvalidPropValueException(
                sprintf('The prop "%s" must be a CSS length like "120px", "%s" given.', $prop, $length),
                1_788_100_042,
            );
        }

        return $length;
    }
}
