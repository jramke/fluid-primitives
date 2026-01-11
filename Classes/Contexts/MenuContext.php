<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class MenuContext extends AbstractComponentContext
{
    public function getState(): string
    {
        return $this->get('defaultOpen') ? 'open' : 'closed';
    }

    public function getDataAttributes(): array
    {
        return [
            'state' => $this->getState(),
        ];
    }
}
