<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class PopoverContext extends AbstractComponentContext
{
    public function getState()
    {
        return $this->get('defaultOpen') ? 'open' : 'closed';
    }
}
