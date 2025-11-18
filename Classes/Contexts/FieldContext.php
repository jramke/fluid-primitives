<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class FieldContext extends AbstractComponentContext
{
    public function getChildVariables(): array
    {
        return [
            'name' => $this->get('name') ?? null,
            'disabled' => $this->get('disabled') ?? null,
            'readOnly' => $this->get('readOnly') ?? null,
            'required' => $this->get('required') ?? null,
            'invalid' => $this->get('invalid') ?? null,
        ];
    }
}
