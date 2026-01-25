<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;

class SelectContext extends AbstractComponentContext
{
    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?array
    {
        if (is_string($this->get('defaultValue'))) {
            return [$this->get('defaultValue')];
        }

        return $this->get('defaultValue');
    }
}
