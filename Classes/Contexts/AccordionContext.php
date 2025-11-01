<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class AccordionContext extends AbstractComponentContext
{
    /**
     * @param array{ value:string, disabled?:bool|null } $item
     */
    public function getItemState(array $item): object
    {
        $value = $item['value'] ?? null;
        $disabled = $item['disabled'] ?? null;

        $defaultValue = $this->get('defaultValue') ?? [];
        $rootDisabled = $this->get('disabled') ?? false;

        return (object)[
            'expanded' => in_array($value, (array)$defaultValue, true),
            'disabled' => $disabled ?? $rootDisabled, // null if not set so it can be directly uses as `data-disabled` by the TagAttributes class
        ];
    }
}
