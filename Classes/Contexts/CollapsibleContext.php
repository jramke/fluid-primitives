<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

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
            $collapsedHeight = Typed::stringOrNull($this->get('collapsedHeight'));
            if ($collapsedHeight) {
                $styles[] = "--collapsed-height: {$collapsedHeight};";
                $styles[] = 'overflow: hidden;';
                $styles[] = "min-height: {$collapsedHeight};";
                $styles[] = "max-height: {$collapsedHeight};";
            }

            $collapsedWidth = Typed::stringOrNull($this->get('collapsedWidth'));
            if ($collapsedWidth) {
                $styles[] = "--collapsed-width: {$collapsedWidth};";
                $styles[] = 'overflow: hidden;';
                $styles[] = "min-width: {$collapsedWidth};";
                $styles[] = "max-width: {$collapsedWidth};";
            }
        }

        return implode(' ', $styles);
    }
}
