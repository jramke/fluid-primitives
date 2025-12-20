<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class CollapsibleContext extends AbstractComponentContext
{
    public function getState(): string
    {
        return $this->get('defaultOpen') ? 'open' : 'closed';
    }

    public function getHasCollapsedSize(): bool
    {
        return $this->get('collapsedHeight') || $this->get('collapsedWidth');
    }

    // we can only apply the styles related to the collapsed size, 
    // the --height and --width variables are applied on the client
    public function getContentStyleString(): string
    {
        $styles = [];

        if ($this->get('defaultOpen') === false) {
            if ($this->get('collapsedHeight')) {
                $styles[] = "--collapsed-height: {$this->get('collapsedHeight')};";
                $styles[] = "overflow: hidden;";
                $styles[] = "min-height: {$this->get('collapsedHeight')};";
                $styles[] = "max-height: {$this->get('collapsedHeight')};";
            }
            if ($this->get('collapsedWidth')) {
                $styles[] = "--collapsed-width: {$this->get('collapsedWidth')};";
                $styles[] = "overflow: hidden;";
                $styles[] = "min-width: {$this->get('collapsedWidth')};";
                $styles[] = "max-width: {$this->get('collapsedWidth')};";
            }
        }

        return implode(' ', $styles);
    }
}
