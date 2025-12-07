<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class FieldContext extends AbstractComponentContext
{
    public function beforeRendering(): void
    {
        $parentRenderingContext = $this->getParentRenderingContext();
        if (!$parentRenderingContext) return;

        // TODO: only do this if we are in a form 
        // we could check the variablecontainer of the parent rendering context if there is a form context
        $parentRenderingContext->getViewHelperVariableContainer()->add(self::class, $this->get('rootId'), ['name' => $this->get('name')]);
    }

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
