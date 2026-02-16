<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class CheckboxGroupContext extends AbstractComponentContext
{
    public function getDataAttributes(): array
    {
        return [
            'readonly' => $this->get('readOnly') ?? null,
            'disabled' => $this->get('disabled') ?? null,
            'invalid' => $this->get('invalid') ?? null,
            'required' => $this->get('required') ?? null,
        ];
    }
}
