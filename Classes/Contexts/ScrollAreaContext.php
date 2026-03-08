<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class ScrollAreaContext extends AbstractComponentContext
{
    public function getRootStyles(): string
    {
        $styles = [];

        $styles[] = 'position: relative;';

        // we cant calculate these on the server
        $styles[] = '--corner-width: 0px;';
        $styles[] = '--corner-height: 0px;';
        $styles[] = '--thumb-width: 0px;';
        $styles[] = '--thumb-height: 0px;';

        return implode(' ', $styles);
    }

    public function getScrollbarStyles(): string
    {
        $styles = [];

        $styles[] = 'position: absolute;';
        $styles[] = 'touch-action: none;';
        $styles[] = '-webkit-user-select: none;';
        $styles[] = 'user-select: none;';

        if ($this->get('scrollbar.orientation') === 'vertical') {
            $styles[] = 'top: 0;';
            $styles[] = 'bottom: var(--corner-height);';
            $styles[] = 'inset-inline-end: 0;';
        } elseif ($this->get('scrollbar.orientation') === 'horizontal') {
            $styles[] = 'inset-inline-start: 0;';
            $styles[] = 'inset-inline-end: var(--corner-width);';
            $styles[] = 'bottom: 0;';
        }

        return implode(' ', $styles);
    }

    public function getThumbStyles(): string
    {
        $styles = [];

        if ($this->get('scrollbar.orientation') === 'vertical') {
            $styles[] = 'height: var(--thumb-height);';
        } elseif ($this->get('scrollbar.orientation') === 'horizontal') {
            $styles[] = 'width: var(--thumb-width);';
        }

        return implode(' ', $styles);
    }
}
